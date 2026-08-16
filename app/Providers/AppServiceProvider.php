<?php

namespace App\Providers;

use App\Services\Payments\MoyasarGateway;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\Tamara\TamaraClient;
use App\Services\Shipping\Oto\OtoClient;
use App\Services\Shipping\Oto\OtoGateway;
use App\Services\Shipping\ShippingGateway;
use App\Services\WhatsApp\CloudApiGateway;
use App\Services\WhatsApp\LogGateway;
use App\Services\WhatsApp\WhatsAppGateway;
use App\Ssr\TimeoutHttpGateway;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Inertia\Ssr\Gateway;

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
            // ⚠️ Was '/checkout/success', which was never routed — a paying
            // customer landed on a 404. Both gateways now return to the one
            // '/checkout/result' handler. Safe to change outright because
            // Moyasar has never been live, so no outstanding invoice carries
            // the old URL.
            successUrl: rtrim((string) config('app.url'), '/').'/checkout/result',
            callbackUrl: rtrim((string) config('app.url'), '/').'/webhooks/moyasar',
        ));

        // Tamara BNPL — authorize at checkout, capture at admin confirmation.
        $this->app->singleton(TamaraClient::class, fn () => new TamaraClient(
            apiToken: (string) config('services.tamara.api_token'),
            notificationToken: (string) config('services.tamara.notification_token'),
            baseUrl: rtrim((string) config('services.tamara.base_url'), '/'),
        ));

        // WhatsApp Cloud API. Defaults to the log driver (no creds needed in dev);
        // set WHATSAPP_DRIVER=cloud in production to send live.
        $this->app->singleton(WhatsAppGateway::class, function () {
            if (config('services.whatsapp.driver') === 'cloud') {
                return new CloudApiGateway(
                    token: (string) config('services.whatsapp.token'),
                    phoneNumberId: (string) config('services.whatsapp.phone_number_id'),
                    baseUrl: rtrim((string) config('services.whatsapp.base_url'), '/'),
                );
            }

            return new LogGateway;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Behind Railway's proxy every generated URL must be https; pairs with
        // the trustProxies configuration in bootstrap/app.php.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');

            // Pin every generated URL to the canonical host, not the host the
            // request happened to arrive on.
            //
            // Laravel builds url()/route() from the REQUEST, so the app stays
            // reachable at retab-website-production.up.railway.app and would
            // serve <link rel="canonical">, og:url, robots.txt's Sitemap: line
            // and a sitemap of all 87 products all pointing at ITSELF — a
            // complete self-canonicalising duplicate of the store. That is bad
            // at any time and actively harmful during an SEO migration off Zid,
            // where the whole point is consolidating ranking onto one host.
            // forceScheme alone does not help: it fixes the scheme, not the host.
            //
            // ⚠️ A staging environment must NOT run with APP_ENV=production, or
            // every link it generates will point at the live site.
            if ($root = config('app.url')) {
                URL::forceRootUrl($root);
            }
        }

        // Override Inertia's SSR gateway with one that applies connect/response
        // timeouts, so a hung/unreachable SSR sidecar falls back to client-side
        // rendering instead of 502ing the site. Bound in boot() (not register())
        // so it wins over Inertia's own register()-time binding regardless of
        // provider order (Sky Amman pattern).
        $this->app->bind(Gateway::class, TimeoutHttpGateway::class);
    }
}
