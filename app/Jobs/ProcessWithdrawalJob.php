<?php

namespace App\Jobs;

use App\Services\WithdrawalService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Çekimi zincire gönderir. Çift gönderimi önlemek için otomatik retry KAPALI (tries=1);
 * yarıda kalan çekimler `withdrawals:reconcile` ile zincir durumuna göre çözülür.
 */
class ProcessWithdrawalJob implements ShouldQueue
{
    use Queueable;

    /** Çift zincir gönderimi riskine karşı yeniden deneme yok. */
    public int $tries = 1;

    public function __construct(public int $withdrawalId) {}

    public function handle(WithdrawalService $withdrawals): void
    {
        $withdrawals->process($this->withdrawalId);
    }
}
