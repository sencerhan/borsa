<?php

namespace Tests\Feature\Exchange;

use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\InvalidOrderException;
use App\Models\Order;
use App\Models\Trade;
use App\Models\User;
use App\Services\Data\PlaceOrderData;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ExchangeTestHelpers;
use Tests\TestCase;

class MatchingEngineTest extends TestCase
{
    use ExchangeTestHelpers, RefreshDatabase;

    private function orders(): OrderService
    {
        return app(OrderService::class);
    }

    public function test_limit_buy_fully_matches_resting_limit_sell_with_fees(): void
    {
        $market = $this->makeMarket();
        $seller = User::factory()->create();
        $buyer = User::factory()->create();

        $this->fund($seller, 'BK$', '1000');
        $this->fund($buyer, 'USDT', '30'); // tam olarak 1000 * 0.03

        // Satıcı 1000 BK$'i 0.03'ten satışa koyar (maker)
        $this->orders()->place($seller, new PlaceOrderData($market->id, OrderSide::Sell, OrderType::Limit, price: '0.03', quantity: '1000'));

        // Alıcı 1000 BK$'i 0.03'ten alır (taker)
        $order = $this->orders()->place($buyer, new PlaceOrderData($market->id, OrderSide::Buy, OrderType::Limit, price: '0.03', quantity: '1000'));

        $this->assertSame(OrderStatus::Filled, $order->status);
        $this->assertSame(1, Trade::count());

        // Alıcı taker: fee = 1000 * 0.002 = 2 BK$ → net 998 BK$
        $this->assertBalance('998', $this->wallet($buyer, 'BK$')->available);
        $this->assertBalance('0', $this->wallet($buyer, 'USDT')->available);
        $this->assertBalance('0', $this->wallet($buyer, 'USDT')->locked);

        // Satıcı maker: quote = 30 USDT, fee = 30 * 0.001 = 0.03 → net 29.97 USDT
        $this->assertBalance('29.97', $this->wallet($seller, 'USDT')->available);
        $this->assertBalance('0', $this->wallet($seller, 'BK$')->locked);
    }

    public function test_partial_fill_leaves_remainder_resting(): void
    {
        $market = $this->makeMarket();
        $seller = User::factory()->create();
        $buyer = User::factory()->create();

        $this->fund($seller, 'BK$', '1000');
        $this->fund($buyer, 'USDT', '100');

        // Satıcı 400 BK$ satışta
        $this->orders()->place($seller, new PlaceOrderData($market->id, OrderSide::Sell, OrderType::Limit, price: '0.03', quantity: '400'));

        // Alıcı 1000 almak ister, 400 eşleşir, 600 defterde kalır
        $order = $this->orders()->place($buyer, new PlaceOrderData($market->id, OrderSide::Buy, OrderType::Limit, price: '0.03', quantity: '1000'));

        $this->assertSame(OrderStatus::PartiallyFilled, $order->status);
        $this->assertBalance('400', $order->filled_quantity);
        // Kalan 600 * 0.03 = 18 USDT kilitli kalır
        $this->assertBalance('18', $this->wallet($buyer, 'USDT')->locked);
    }

    public function test_price_time_priority_matches_best_price_first(): void
    {
        $market = $this->makeMarket();
        $s1 = User::factory()->create();
        $s2 = User::factory()->create();
        $buyer = User::factory()->create();

        $this->fund($s1, 'BK$', '1000');
        $this->fund($s2, 'BK$', '1000');
        $this->fund($buyer, 'USDT', '1000');

        // Pahalı satış önce girer, ucuz sonra
        $this->orders()->place($s1, new PlaceOrderData($market->id, OrderSide::Sell, OrderType::Limit, price: '0.032', quantity: '1000'));
        $this->orders()->place($s2, new PlaceOrderData($market->id, OrderSide::Sell, OrderType::Limit, price: '0.030', quantity: '1000'));

        // Alıcı 1000 alır → en ucuz (0.030 / s2) ile eşleşmeli
        $this->orders()->place($buyer, new PlaceOrderData($market->id, OrderSide::Buy, OrderType::Limit, price: '0.035', quantity: '1000'));

        $trade = Trade::firstOrFail();
        $this->assertBalance('0.030', $trade->price);
        $this->assertSame($s2->id, $trade->seller_id);
    }

    public function test_buy_at_better_maker_price_refunds_difference(): void
    {
        $market = $this->makeMarket(['maker_fee' => '0', 'taker_fee' => '0']);
        $seller = User::factory()->create();
        $buyer = User::factory()->create();

        $this->fund($seller, 'BK$', '1000');
        $this->fund($buyer, 'USDT', '100');

        // Satış 0.03'ten
        $this->orders()->place($seller, new PlaceOrderData($market->id, OrderSide::Sell, OrderType::Limit, price: '0.03', quantity: '1000'));

        // Alıcı 0.05'ten almaya razı (50 USDT kilitlenir) ama 0.03'ten alır → 20 USDT iade
        $this->orders()->place($buyer, new PlaceOrderData($market->id, OrderSide::Buy, OrderType::Limit, price: '0.05', quantity: '1000'));

        $this->assertBalance('70', $this->wallet($buyer, 'USDT')->available); // 100 - 30 harcandı
        $this->assertBalance('0', $this->wallet($buyer, 'USDT')->locked);
        $this->assertBalance('1000', $this->wallet($buyer, 'BK$')->available);
    }

    public function test_market_buy_sweeps_book_by_budget(): void
    {
        $market = $this->makeMarket(['maker_fee' => '0', 'taker_fee' => '0']);
        $seller = User::factory()->create();
        $buyer = User::factory()->create();

        $this->fund($seller, 'BK$', '10000');
        $this->fund($buyer, 'USDT', '100');

        $this->orders()->place($seller, new PlaceOrderData($market->id, OrderSide::Sell, OrderType::Limit, price: '0.031', quantity: '3000'));
        $this->orders()->place($seller, new PlaceOrderData($market->id, OrderSide::Sell, OrderType::Limit, price: '0.032', quantity: '3000'));

        // 100 USDT bütçe: 3000@0.031 = 93, kalan 7 → 7/0.032 = 218.75
        $order = $this->orders()->place($buyer, new PlaceOrderData($market->id, OrderSide::Buy, OrderType::Market, quoteAmount: '100'));

        $this->assertSame(OrderStatus::Filled, $order->status);
        $this->assertBalance('100', $order->quote_filled);
        $this->assertBalance('3218.75', $this->wallet($buyer, 'BK$')->available);
        $this->assertBalance('0', $this->wallet($buyer, 'USDT')->available);
    }

    public function test_market_order_on_empty_book_throws(): void
    {
        $market = $this->makeMarket();
        $buyer = User::factory()->create();
        $this->fund($buyer, 'USDT', '100');

        $this->expectException(InvalidOrderException::class);
        $this->orders()->place($buyer, new PlaceOrderData($market->id, OrderSide::Buy, OrderType::Market, quoteAmount: '100'));
    }

    public function test_insufficient_balance_throws_and_creates_no_order(): void
    {
        $market = $this->makeMarket();
        $buyer = User::factory()->create();
        $this->fund($buyer, 'USDT', '10');

        try {
            $this->orders()->place($buyer, new PlaceOrderData($market->id, OrderSide::Buy, OrderType::Limit, price: '0.03', quantity: '1000'));
            $this->fail('InsufficientBalanceException bekleniyordu.');
        } catch (InsufficientBalanceException) {
            // Emir oluşturulmamalı (rollback)
            $this->assertSame(0, Order::count());
            $this->assertBalance('10', $this->wallet($buyer, 'USDT')->available);
        }
    }

    public function test_cancel_refunds_locked_funds(): void
    {
        $market = $this->makeMarket();
        $seller = User::factory()->create();
        $this->fund($seller, 'BK$', '1000');

        $order = $this->orders()->place($seller, new PlaceOrderData($market->id, OrderSide::Sell, OrderType::Limit, price: '0.03', quantity: '1000'));
        $this->assertBalance('1000', $this->wallet($seller, 'BK$')->locked);

        $cancelled = $this->orders()->cancel($seller, $order->id);

        $this->assertSame(OrderStatus::Cancelled, $cancelled->status);
        $this->assertBalance('1000', $this->wallet($seller, 'BK$')->available);
        $this->assertBalance('0', $this->wallet($seller, 'BK$')->locked);
    }
}
