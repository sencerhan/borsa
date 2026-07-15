<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateMarketRequest;
use App\Models\Market;
use App\Services\Admin\MarketSettingService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class MarketController extends Controller
{
    public function __construct(private readonly MarketSettingService $settings) {}

    /**
     * Market ayarları (komisyon) yönetim sayfası.
     */
    public function edit(): Response
    {
        return Inertia::render('admin/Market', [
            'markets' => Market::orderBy('symbol')->get(),
        ]);
    }

    /**
     * Market komisyon/aktiflik ayarlarını günceller.
     */
    public function update(UpdateMarketRequest $request, Market $market): RedirectResponse
    {
        $this->settings->update($market, [
            'maker_fee' => $request->string('maker_fee')->value(),
            'taker_fee' => $request->string('taker_fee')->value(),
            'min_order_size' => $request->string('min_order_size')->value(),
            'is_active' => $request->boolean('is_active'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('exchange.flash.market_updated')]);

        return back();
    }
}
