<?php

namespace App\Services\Admin;

use App\Enums\DepositStatus;
use App\Enums\WithdrawalStatus;
use App\Events\SystemStatsUpdated;
use App\Models\Deposit;
use App\Models\Market;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Withdrawal;
use App\Services\DepositService;
use App\Services\WithdrawalService;
use App\Support\BigMath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Sahte para yatırma ve çekme işlemlerini otonom simüle eden servis.
 * Gerçek kullanıcı deneyimi simülasyonu için kuyruğa talepler gönderir.
 */
class TransactionSimulatorService
{
    public function __construct(
        private readonly DepositService $deposits,
        private readonly WithdrawalService $withdrawals,
    ) {}

    /**
     * Simülatörü 1 adım çalıştırır.
     */
    public function tick(): void
    {
        // Aktif simülatör ayarı olan market var mı?
        $activeSimulatorMarket = Market::where('simulator_active', true)->where('is_active', true)->first();
        
        if ($activeSimulatorMarket === null) {
            $this->broadcastStats();
            return;
        }

        try {
            // Test kullanıcısını bul (sadece test@example.com üzerinden veya rastgele user)
            $user = User::where('email', 'test@example.com')->first();
            
            if ($user !== null) {
                $rand = random_int(1, 100);

                if ($rand <= 15) {
                    // %15 Olasılıkla Yatırma Simüle Et
                    $this->simulateDeposit($user);
                } elseif ($rand <= 25) {
                    // %10 Olasılıkla Çekme Simüle Et
                    $this->simulateWithdrawal($user);
                }
            }
        } catch (\Throwable $e) {
            Log::error("Yatırma/Çekme Simülatör hatası: " . $e->getMessage());
        }

        // Güncel istatistikleri Reverb üzerinden admin paneline yolla
        $this->broadcastStats();
    }

    private function simulateDeposit(User $user): void
    {
        $asset = random_int(0, 1) === 1 ? 'USDT' : 'BK$';
        
        // Rastgele tutar üret
        if ($asset === 'USDT') {
            $amount = (string) random_int(50, 1000);
        } else {
            $amount = (string) random_int(1000, 50000);
        }

        $this->deposits->request($user, $asset, $amount);
    }

    private function simulateWithdrawal(User $user): void
    {
        $asset = random_int(0, 1) === 1 ? 'USDT' : 'BK$';
        
        // Kullanıcının cüzdan bakiyesini kontrol et
        $wallet = Wallet::where('user_id', $user->id)->where('asset', $asset)->first();
        
        if ($wallet === null || BigMath::lte($wallet->available, '50.000000')) {
            return;
        }

        // Bakiyesinin küçük bir kısmını çek (maks %10)
        $maxWithdraw = BigMath::div($wallet->available, '10');
        $amountVal = random_int(10, max(20, (int)floatval($maxWithdraw)));
        $amount = (string) $amountVal;
        
        $address = $asset === 'USDT' 
            ? '0x' . bin2hex(random_bytes(20))
            : 'bk1' . bin2hex(random_bytes(19));

        $this->withdrawals->request($user, $asset, $amount, $address);
    }

    public function broadcastStats(): void
    {
        try {
            $pendingJobs = DB::table('jobs')->count();
            
            $today = now()->startOfDay();
            
            $completedDeposits = Deposit::where('status', DepositStatus::Completed)
                ->where('processed_at', '>=', $today)
                ->count();

            $completedWithdrawals = Withdrawal::where('status', WithdrawalStatus::Completed)
                ->where('processed_at', '>=', $today)
                ->count();

            $stats = [
                'pending_jobs' => $pendingJobs,
                'completed_today' => $completedDeposits + $completedWithdrawals,
                'completed_deposits' => $completedDeposits,
                'completed_withdrawals' => $completedWithdrawals,
            ];

            event(new SystemStatsUpdated($stats));
        } catch (\Throwable $e) {
            // Hata olursa sessizce logla
            Log::debug("İstatistik yayınlanamadı: " . $e->getMessage());
        }
    }
}
