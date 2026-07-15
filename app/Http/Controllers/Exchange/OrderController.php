<?php

namespace App\Http\Controllers\Exchange;

use App\Enums\OrderSide;
use App\Enums\OrderType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Exchange\StoreOrderRequest;
use App\Services\Data\PlaceOrderData;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    /**
     * Yeni emir verir.
     */
    public function store(StoreOrderRequest $request): RedirectResponse
    {
        $data = new PlaceOrderData(
            marketId: (int) $request->input('market_id'),
            side: OrderSide::from($request->string('side')->value()),
            type: OrderType::from($request->string('type')->value()),
            price: $request->filled('price') ? $request->string('price')->value() : null,
            quantity: $request->filled('quantity') ? $request->string('quantity')->value() : null,
            quoteAmount: $request->filled('quote_amount') ? $request->string('quote_amount')->value() : null,
        );

        $this->orders->place($request->user(), $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('exchange.flash.order_placed')]);

        return back();
    }

    /**
     * Açık emri iptal eder.
     */
    public function destroy(Request $request, int $order): RedirectResponse
    {
        $this->orders->cancel($request->user(), $order);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('exchange.flash.order_cancelled')]);

        return back();
    }
}
