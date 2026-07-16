<?php

namespace App\Services;

use App\Enums\LedgerEntryType;
use App\Exceptions\InsufficientBalanceException;
use App\Models\Asset;
use App\Models\LedgerEntry;
use App\Models\Wallet;
use App\Support\BigMath;
use Illuminate\Database\Eloquent\Model;

/**
 * Bakiye mutasyonlarının tek kapısı. Tüm metodlar bir DB transaction
 * içinde çağrılmalıdır; ilgili cüzdan satırı lockForUpdate ile kilitlenir
 * ve her değişim ledger_entries'e yazılır.
 */
class WalletService
{
    /** Cüzdanı oluşturur (yoksa) — kilit almadan. Oluşturulurken yatırma adresi (ve gerekiyorsa anahtarı) üretilir. */
    public function getOrCreate(int $userId, string $asset): Wallet
    {
        return Wallet::firstOrCreate(
            ['user_id' => $userId, 'asset' => $asset],
            ['available' => '0', 'locked' => '0'] + $this->generateDepositCredentials($asset),
        );
    }

    /**
     * Varlığın ağına göre yatırma adresi (ve gerekiyorsa şifreli özel anahtar) üretir.
     *
     * @return array{deposit_address: string, deposit_secret?: string}
     */
    private function generateDepositCredentials(string $asset): array
    {
        $network = Asset::where('symbol', $asset)->value('network');

        return match ($network) {
            // BK Chain (BitKuruş): borsanın kontrol ettiği Ed25519 anahtar çifti.
            // Public key = adres; secret key şifreli saklanır (gerçek çekim imzası için).
            'BK Chain' => $this->bkCredentials(),
            // TRON (TRC20): borsanın kontrol ettiği secp256k1 anahtar çifti (gerçek custody).
            'TRC20' => $this->tronCredentials(),
            // EVM benzeri: '0x' + 40 hex karakter
            'ERC20', 'BEP20' => ['deposit_address' => '0x'.$this->randomString(40, '0123456789abcdef')],
            // Bilinmeyen ağ: yalnızca rastgele bir adres
            default => ['deposit_address' => bin2hex(random_bytes(32))],
        };
    }

    /** BK Chain için anahtar çifti üretir; özel anahtarı şifreleyerek döndürür. */
    private function bkCredentials(): array
    {
        $keypair = \App\Support\BkKeypair::generate();

        return [
            'deposit_address' => $keypair['address'],
            'deposit_secret' => \Illuminate\Support\Facades\Crypt::encryptString($keypair['secret']),
        ];
    }

    /** TRON için secp256k1 anahtar çifti üretir; özel anahtarı şifreleyerek döndürür. */
    private function tronCredentials(): array
    {
        $keypair = \App\Support\TronCrypto::generate();

        return [
            'deposit_address' => $keypair['address_base58'],
            'deposit_secret' => \Illuminate\Support\Facades\Crypt::encryptString($keypair['private_key']),
        ];
    }

    /** Belirtilen alfabeden rastgele karakter dizisi üretir. */
    private function randomString(int $length, string $alphabet): string
    {
        $max = strlen($alphabet) - 1;
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $result .= $alphabet[random_int(0, $max)];
        }

        return $result;
    }

    /** Serbest bakiyeye ekler (yatırma, işlemden gelen varlık, iade). */
    public function credit(int $userId, string $asset, string $amount, LedgerEntryType $type, ?Model $reference = null): Wallet
    {
        $this->assertPositive($amount);
        $wallet = $this->lockWallet($userId, $asset);

        $wallet->available = BigMath::add($wallet->available, $amount);
        $wallet->save();

        event(new \App\Events\WalletUpdated($wallet));

        $this->record($wallet, $type, $amount, $reference);

        return $wallet;
    }

    /** Serbest bakiyeden düşer (çekim). Yetersizse hata. */
    public function debit(int $userId, string $asset, string $amount, LedgerEntryType $type, ?Model $reference = null): Wallet
    {
        $this->assertPositive($amount);
        $wallet = $this->lockWallet($userId, $asset);

        if (BigMath::lt($wallet->available, $amount)) {
            throw InsufficientBalanceException::for($asset);
        }

        $wallet->available = BigMath::sub($wallet->available, $amount);
        $wallet->save();

        event(new \App\Events\WalletUpdated($wallet));

        $this->record($wallet, $type, BigMath::mul($amount, '-1'), $reference);

        return $wallet;
    }

    /** Serbest bakiyeyi kilitli bakiyeye taşır (emir verirken). */
    public function lock(int $userId, string $asset, string $amount, ?Model $reference = null): Wallet
    {
        $this->assertPositive($amount);
        $wallet = $this->lockWallet($userId, $asset);

        if (BigMath::lt($wallet->available, $amount)) {
            throw InsufficientBalanceException::for($asset);
        }

        $wallet->available = BigMath::sub($wallet->available, $amount);
        $wallet->locked = BigMath::add($wallet->locked, $amount);
        $wallet->save();

        event(new \App\Events\WalletUpdated($wallet));

        $this->record($wallet, LedgerEntryType::Lock, BigMath::mul($amount, '-1'), $reference);

        return $wallet;
    }

    /** Kilitli bakiyeyi serbest bakiyeye geri taşır (emir iptali / fazla kilit iadesi). */
    public function unlock(int $userId, string $asset, string $amount, LedgerEntryType $type = LedgerEntryType::Unlock, ?Model $reference = null): Wallet
    {
        $this->assertPositive($amount);
        $wallet = $this->lockWallet($userId, $asset);

        $unlockAmount = BigMath::min($amount, $wallet->locked);
        if (BigMath::isZero($unlockAmount)) {
            return $wallet;
        }

        $wallet->locked = BigMath::sub($wallet->locked, $unlockAmount);
        $wallet->available = BigMath::add($wallet->available, $unlockAmount);
        $wallet->save();

        event(new \App\Events\WalletUpdated($wallet));

        $this->record($wallet, $type, $unlockAmount, $reference);

        return $wallet;
    }

    /** Kilitli bakiyeden düşer (işlem gerçekleşince kilitli varlık borsadan çıkar). */
    public function debitLocked(int $userId, string $asset, string $amount, LedgerEntryType $type, ?Model $reference = null): Wallet
    {
        $this->assertPositive($amount);
        $wallet = $this->lockWallet($userId, $asset);

        if (BigMath::lt($wallet->locked, $amount)) {
            throw InsufficientBalanceException::for($asset);
        }

        $wallet->locked = BigMath::sub($wallet->locked, $amount);
        $wallet->save();

        event(new \App\Events\WalletUpdated($wallet));

        // Available değişmez; locked düşer.
        $this->record($wallet, $type, '0', $reference);

        return $wallet;
    }

    /** Cüzdanı kilitleyerek getirir (yoksa oluşturur). */
    private function lockWallet(int $userId, string $asset): Wallet
    {
        $this->getOrCreate($userId, $asset);

        return Wallet::where('user_id', $userId)
            ->where('asset', $asset)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function record(Wallet $wallet, LedgerEntryType $type, string $amount, ?Model $reference): void
    {
        $entry = new LedgerEntry([
            'user_id' => $wallet->user_id,
            'asset' => $wallet->asset,
            'type' => $type,
            'amount' => $amount,
            'available_after' => $wallet->available,
            'locked_after' => $wallet->locked,
        ]);

        if ($reference !== null) {
            $entry->reference()->associate($reference);
        }

        $entry->save();
    }

    private function assertPositive(string $amount): void
    {
        if (! BigMath::isPositive($amount)) {
            throw new \InvalidArgumentException('Tutar pozitif olmalıdır.');
        }
    }
}
