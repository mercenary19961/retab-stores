<?php

namespace App\Http\Middleware;

use App\Models\Category;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        return array_merge(parent::share($request), [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $request->user(),
                // Editors: their resolved section→action grants (drives admin nav
                // visibility + client-side gating). Null for admins = full access.
                'permissions' => $request->user()?->isEditor() ? $request->user()->resolvedPermissions() : null,
            ],
            'locale' => session('locale', 'ar'),
            // Global toggle for the admin "How it works" attention beam (staff only;
            // per-session dismissal is client-side). Default on when unset.
            'helpPulse' => fn () => $request->user()?->isStaff()
                ? \App\Models\Setting::get('admin_help_pulse', '1') !== '0'
                : null,
            // Null while unset → the Turnstile widget renders nothing (dev).
            'turnstileSiteKey' => config('services.turnstile.site_key'),
            // Storefront nav tree (parents + active children) for the navbar.
            // Closure → only resolved for Inertia responses, not every request.
            'navCategories' => fn () => Category::query()
                ->whereNull('parent_id')
                ->where('is_active', true)
                ->with(['children' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
                ->orderBy('sort_order')
                ->get()
                ->map(fn (Category $c) => [
                    'id' => $c->id,
                    'name_ar' => $c->name_ar,
                    'name_en' => $c->name_en,
                    'slug' => $c->slug,
                    'children' => $c->children->map(fn (Category $child) => [
                        'id' => $child->id,
                        'name_ar' => $child->name_ar,
                        'name_en' => $child->name_en,
                        'slug' => $child->slug,
                    ])->values(),
                ])->values(),
            'cart' => [
                'count' => app(\App\Services\CartService::class)->count(),
            ],
            // Footer/contact block, admin-editable via settings (falls back to
            // FOOTER_DEFAULTS when a key is unset). Closure → resolved only for
            // Inertia page responses; one batched query.
            'footer' => fn () => $this->footerSettings(),
            // Per-user saved table column widths (resizable admin tables).
            'tablePrefs' => fn () => $request->user()?->isStaff()
                ? (object) ($request->user()->ui_preferences['tableWidths'] ?? [])
                : null,
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                // Structured revert-conflict (fields + the blocking change to
                // undo first) → rendered as a banner with a "take me to it" link.
                'revertConflict' => $request->session()->get('revertConflict'),
            ],
            // Flashed "undo last save" pointer → the immediate toast after a
            // tracked admin save (staff only; the persistent per-section button
            // is passed as a page prop instead).
            'undo' => fn () => $request->user()?->isStaff() ? $request->session()->get('undo') : null,
        ]);
    }

    /**
     * Footer/contact values for the storefront: stored setting where present,
     * otherwise the FOOTER_DEFAULTS fallback. One batched query over the keys.
     *
     * @return array<string, string>
     */
    private function footerSettings(): array
    {
        $defaults = \App\Http\Controllers\Admin\SettingController::FOOTER_DEFAULTS;
        $stored = \App\Models\Setting::query()
            ->whereIn('key', array_keys($defaults))
            ->pluck('value', 'key');

        return collect($defaults)
            ->map(fn (string $default, string $key) => filled($stored->get($key)) ? $stored->get($key) : $default)
            ->all();
    }
}
