<?php

namespace App\Jobs;

use App\Services\DepositService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Yatırma talebini otomatik işler (simüle otomasyon).
 */
class ProcessDepositJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $depositId) {}

    public function handle(DepositService $deposits): void
    {
        $deposits->process($this->depositId);
    }
}
