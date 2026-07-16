<?php

namespace App\Console\Commands;

use App\Services\WithdrawalService;
use Illuminate\Console\Command;

/**
 * "Processing"te takılı kalan çekimleri zincir durumuna göre çözer
 * (tamamla veya iade et). Tek seferlik; sürekli için borsa:daemon çağırır.
 */
class ReconcileWithdrawalsCommand extends Command
{
    protected $signature = 'withdrawals:reconcile';
    protected $description = 'Yarıda kalan çekimleri zincir durumuna göre tamamlar veya iade eder.';

    public function handle(WithdrawalService $withdrawals): int
    {
        $resolved = $withdrawals->reconcile();

        $this->components->info("Çekim mutabakatı tamam. Çözülen: {$resolved}");

        return self::SUCCESS;
    }
}
