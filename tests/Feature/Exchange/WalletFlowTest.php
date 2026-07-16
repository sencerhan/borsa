<?php

namespace Tests\Feature\Exchange;

use App\Enums\DepositStatus;
use App\Enums\WithdrawalStatus;
use App\Exceptions\InsufficientBalanceException;
use App\Models\User;
use App\Jobs\ProcessWithdrawalJob;
use App\Services\DepositService;
use App\Services\WithdrawalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\ExchangeTestHelpers;
use Tests\TestCase;

class WalletFlowTest extends TestCase
{
    use ExchangeTestHelpers, RefreshDatabase;

    public function test_deposit_request_then_process_credits_balance(): void
    {
        $this->makeMarket();
        $user = User::factory()->create();
        $deposits = app(DepositService::class);

        $deposit = $deposits->request($user, 'USDT', '500');
        $this->assertSame(DepositStatus::Pending, $deposit->status);

        $deposits->process($deposit->id);

        $this->assertSame(DepositStatus::Completed, $deposit->fresh()->status);
        $this->assertBalance('500', $this->wallet($user, 'USDT')->available);
    }

    public function test_deposit_process_is_idempotent(): void
    {
        $this->makeMarket();
        $user = User::factory()->create();
        $deposits = app(DepositService::class);

        $deposit = $deposits->request($user, 'USDT', '500');
        $deposits->process($deposit->id);
        $deposits->process($deposit->id); // ikinci kez çalışmamalı

        $this->assertBalance('500', $this->wallet($user, 'USDT')->available);
    }

    public function test_withdrawal_reserves_funds_immediately(): void
    {
        Queue::fake(); // işlemeyi (job) ayrı tut; sadece rezerveyi ölç
        $this->makeMarket();
        $user = User::factory()->create();
        $this->fund($user, 'USDT', '1000');

        app(WithdrawalService::class)->request($user, 'USDT', '400', 'TR3LGiGDFTnKkbXL64jTH1DAvTpGmhxeQk');

        // Fon talep anında düşülür (rezerve) ve işleme kuyruğa alınır
        $this->assertBalance('600', $this->wallet($user, 'USDT')->available);
        Queue::assertPushed(ProcessWithdrawalJob::class);
    }

    public function test_withdrawal_refunds_when_chain_send_cannot_proceed(): void
    {
        Queue::fake();
        $this->makeMarket();
        $user = User::factory()->create();
        $this->fund($user, 'USDT', '1000');

        $withdrawals = app(WithdrawalService::class);
        // Adressiz USDT çekimi → ödeme hiç yayınlanamaz → güvenli iade
        $withdrawal = $withdrawals->request($user, 'USDT', '400', null);
        $this->assertBalance('600', $this->wallet($user, 'USDT')->available);

        $withdrawals->process($withdrawal->id);

        // Ödeme yayınlanmadığı için fon iade edilir, çekim başarısız
        $this->assertSame(WithdrawalStatus::Failed, $withdrawal->fresh()->status);
        $this->assertBalance('1000', $this->wallet($user, 'USDT')->available);
    }

    public function test_withdrawal_over_balance_throws(): void
    {
        $this->makeMarket();
        $user = User::factory()->create();
        $this->fund($user, 'USDT', '100');

        $this->expectException(InsufficientBalanceException::class);
        app(WithdrawalService::class)->request($user, 'USDT', '500');
    }
}
