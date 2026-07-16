<?php

namespace App\Console\Commands;

use App\Services\DepositVerificationService;
use Illuminate\Console\Command;

/**
 * Zincir üzerinden gelen yatırmaları tarar ve doğrulanmışları cüzdanlara ekler.
 * Tek seferlik çalışır; sürekli tarama için borsa:daemon içinden periyodik çağrılır.
 */
class ScanDepositsCommand extends Command
{
    protected $signature = 'deposits:scan';
    protected $description = 'Zincirden gelen (BK$/USDT) yatırmaları doğrular ve cüzdanlara işler.';

    public function handle(DepositVerificationService $verifier): int
    {
        $credited = $verifier->scanAll();

        $this->components->info("Yatırma taraması tamam. Yeni kredilenen: {$credited}");

        return self::SUCCESS;
    }
}
