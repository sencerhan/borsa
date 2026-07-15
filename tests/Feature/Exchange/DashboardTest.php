<?php

namespace Tests\Feature\Exchange;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\ExchangeTestHelpers;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use ExchangeTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_dashboard_shows_portfolio_value(): void
    {
        $market = $this->makeMarket();
        $market->update(['last_price' => '0.03']);

        $user = $this->createFundedUser();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->has('wallets')
                ->where('portfolioValue', fn ($v) => (float) $v > 0));
    }

    public function test_exchange_shows_closed_state_when_no_active_market(): void
    {
        $market = $this->makeMarket();
        $market->update(['is_active' => false]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/exchange')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('exchange/Index')
                ->where('market', null));
    }

    private function createFundedUser(): User
    {
        $user = User::factory()->create();
        $this->fund($user, 'USDT', '1000');
        $this->fund($user, 'BK$', '5000');

        return $user;
    }
}
