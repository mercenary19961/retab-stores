<?php

namespace App\Providers;

use App\Services\Shipping\Oto\OtoClient;
use App\Services\Shipping\Oto\OtoGateway;
use App\Services\Shipping\ShippingGateway;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // OTO (Tryoto) shipping. Swap this binding for a future provider gateway
        // (e.g. Torod) without touching any callers.
        $this->app->singleton(OtoClient::class, fn () => new OtoClient(
            refreshToken: (string) config('services.oto.refresh_token'),
            baseUrl: rtrim((string) config('services.oto.base_url'), '/'),
        ));

        $this->app->singleton(ShippingGateway::class, fn ($app) => new OtoGateway(
            client: $app->make(OtoClient::class),
            originCity: (string) config('services.oto.origin_city', 'Riyadh'),
            webhookSecret: (string) config('services.oto.webhook_secret'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
