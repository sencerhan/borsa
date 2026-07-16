<?php

namespace App\Providers;

use App\Services\Chain\BkChainClient;
use App\Services\Chain\TronChainClient;
use App\Services\Chain\TronWithdrawalService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\DevCommands;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // BK zincir istemcisi: taban URL config'den gelsin.
        $this->app->bind(BkChainClient::class, fn () => new BkChainClient(
            (string) config('services.bitkurush.base_url'),
        ));

        // Tron zincir istemcisi + USDT çekim servisi (config'den).
        $this->app->bind(TronChainClient::class, fn () => new TronChainClient(
            (string) config('services.trongrid.base_url'),
            config('services.trongrid.api_key'),
        ));

        $this->app->bind(TronWithdrawalService::class, fn ($app) => new TronWithdrawalService(
            $app->make(TronChainClient::class),
            (string) config('services.trongrid.usdt_contract'),
            (int) config('services.trongrid.fee_limit', 20000000),
            (int) config('services.trongrid.usdt_decimals', 6),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->registerDevProcesses();
    }

    /**
     * Otonom borsa yığınını `php artisan dev` (composer dev) altına ekler.
     * Böylece tek komutla websocket (reverb) ve otonom daemon da ayağa kalkar.
     */
    protected function registerDevProcesses(): void
    {
        if (! $this->app->environment('local')) {
            return;
        }

        // Canlı yayın (order book / trade / cüzdan / istatistik) websocket sunucusu
        DevCommands::artisan('reverb:start', 'reverb');

        // Piyasa yapıcı bot + yatırma/çekme simülatörü + kuyruk + istatistik yayını
        DevCommands::artisan('borsa:daemon', 'borsa');
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
