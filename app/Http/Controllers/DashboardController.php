<?php

namespace App\Http\Controllers;

use App\Models\Market;
use App\Services\MarketDataService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private readonly MarketDataService $marketData) {}

    /**
     * Kullanıcı paneli: portföy özeti, bakiyeler, son emirler, piyasa anlık görünümü.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $market = Market::where('is_active', true)->first();

        return Inertia::render('Dashboard', [
            'market' => $market,
            'ticker' => $market ? $this->marketData->ticker($market) : null,
            'wallets' => $this->marketData->userWallets($user),
            'portfolioValue' => $market ? $this->marketData->portfolioValue($user, $market) : null,
            'openOrdersCount' => $user->orders()->active()->count(),
            'recentOrders' => $user->orders()->latest('id')->limit(6)->get(),
        ]);
    }
}
