<?php

namespace App\Http\Controllers\Exchange;

use App\Http\Controllers\Controller;
use App\Models\Market;
use App\Services\MarketDataService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExchangeController extends Controller
{
    public function __construct(private readonly MarketDataService $marketData) {}

    /**
     * İşlem (trading) sayfası: market, emir defteri, son işlemler, açık emirler, bakiyeler.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $market = Market::where('is_active', true)->first();

        // Aktif piyasa yoksa "kapalı" durumunu göster (çökme yerine)
        if ($market === null) {
            return Inertia::render('exchange/Index', [
                'market' => null,
                'orderBook' => null,
                'recentTrades' => [],
                'ticker' => null,
                'openOrders' => [],
                'wallets' => $this->marketData->userWallets($user),
            ]);
        }

        // Kapanışlar her istekte (polling dahil) yeniden değerlendirilir
        return Inertia::render('exchange/Index', [
            'market' => $market,
            'orderBook' => fn () => $this->marketData->orderBook($market),
            'recentTrades' => fn () => $this->marketData->recentTrades($market),
            'ticker' => fn () => $this->marketData->ticker($market),
            'openOrders' => fn () => $this->marketData->userOpenOrders($user, $market),
            'wallets' => fn () => $this->marketData->userWallets($user),
        ]);
    }
}
