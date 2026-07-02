<?php

namespace App\Ssr;

use Exception;
use Illuminate\Http\Client\StrayRequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Vite;
use Inertia\Ssr\HttpGateway;
use Inertia\Ssr\Response;
use Inertia\Ssr\SsrException;

/**
 * SSR gateway that adds connect/response timeouts to the call to the Inertia
 * SSR sidecar (ported from Sky Amman).
 *
 * Inertia's stock HttpGateway already falls back to client-side rendering when
 * the SSR call throws, but it sets no timeout — so a *hung* (not crashed) SSR
 * process blocks the request until the HTTP client's default (~30s), well past
 * Railway's ~15s proxy timeout, which 502s the request before the fallback can
 * fire. With short timeouts here, a slow/unreachable sidecar throws quickly,
 * we fall back to client-side rendering, and the site stays up (momentarily
 * degraded SEO) instead of 502ing site-wide.
 *
 * Mirrors the parent's (inertia-laravel v3) dispatch() logic verbatim and only
 * adds the timeouts to the outgoing HTTP calls.
 */
class TimeoutHttpGateway extends HttpGateway
{
    /**
     * Dispatch the Inertia page to the SSR engine via HTTP, with timeouts.
     *
     * @param  array<string, mixed>  $page
     */
    public function dispatch(array $page, ?Request $request = null): ?Response
    {
        if (! $this->ssrIsEnabled($request ?? request())) {
            return null;
        }

        $isHot = Vite::isRunningHot();

        if (! $isHot && $this->shouldEnsureBundleExists() && ! $this->bundleExists()) {
            return null;
        }

        $url = $isHot
            ? $this->getHotUrl('/__inertia_ssr')
            : $this->getProductionUrl('/render');

        try {
            $response = Http::connectTimeout($this->connectTimeout())
                ->timeout($this->timeout())
                ->post($url, $page);

            if ($response->failed()) {
                $this->handleSsrFailure($page, $response->json());

                return null;
            }

            if (! $data = $response->json()) {
                return null;
            }

            return new Response(
                implode("\n", $data['head'] ?? []),
                $data['body'] ?? ''
            );
        } catch (Exception $e) {
            if ($e instanceof StrayRequestException || $e instanceof SsrException) {
                throw $e;
            }

            // SSR unreachable/slow/errored — fall back to client-side rendering.
            $this->handleSsrFailure($page, [
                'error' => $e->getMessage(),
                'type' => 'connection',
            ]);

            return null;
        }
    }

    /**
     * Determine if the SSR server is healthy, with timeouts applied.
     */
    public function isHealthy(): bool
    {
        try {
            return Http::connectTimeout($this->connectTimeout())
                ->timeout($this->timeout())
                ->get($this->getProductionUrl('/health'))
                ->successful();
        } catch (Exception $e) {
            if ($e instanceof StrayRequestException) {
                throw $e;
            }

            return false;
        }
    }

    /**
     * Max seconds to wait for the response from the SSR sidecar.
     */
    protected function timeout(): float
    {
        return (float) config('inertia.ssr.timeout', 3);
    }

    /**
     * Max seconds to wait while establishing the TCP connection to the sidecar.
     */
    protected function connectTimeout(): float
    {
        return (float) config('inertia.ssr.connect_timeout', 2);
    }
}
