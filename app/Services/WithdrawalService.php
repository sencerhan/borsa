<?php

namespace App\Services;

use App\Enums\LedgerEntryType;
use App\Enums\WithdrawalStatus;
use App\Exceptions\WithdrawalFailedException;
use App\Jobs\ProcessWithdrawalJob;
use App\Models\Asset;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Withdrawal;
use App\Services\Chain\BkChainClient;
use App\Services\Chain\BkWithdrawalService;
use App\Services\Chain\TronChainClient;
use App\Services\Chain\TronWithdrawalService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Para çekme akışı (gerçek zincir + mutabakat).
 *
 * Akış: talep anında bakiye rezerve edilir (debit) ve iş kuyruğa alınır. Job,
 * çekimi "Processing" olarak claim eder ve zincire gönderir. Ödeme yayınlanmadan
 * ÖNCE txid kaydedilir; böylece süreç çökerse mutabakat (reconcile) txid'in zincir
 * durumuna göre çekimi tamamlar ya da iade eder. Ödeme hiç yayınlanmadıysa iade güvenlidir.
 */
class WithdrawalService
{
    /** Bu süreden eski "Processing" çekimler mutabakata girer (dk). */
    private const RECONCILE_AFTER_MINUTES = 3;

    public function __construct(
        private readonly WalletService $wallets,
        private readonly BkWithdrawalService $bkWithdrawal,
        private readonly TronWithdrawalService $tronWithdrawal,
        private readonly BkChainClient $bkClient,
        private readonly TronChainClient $tronClient,
    ) {}

    /** Çekim talebi: bakiyeyi rezerve eder ve işlemeyi kuyruğa alır. */
    public function request(User $user, string $asset, string $amount, ?string $address = null): Withdrawal
    {
        $withdrawal = DB::transaction(function () use ($user, $asset, $amount, $address) {
            $withdrawal = Withdrawal::create([
                'user_id' => $user->id,
                'asset' => $asset,
                'amount' => $amount,
                'address' => $address,
                'status' => WithdrawalStatus::Pending,
                'reference' => 'WD-'.Str::upper(Str::random(12)),
            ]);

            // Çift harcamayı önlemek için fonu talep anında rezerve et
            $this->wallets->debit($user->id, $asset, $amount, LedgerEntryType::Withdraw, $withdrawal);

            return $withdrawal;
        });

        ProcessWithdrawalJob::dispatch($withdrawal->id);

        return $withdrawal;
    }

    /** Job girişi: çekimi claim eder ve zincire gönderir. */
    public function process(int $withdrawalId): void
    {
        // Atomik claim: yalnızca Pending → Processing.
        $withdrawal = DB::transaction(function () use ($withdrawalId) {
            /** @var Withdrawal|null $w */
            $w = Withdrawal::whereKey($withdrawalId)->lockForUpdate()->first();

            if ($w === null || $w->status !== WithdrawalStatus::Pending) {
                return null;
            }

            $w->status = WithdrawalStatus::Processing;
            $w->save();

            return $w;
        });

        if ($withdrawal === null) {
            return; // zaten işleniyor/işlendi
        }

        $this->dispatchOnChain($withdrawal);
    }

    /** Çekimi ağına göre zincire gönderir; ödeme yayınlanmadıysa iade eder. */
    private function dispatchOnChain(Withdrawal $withdrawal): void
    {
        $network = Asset::where('symbol', $withdrawal->asset)->value('network');

        // Ödeme yayınlanmadan hemen önce txid'yi kaydeder (mutabakat için).
        $paymentBroadcast = false;
        $recordTxId = function (string $txid) use (&$paymentBroadcast, $withdrawal): void {
            $paymentBroadcast = true;
            $withdrawal->txid = $txid;
            $withdrawal->save();
        };

        try {
            $txid = match ($network) {
                'BK Chain' => $this->sendBk($withdrawal, $recordTxId),
                'TRC20' => $this->sendUsdt($withdrawal, $recordTxId),
                default => throw WithdrawalFailedException::make('Desteklenmeyen çekim ağı.'),
            };

            $this->complete($withdrawal, $txid);
        } catch (\Throwable $e) {
            if (! $paymentBroadcast) {
                // Ödeme hiç yayınlanmadı → fonu güvenle iade et.
                $this->failAndRefund($withdrawal);

                return;
            }

            // Ödeme yayınlandı ama onay/tamamlanma belirsiz → iade ETME.
            // Processing olarak bırak; reconcile txid'e göre kesin karar verir.
            Log::warning('Çekim yayınlandı, onay bekliyor (reconcile edilecek).', [
                'withdrawal_id' => $withdrawal->id,
                'txid' => $withdrawal->txid,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendBk(Withdrawal $withdrawal, callable $recordTxId): string
    {
        $wallet = Wallet::where('user_id', $withdrawal->user_id)->where('asset', 'BK$')->first();

        if ($withdrawal->address === null || $wallet === null || $wallet->deposit_secret === null) {
            throw WithdrawalFailedException::make('BK$ çekimi için adres/anahtar eksik.');
        }

        return $this->bkWithdrawal->send(
            $wallet->deposit_address,
            Crypt::decryptString($wallet->deposit_secret),
            $withdrawal->address,
            (string) $withdrawal->amount,
            $recordTxId,
        );
    }

    private function sendUsdt(Withdrawal $withdrawal, callable $recordTxId): string
    {
        $hotAddress = config('services.trongrid.hot_wallet_address');
        $hotKey = config('services.trongrid.hot_wallet_key');

        if ($withdrawal->address === null || empty($hotAddress) || empty($hotKey)) {
            throw WithdrawalFailedException::make('USDT çekimi için adres/hot-wallet eksik.');
        }

        return $this->tronWithdrawal->send(
            Crypt::decryptString($hotKey),
            (string) $hotAddress,
            $withdrawal->address,
            (string) $withdrawal->amount,
            $recordTxId,
        );
    }

    /**
     * Mutabakat: "Processing"te takılı çekimleri zincir durumuna göre çözer.
     * Daemon tarafından periyodik çağrılır. Çözülen çekim sayısını döndürür.
     */
    public function reconcile(): int
    {
        $cutoff = CarbonImmutable::now()->subMinutes(self::RECONCILE_AFTER_MINUTES);
        $resolved = 0;

        Withdrawal::where('status', WithdrawalStatus::Processing)
            ->where('updated_at', '<', $cutoff)
            ->orderBy('id')
            ->each(function (Withdrawal $withdrawal) use (&$resolved) {
                if ($this->reconcileOne($withdrawal)) {
                    $resolved++;
                }
            });

        return $resolved;
    }

    private function reconcileOne(Withdrawal $withdrawal): bool
    {
        // txid yoksa: ödeme hiç yayınlanmadı (merge fonu adreste tutar, güvenli) → iade.
        if ($withdrawal->txid === null) {
            $this->failAndRefund($withdrawal);

            return true;
        }

        $network = Asset::where('symbol', $withdrawal->asset)->value('network');

        $status = match ($network) {
            'BK Chain' => $this->normalizeBk($this->bkClient->txStatus($withdrawal->txid)),
            'TRC20' => $this->tronClient->txStatus($withdrawal->txid),
            default => 'pending',
        };

        return match ($status) {
            'success' => tap(true, fn () => $this->complete($withdrawal, $withdrawal->txid)),
            'failed' => tap(true, fn () => $this->failAndRefund($withdrawal)),
            default => false, // pending → bir sonraki turda tekrar bak
        };
    }

    /** BK durumunu ortak sözlüğe çevirir (committed→success, rejected→failed). */
    private function normalizeBk(?string $status): string
    {
        return match ($status) {
            'committed' => 'success',
            'rejected' => 'failed',
            default => 'pending',
        };
    }

    private function complete(Withdrawal $withdrawal, string $txid): void
    {
        DB::transaction(function () use ($withdrawal, $txid) {
            /** @var Withdrawal $fresh */
            $fresh = Withdrawal::whereKey($withdrawal->id)->lockForUpdate()->first();

            if ($fresh->status === WithdrawalStatus::Completed) {
                return; // idempotent
            }

            $fresh->status = WithdrawalStatus::Completed;
            $fresh->txid = $txid;
            $fresh->processed_at = now();
            $fresh->save();
        });
    }

    /** Rezerve edilen bakiyeyi iade eder ve çekimi başarısız işaretler (idempotent). */
    private function failAndRefund(Withdrawal $withdrawal): void
    {
        DB::transaction(function () use ($withdrawal) {
            /** @var Withdrawal $fresh */
            $fresh = Withdrawal::whereKey($withdrawal->id)->lockForUpdate()->first();

            // Yalnızca henüz sonuçlanmamış çekimler iade edilir (çift iade koruması).
            if (! in_array($fresh->status, [WithdrawalStatus::Pending, WithdrawalStatus::Processing], true)) {
                return;
            }

            $this->wallets->credit(
                $fresh->user_id,
                $fresh->asset,
                (string) $fresh->amount,
                LedgerEntryType::Refund,
                $fresh,
            );

            $fresh->status = WithdrawalStatus::Failed;
            $fresh->save();
        });
    }
}
