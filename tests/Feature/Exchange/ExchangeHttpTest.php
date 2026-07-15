<?php

namespace Tests\Feature\Exchange;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\ExchangeTestHelpers;
use Tests\TestCase;

class ExchangeHttpTest extends TestCase
{
    use ExchangeTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Frontend derleme (Vite manifest) gerektirmeden Inertia sayfalarını test et
        $this->withoutVite();
    }

    public function test_guests_cannot_view_exchange(): void
    {
        $this->makeMarket();
        $this->get('/exchange')->assertRedirect(route('login'));
    }

    public function test_authenticated_user_sees_exchange_page(): void
    {
        $this->makeMarket();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/exchange')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('exchange/Index')
                ->has('market')
                ->has('orderBook'));
    }

    public function test_user_can_place_limit_order_via_http(): void
    {
        $market = $this->makeMarket();
        $user = User::factory()->create();
        $this->fund($user, 'USDT', '100');

        $this->actingAs($user)
            ->post('/exchange/orders', [
                'market_id' => $market->id,
                'side' => 'buy',
                'type' => 'limit',
                'price' => '0.03',
                'quantity' => '1000',
            ])
            ->assertRedirect();

        $this->assertSame(1, Order::where('user_id', $user->id)->count());
        $this->assertBalance('70', $this->wallet($user, 'USDT')->available); // 30 kilitlendi
    }

    public function test_order_validation_rejects_missing_price_for_limit(): void
    {
        $market = $this->makeMarket();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/exchange/orders', [
                'market_id' => $market->id,
                'side' => 'buy',
                'type' => 'limit',
                'quantity' => '1000',
            ])
            ->assertSessionHasErrors('price');
    }

    public function test_non_admin_cannot_access_market_settings(): void
    {
        $this->makeMarket();
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin/markets')->assertForbidden();
    }

    public function test_admin_can_view_and_update_market_settings(): void
    {
        $market = $this->makeMarket();
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        $this->actingAs($admin)->get('/admin/markets')->assertOk();

        $this->actingAs($admin)
            ->patch("/admin/markets/{$market->id}", [
                'maker_fee' => '0.0005',
                'taker_fee' => '0.0015',
                'min_order_size' => '5',
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertBalance('0.0005', $market->fresh()->maker_fee);
    }
}
