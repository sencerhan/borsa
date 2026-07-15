<?php

namespace App\Http\Controllers;

use App\Models\Market;
use App\Services\MarketDataService;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __construct(private readonly MarketDataService $marketData) {}

    /**
     * Anasayfa (landing): canlı piyasa özeti ile.
     */
    public function index(): Response
    {
        $market = Market::where('is_active', true)->first();

        return Inertia::render('Welcome', [
            'market' => $market,
            'ticker' => $market ? $this->marketData->ticker($market) : null,
            'orderBook' => $market ? $this->marketData->orderBook($market, 6) : null,
        ]);
    }
}
