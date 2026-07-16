<?php

namespace App\Console\Commands;

use App\Models\Market;
use App\Services\Admin\MarketBotService;
use App\Services\Admin\TransactionSimulatorService;
use App\Services\DepositVerificationService;
use App\Services\WithdrawalService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class BorsaDaemonCommand extends Command
{
    protected $signature = 'borsa:daemon {--delay=1 : Her döngü adımındaki bekleme süresi (saniye)}';
    protected $description = 'Borsa otonom işlem botlarını, yatırma/çekme simülatörünü ve kuyruk işlemlerini çalıştırır.';

    private bool $shouldKeepRunning = true;

    public function handle(
        MarketBotService $botService,
        TransactionSimulatorService $simulatorService,
        DepositVerificationService $depositVerifier,
        WithdrawalService $withdrawals
    ): int {
        $this->components->info('BitKuruş Borsa Otonom Daemon Başlatılıyor...');
        $this->components->info('Çıkmak için CTRL+C tuşlarına basın.');

        // PCNTL sinyalleri varsa temiz durdurma için dinle
        if (function_exists('pcntl_signal')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGINT, function () {
                $this->shouldKeepRunning = false;
                $this->components->info('Durduruluyor...');
            });
            pcntl_signal(SIGTERM, function () {
                $this->shouldKeepRunning = false;
                $this->components->info('Durduruluyor...');
            });
        }

        // Son çalışma zamanlarını tutalım
        $lastBotRun = [];
        $lastSimulatorRun = 0;
        $lastDepositScan = 0;
        $depositScanInterval = 20; // sn: zincir yatırma taraması sıklığı
        $lastWithdrawalReconcile = 0;
        $withdrawalReconcileInterval = 30; // sn: takılı çekim mutabakatı sıklığı

        $delay = (int) $this->option('delay');

        while ($this->shouldKeepRunning) {
            $now = time();

            // 0. Zincirden gelen yatırmaları doğrula ve işle (BK$/USDT)
            if ($now - $lastDepositScan >= $depositScanInterval) {
                try {
                    $credited = $depositVerifier->scanAll();
                    if ($credited > 0) {
                        $this->line("<info>[YATIRMA]</info> {$credited} yeni onaylı yatırma işlendi.");
                    }
                } catch (\Throwable $e) {
                    Log::error('Yatırma tarama hatası: '.$e->getMessage());
                }
                $lastDepositScan = $now;
            }

            // 0b. Yarıda kalan çekimleri zincir durumuna göre çöz (tamamla/iade)
            if ($now - $lastWithdrawalReconcile >= $withdrawalReconcileInterval) {
                try {
                    $resolved = $withdrawals->reconcile();
                    if ($resolved > 0) {
                        $this->line("<info>[MUTABAKAT]</info> {$resolved} takılı çekim çözüldü.");
                    }
                } catch (\Throwable $e) {
                    Log::error('Çekim mutabakat hatası: '.$e->getMessage());
                }
                $lastWithdrawalReconcile = $now;
            }

            // Aktif marketleri getir
            $markets = Market::where('is_active', true)->get();

            foreach ($markets as $market) {
                // 1. Piyasa Yapıcı Botunu Çalıştır
                if ($market->bot_active) {
                    $interval = $market->bot_interval ?: 10;
                    $lastRun = $lastBotRun[$market->id] ?? 0;

                    if ($now - $lastRun >= $interval) {
                        $this->line("<info>[BOT] {$market->symbol}</info> tetikleniyor... (Ref Fiyat: {$market->bot_reference_price})");
                        $botService->tick($market);
                        $lastBotRun[$market->id] = $now;
                    }
                }

                // 2. Yatırma/Çekme Simülatörünü Çalıştır
                if ($market->simulator_active) {
                    $simInterval = $market->simulator_interval ?: 30;

                    if ($now - $lastSimulatorRun >= $simInterval) {
                        $this->line("<info>[SİMÜLATÖR]</info> tetikleniyor...");
                        $simulatorService->tick();
                        $lastSimulatorRun = $now;
                    }
                }
            }

            // 3. Kuyruktaki İşleri Temizle (Otonom Kuyruk İşleyici)
            // stop-when-empty: kuyruk boşalana kadar TÜM işleri (yayınlar dahil) eritir, sonra döner.
            // max-jobs güvenlik sınırı: bir döngüde sonsuza kadar takılmayı önler.
            try {
                Artisan::call('queue:work', [
                    '--stop-when-empty' => true,
                    '--backoff' => 0,
                    '--tries' => 1,
                    '--max-jobs' => 200,
                    '--max-time' => 15,
                ]);
            } catch (\Throwable $e) {
                // Kuyruk işleme hatasını loglama ama daemon'ı çökertme
            }

            // Her adımda stats güncellemesini tetikle
            $simulatorService->broadcastStats();

            sleep($delay);
        }

        $this->components->info('BitKuruş Borsa Otonom Daemon başarıyla durduruldu.');
        return Command::SUCCESS;
    }
}
