<?php

namespace App\Services;

use App\Enums\DepositStatus;
use App\Enums\LedgerEntryType;
use App\Jobs\ProcessDepositJob;
use App\Models\Deposit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Para yatırma akışı (simüle otomatik). Talep oluşturur, işlemeyi job'a delege eder.
 */
class DepositService
{
    public function __construct(private readonly WalletService $wallets) {}

    /** Yatırma talebi oluşturur ve otomatik işleme job'ını kuyruğa atar. */
    public function request(User $user, string $asset, string $amount): Deposit
    {
        $deposit = Deposit::create([
            'user_id' => $user->id,
            'asset' => $asset,
            'amount' => $amount,
            'status' => DepositStatus::Pending,
            'reference' => 'DEP-'.Str::upper(Str::random(12)),
        ]);

        // 5 saniye gecikmeyle kuyruğa ekle (otonom olarak işlenmesi için)
        ProcessDepositJob::dispatch($deposit->id)->delay(now()->addSeconds(5));

        return $deposit;
    }

    /** Yatırmayı işler: bakiyeyi artırır ve tamamlandı olarak işaretler. */
    public function process(int $depositId): void
    {
        DB::transaction(function () use ($depositId) {
            /** @var Deposit|null $deposit */
            $deposit = Deposit::whereKey($depositId)->lockForUpdate()->first();

            if ($deposit === null || $deposit->status !== DepositStatus::Pending) {
                return;
            }

            $this->wallets->credit($deposit->user_id, $deposit->asset, $deposit->amount, LedgerEntryType::Deposit, $deposit);

            $deposit->status = DepositStatus::Completed;
            $deposit->txid = '0x' . bin2hex(random_bytes(32));
            $deposit->processed_at = now();
            $deposit->save();
        });
    }
}
