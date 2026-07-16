<?php

namespace App\Services;

use App\Enums\DepositStatus;
use App\Enums\LedgerEntryType;
use App\Models\Deposit;
use App\Models\Wallet;
use App\Services\Chain\BkChainVerifier;
use App\Services\Chain\ChainDepositVerifier;
use App\Services\Chain\TronUsdtVerifier;
use App\Support\BigMath;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Zincir üzerinden yatırma doğrulama.
 *
 * Kullanıcı deposit adresine gerçek transfer yapar; bu servis ilgili zincirin
 * API'sinden gelen (receive) transferleri okur ve YALNIZCA onaylı, daha önce
 * işlenmemiş transferleri gerçek tutarıyla cüzdana ekler. Kullanıcının girdiği
 * bir tutar yoktur — tutar her zaman zincirden gelir.
 */
class DepositVerificationService
{
    public function __construct(private readonly WalletService $wallets) {}

    /** Tüm ilgili cüzdanları tarar; toplam yeni kredilenen yatırma sayısını döndürür. */
    public function scanAll(): int
    {
        $credited = 0;

        Wallet::whereNotNull('deposit_address')->chunkById(200, function ($wallets) use (&$credited) {
            foreach ($wallets as $wallet) {
                $credited += $this->scanWallet($wallet);
            }
        });

        return $credited;
    }

    /** Bir kullanıcının tüm cüzdanlarını tarar; kredilenen yeni yatırma sayısını döndürür. */
    public function scanUser(int $userId): int
    {
        $credited = 0;

        foreach (Wallet::where('user_id', $userId)->whereNotNull('deposit_address')->get() as $wallet) {
            $credited += $this->scanWallet($wallet);
        }

        return $credited;
    }

    /** Tek bir cüzdanı tarar; bu tarama sırasında kredilenen yeni yatırma sayısını döndürür. */
    public function scanWallet(Wallet $wallet): int
    {
        $verifier = $this->verifierForAsset($wallet->asset);

        if ($verifier === null || $wallet->deposit_address === null) {
            return 0;
        }

        $credited = 0;

        foreach ($verifier->fetchIncoming($wallet->deposit_address) as $transfer) {
            // Sıfır/negatif tutarı yok say
            if (! BigMath::isValid($transfer->amount) || ! BigMath::isPositive($transfer->amount)) {
                continue;
            }

            // Borsanın kendi ürettiği token (çekim para üstü / merge çıktısı) yatırma değildir.
            if (\App\Models\ChainInternalToken::isInternal($wallet->asset, $transfer->txid)) {
                continue;
            }

            if ($this->creditIfNew($wallet, $transfer->txid, $transfer->amount)) {
                $credited++;
            }
        }

        return $credited;
    }

    /**
     * Bu (asset, txid) daha önce işlenmediyse yatırmayı oluşturur ve cüzdana ekler.
     * Idempotent: unique (asset, txid) indeksi çift kredilemeyi engeller.
     */
    private function creditIfNew(Wallet $wallet, string $txid, string $amount): bool
    {
        // Erken çıkış (yaygın durum): zaten işlenmiş.
        if (Deposit::where('asset', $wallet->asset)->where('txid', $txid)->exists()) {
            return false;
        }

        try {
            return DB::transaction(function () use ($wallet, $txid, $amount) {
                $deposit = Deposit::create([
                    'user_id' => $wallet->user_id,
                    'asset' => $wallet->asset,
                    'amount' => $amount,
                    'status' => DepositStatus::Completed,
                    'reference' => 'CHAIN-'.substr($txid, 0, 16),
                    'txid' => $txid,
                    'processed_at' => now(),
                ]);

                $this->wallets->credit(
                    $wallet->user_id,
                    $wallet->asset,
                    $amount,
                    LedgerEntryType::Deposit,
                    $deposit,
                );

                return true;
            });
        } catch (QueryException $e) {
            // Eşzamanlı tarama aynı txid'i eklemeye çalıştıysa: unique ihlali → zaten işlenmiş say.
            return false;
        }
    }

    /** Varlığın ağına göre uygun zincir doğrulayıcısını üretir (yoksa null). */
    private function verifierForAsset(string $asset): ?ChainDepositVerifier
    {
        $network = \App\Models\Asset::where('symbol', $asset)->value('network');

        return match ($network) {
            'BK Chain' => new BkChainVerifier(
                baseUrl: (string) config('services.bitkurush.base_url'),
            ),
            'TRC20' => new TronUsdtVerifier(
                baseUrl: (string) config('services.trongrid.base_url'),
                usdtContract: (string) config('services.trongrid.usdt_contract'),
                apiKey: config('services.trongrid.api_key'),
                limit: (int) config('services.trongrid.limit', 50),
            ),
            default => null,
        };
    }
}
