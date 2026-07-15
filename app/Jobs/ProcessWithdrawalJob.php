<?php

namespace App\Jobs;

use App\Services\WithdrawalService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Çekim talebini otomatik sonlandırır (simüle otomasyon).
 */
class ProcessWithdrawalJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $withdrawalId) {}

    public function handle(WithdrawalService $withdrawals): void
    {
        $withdrawals->process($this->withdrawalId);
    }
}
