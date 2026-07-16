<?php

namespace App\Providers;

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
        $this->app->bind(\App\Services\Chain\BkChainClient::class, fn () => new \App\Services\Chain\BkChainClient(
            (string) config('services.bitkurush.base_url'),
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
