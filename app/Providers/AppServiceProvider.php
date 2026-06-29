<?php

namespace App\Providers;

use App\Services\Payments\MoyasarGateway;
use App\Services\Payments\PaymentGateway;
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

        // Moyasar (cards: mada/Visa/MC/Apple Pay/STC Pay) — captured at checkout.
        // Swap this binding for a future provider without touching callers.
        $this->app->singleton(PaymentGateway::class, fn () => new MoyasarGateway(
            secretKey: (string) config('services.moyasar.secret_key'),
            baseUrl: rtrim((string) config('services.moyasar.base_url'), '/'),
            currency: (string) config('services.moyasar.currency', 'SAR'),
            webhookToken: (string) config('services.moyasar.webhook_secret'),
            successUrl: rtrim((string) config('app.url'), '/') . '/checkout/success',
            callbackUrl: rtrim((string) config('app.url'), '/') . '/webhooks/moyasar',
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
