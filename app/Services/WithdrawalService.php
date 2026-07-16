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
use App\Services\Chain\BkWithdrawalService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Para çekme akışı.
 *
 * Talep anında bakiye rezerve edilir (debit). BK$ için çekim GERÇEKTİR: zincir
 * üzerinde imzalı işlem gönderilir (bkz [[BkWithdrawalService]]); başarısızsa
 * bakiye iade edilir. Diğer varlıklar (ör. USDT) şimdilik simüle işlenir.
 */
class WithdrawalService
{
    public function __construct(
        private readonly WalletService $wallets,
        private readonly BkWithdrawalService $bkWithdrawal,
    ) {}

    /**
     * Çekim talebi oluşturur: bakiyeyi rezerve eder ve işlemi başlatır.
     */
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

        $network = Asset::where('symbol', $asset)->value('network');

        if ($network === 'BK Chain') {
            // BK$: gerçek zincir gönderimi (senkron). Hata olursa iade edilir.
            $this->sendBkOnChain($withdrawal, $address);
        } else {
            // Diğer ağlar: şimdilik simüle işleme (job).
            ProcessWithdrawalJob::dispatch($withdrawal->id)->delay(now()->addSeconds(5));
        }

        return $withdrawal;
    }

    /** BK$ çekimini zincirde gerçekleştirir; başarısızsa bakiyeyi iade eder ve hata fırlatır. */
    private function sendBkOnChain(Withdrawal $withdrawal, ?string $address): void
    {
        if ($address === null || $address === '') {
            $this->failAndRefund($withdrawal);
            throw WithdrawalFailedException::make('BK$ çekimi için alıcı adresi zorunludur.');
        }

        $wallet = Wallet::where('user_id', $withdrawal->user_id)->where('asset', 'BK$')->first();

        if ($wallet === null || $wallet->deposit_secret === null) {
            $this->failAndRefund($withdrawal);
            throw WithdrawalFailedException::make('Bu cüzdanın zincir anahtarı yok; çekim yapılamıyor.');
        }

        try {
            $txId = $this->bkWithdrawal->send(
                $wallet->deposit_address,
                Crypt::decryptString($wallet->deposit_secret),
                $address,
                (string) $withdrawal->amount,
            );
        } catch (WithdrawalFailedException $e) {
            $this->failAndRefund($withdrawal);
            throw $e;
        }

        $withdrawal->status = WithdrawalStatus::Completed;
        $withdrawal->txid = $txId;
        $withdrawal->processed_at = now();
        $withdrawal->save();
    }

    /** Rezerve edilen bakiyeyi iade eder ve çekimi başarısız işaretler. */
    private function failAndRefund(Withdrawal $withdrawal): void
    {
        DB::transaction(function () use ($withdrawal) {
            $this->wallets->credit(
                $withdrawal->user_id,
                $withdrawal->asset,
                (string) $withdrawal->amount,
                LedgerEntryType::Refund,
                $withdrawal,
            );

            $withdrawal->status = WithdrawalStatus::Failed;
            $withdrawal->save();
        });
    }

    /** Simüle çekimi sonlandırır (fon zaten talep anında düşülmüştür). */
    public function process(int $withdrawalId): void
    {
        DB::transaction(function () use ($withdrawalId) {
            /** @var Withdrawal|null $withdrawal */
            $withdrawal = Withdrawal::whereKey($withdrawalId)->lockForUpdate()->first();

            if ($withdrawal === null || $withdrawal->status !== WithdrawalStatus::Pending) {
                return;
            }

            $withdrawal->status = WithdrawalStatus::Completed;
            $withdrawal->txid = '0x'.bin2hex(random_bytes(32));
            $withdrawal->processed_at = now();
            $withdrawal->save();
        });
    }
}
