<?php

namespace App\Http\Controllers\Exchange;

use App\Http\Controllers\Controller;
use App\Http\Requests\Exchange\StoreWithdrawalRequest;
use App\Models\Asset;
use App\Services\DepositVerificationService;
use App\Services\MarketDataService;
use App\Services\WalletService;
use App\Services\WithdrawalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class WalletController extends Controller
{
    public function __construct(
        private readonly MarketDataService $marketData,
        private readonly WithdrawalService $withdrawals,
        private readonly WalletService $wallets,
        private readonly DepositVerificationService $deposits,
    ) {}

    /**
     * Cüzdan sayfası: bakiyeler, yatırma adresleri, yatırma/çekme geçmişi.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $assets = Asset::orderBy('symbol')->get();

        // Her varlık için cüzdan (dolayısıyla yatırma adresi) hazır olsun
        foreach ($assets as $asset) {
            $this->wallets->getOrCreate($user->id, $asset->symbol);
        }

        // Sayfa açılışında zincirden gelen yeni yatırmaları doğrula (daemon çalışmasa da
        // kullanıcı cüzdanını her açtığında yatırmaları anında yansır). Zincir erişilemezse
        // sayfa yine de açılır.
        try {
            $this->deposits->scanUser($user->id);
        } catch (\Throwable $e) {
            Log::warning('Cüzdan açılışında yatırma taraması başarısız: '.$e->getMessage());
        }

        return Inertia::render('wallet/Index', [
            'assets' => $assets,
            'wallets' => fn () => $this->marketData->userWallets($user),
            'deposits' => fn () => $user->deposits()->latest('id')->limit(20)->get(),
            'withdrawals' => fn () => $user->withdrawals()->latest('id')->limit(20)->get(),
        ]);
    }

    /**
     * Para çekme talebi (bakiye anında rezerve edilir, simüle otomatik işlenir).
     */
    public function withdraw(StoreWithdrawalRequest $request): RedirectResponse
    {
        $this->withdrawals->request(
            $request->user(),
            $request->string('asset')->value(),
            $request->string('amount')->value(),
            $request->filled('address') ? $request->string('address')->value() : null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('exchange.flash.withdrawal_requested')]);

        return back();
    }
}
