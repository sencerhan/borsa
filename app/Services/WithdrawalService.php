<?php

namespace App\Services;

use App\Enums\LedgerEntryType;
use App\Enums\WithdrawalStatus;
use App\Jobs\ProcessWithdrawalJob;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Para çekme akışı (simüle otomatik). Talep anında bakiye rezerve edilir (debit),
 * işleme job ile tamamlanır.
 */
class WithdrawalService
{
    public function __construct(private readonly WalletService $wallets) {}

    /**
     * Çekim talebi oluşturur: bakiyeyi anında düşer (yetersizse hata) ve job'ı kuyruğa atar.
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

        // Yanıt gönderildikten sonra otomatik sonlandırılır (worker gerektirmez)
        ProcessWithdrawalJob::dispatch($withdrawal->id)->afterResponse();

        return $withdrawal;
    }

    /** Çekimi sonlandırır (fon zaten talep anında düşülmüştür). */
    public function process(int $withdrawalId): void
    {
        DB::transaction(function () use ($withdrawalId) {
            /** @var Withdrawal|null $withdrawal */
            $withdrawal = Withdrawal::whereKey($withdrawalId)->lockForUpdate()->first();

            if ($withdrawal === null || $withdrawal->status !== WithdrawalStatus::Pending) {
                return;
            }

            $withdrawal->status = WithdrawalStatus::Completed;
            $withdrawal->processed_at = now();
            $withdrawal->save();
        });
    }
}
