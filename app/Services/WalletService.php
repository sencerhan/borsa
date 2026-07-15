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
    /** Cüzdanı oluşturur (yoksa) — kilit almadan. Oluşturulurken simüle yatırma adresi üretilir. */
    public function getOrCreate(int $userId, string $asset): Wallet
    {
        return Wallet::firstOrCreate(
            ['user_id' => $userId, 'asset' => $asset],
            ['available' => '0', 'locked' => '0', 'deposit_address' => $this->generateDepositAddress($asset)],
        );
    }

    /**
     * Varlığın ağına göre simüle bir yatırma adresi üretir (blokzincir yok).
     */
    private function generateDepositAddress(string $asset): string
    {
        $network = Asset::where('symbol', $asset)->value('network');

        return match ($network) {
            // TRON (TRC20) benzeri: 'T' + 33 base58 karakter
            'TRC20' => 'T'.$this->randomString(33, '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz'),
            // EVM benzeri: '0x' + 40 hex karakter
            'ERC20', 'BEP20' => '0x'.$this->randomString(40, '0123456789abcdef'),
            // Yerel BK ağı
            default => 'bk1'.$this->randomString(38, '023456789acdefghjklmnpqrstuvwxyz'),
        };
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
