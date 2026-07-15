<?php

namespace Tests\Feature\Exchange;

use App\Enums\DepositStatus;
use App\Enums\WithdrawalStatus;
use App\Exceptions\InsufficientBalanceException;
use App\Models\User;
use App\Services\DepositService;
use App\Services\WithdrawalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_withdrawal_reserves_funds_immediately_and_completes(): void
    {
        $this->makeMarket();
        $user = User::factory()->create();
        $this->fund($user, 'USDT', '1000');

        $withdrawals = app(WithdrawalService::class);
        $withdrawal = $withdrawals->request($user, 'USDT', '400');

        // Fon talep anında düşülür
        $this->assertBalance('600', $this->wallet($user, 'USDT')->available);

        $withdrawals->process($withdrawal->id);
        $this->assertSame(WithdrawalStatus::Completed, $withdrawal->fresh()->status);
        $this->assertBalance('600', $this->wallet($user, 'USDT')->available);
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
