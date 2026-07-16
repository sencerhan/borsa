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
     * Market komisyon/aktiflik ve bot ayarlarını günceller.
     */
    public function update(UpdateMarketRequest $request, Market $market): RedirectResponse
    {
        $this->settings->update($market, [
            'maker_fee' => $request->string('maker_fee')->value(),
            'taker_fee' => $request->string('taker_fee')->value(),
            'min_order_size' => $request->string('min_order_size')->value(),
            'is_active' => $request->boolean('is_active'),
            'bot_active' => $request->boolean('bot_active'),
            'bot_reference_price' => $request->string('bot_reference_price')->value(),
            'bot_volatility' => $request->string('bot_volatility')->value(),
            'bot_spread' => $request->string('bot_spread')->value(),
            'bot_min_qty' => $request->string('bot_min_qty')->value(),
            'bot_max_qty' => $request->string('bot_max_qty')->value(),
            'bot_interval' => $request->integer('bot_interval'),
            'simulator_active' => $request->boolean('simulator_active'),
            'simulator_interval' => $request->integer('simulator_interval'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('exchange.flash.market_updated')]);

        return back();
    }

    /**
     * Piyasa Yapıcı Botunu bu market için anlık 1 kez çalıştırır.
     */
    public function triggerBot(Market $market, \App\Services\Admin\MarketBotService $bots): RedirectResponse
    {
        $bots->tick($market);
        
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Piyasa yapıcı bot bu market için çalıştırıldı.']);
        return back();
    }

    /**
     * Test kullanıcısı için sahte bir para yatırma işlemi simüle eder.
     */
    public function triggerMockDeposit(\App\Services\Admin\TransactionSimulatorService $simulator): RedirectResponse
    {
        $user = \App\Models\User::where('email', 'test@example.com')->first();
        if ($user) {
            $asset = random_int(0, 1) === 1 ? 'USDT' : 'BK$';
            $amount = $asset === 'USDT' ? (string) random_int(100, 2000) : (string) random_int(5000, 100000);
            
            app(\App\Services\DepositService::class)->request($user, $asset, $amount);
            $simulator->broadcastStats();
            
            Inertia::flash('toast', ['type' => 'success', 'message' => "{$amount} {$asset} sahte yatırma talebi kuyruğa eklendi."]);
        } else {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Test kullanıcısı bulunamadı.']);
        }
        return back();
    }

    /**
     * Test kullanıcısı için sahte bir para çekme işlemi simüle eder.
     */
    public function triggerMockWithdrawal(\App\Services\Admin\TransactionSimulatorService $simulator): RedirectResponse
    {
        $user = \App\Models\User::where('email', 'test@example.com')->first();
        if ($user) {
            $asset = random_int(0, 1) === 1 ? 'USDT' : 'BK$';
            $wallet = \App\Models\Wallet::where('user_id', $user->id)->where('asset', $asset)->first();
            
            if ($wallet && \App\Support\BigMath::gt($wallet->available, '50')) {
                $amount = (string) random_int(10, 50);
                $address = $asset === 'USDT' ? '0x' . bin2hex(random_bytes(20)) : 'bk1' . bin2hex(random_bytes(19));
                
                app(\App\Services\WithdrawalService::class)->request($user, $asset, $amount, $address);
                $simulator->broadcastStats();
                
                Inertia::flash('toast', ['type' => 'success', 'message' => "{$amount} {$asset} sahte çekim talebi kuyruğa eklendi."]);
            } else {
                Inertia::flash('toast', ['type' => 'warning', 'message' => "Test kullanıcısının çekilebilir {$asset} bakiyesi yetersiz."]);
            }
        } else {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Test kullanıcısı bulunamadı.']);
        }
        return back();
    }

    /**
     * Kuyruktaki bekleyen tüm işleri hemen eritir (Artisan queue:work).
     */
    public function processQueue(\App\Services\Admin\TransactionSimulatorService $simulator): RedirectResponse
    {
        \Illuminate\Support\Facades\Artisan::call('queue:work', [
            '--once' => true,
            '--stop-when-empty' => true
        ]);
        
        $simulator->broadcastStats();
        
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kuyruktaki bekleyen işler başarıyla işlendi.']);
        return back();
    }
}
