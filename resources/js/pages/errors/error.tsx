import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Clock, Home, Hourglass, Lock, SearchX, ServerCrash, type LucideIcon } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

/**
 * The one branded page every unhandled HTTP error resolves to — a 403 an editor
 * reached, a mistyped URL, a rate limit, a 500. Wired up in bootstrap/app.php's
 * withExceptions(): always in production, and in local for CLIENT errors only
 * (server errors keep the framework's stack trace, which is the point in dev).
 * Never in `testing`, where the suite asserts raw status codes.
 *
 * Deliberately self-contained: it renders NO layout, because a layout needs
 * shared props/context an error response may not carry, and an error page that
 * itself errors is the worst outcome. Plain brand styling, works in the
 * storefront and inside the admin shell alike.
 *
 * 🔑 That "may not carry" is literal, not cautious: on the 404 path the router
 * throws before any web middleware runs, so Inertia has shared NOTHING — not even
 * `locale`. Hence the server passes `locale` as an explicit prop, and nothing here
 * may reach for a shared one.
 *
 * Copy is keyed by status, with a sensible generic fallback so a status we did
 * not enumerate still reads as a real page rather than a bare number.
 */

const ICONS: Record<number, LucideIcon> = {
    403: Lock,
    404: SearchX,
    419: Clock,
    429: Hourglass,
    500: ServerCrash,
    503: ServerCrash,
};

export default function ErrorPage({ status, retryAfter }: { status: number; retryAfter?: number }) {
    const { t } = useTranslation();

    // Fall back to a generic entry for any status without its own copy, so the
    // page never shows an i18n key.
    const known = [403, 404, 419, 429, 500, 503].includes(status);
    const key = known ? String(status) : 'generic';
    const Icon = ICONS[status] ?? ServerCrash;

    const title = t(`errors.${key}.title`);
    const message = t(`errors.${key}.message`);

    // Live countdown to when a rate limit clears. Seeded from the server's
    // Retry-After so the very first paint (SSR included) already shows the real
    // number instead of flashing a placeholder, and so the markup the server
    // renders matches what the client hydrates.
    const showTimer = status === 429 && !!retryAfter;
    const [secondsLeft, setSecondsLeft] = useState(retryAfter ?? 0);

    useEffect(() => {
        if (!showTimer) {
            return;
        }

        setSecondsLeft(retryAfter ?? 0);
        const id = window.setInterval(() => setSecondsLeft((s) => (s > 0 ? s - 1 : 0)), 1000);

        return () => window.clearInterval(id);
    }, [showTimer, retryAfter]);

    return (
        <>
            <Head title={title} />
            <main className="bg-brand-cream text-brand-teal flex min-h-screen flex-col items-center justify-center px-6 py-16 text-center">
                <div className="flex max-w-md flex-col items-center">
                    <span className="bg-brand-teal/10 text-brand-teal mb-6 flex h-16 w-16 items-center justify-center rounded-2xl">
                        <Icon className="h-8 w-8" aria-hidden />
                    </span>

                    <p className="font-heading text-brand-gold text-5xl font-black tracking-tight" aria-hidden>
                        {status}
                    </p>
                    <h1 className="font-heading mt-3 text-2xl font-bold">{title}</h1>
                    <p className="text-brand-teal/70 mt-3 text-sm leading-relaxed">{message}</p>

                    {showTimer && (
                        <p className="text-brand-teal/70 mt-4 text-sm">
                            {secondsLeft > 0 ? (
                                <>
                                    {t('errors.429.retryIn')}{' '}
                                    {/* dir=ltr so bidi cannot reorder the clock around its colon.
                                        ⚠️ Deliberately carries NO `ltr:` utilities: that variant resolves
                                        against the element's OWN direction, so any would switch on here
                                        while the rest of the page is still Arabic. */}
                                    <span dir="ltr" className="font-heading text-brand-teal text-base font-bold tabular-nums">
                                        {Math.floor(secondsLeft / 60)}:{String(secondsLeft % 60).padStart(2, '0')}
                                    </span>
                                </>
                            ) : (
                                t('errors.429.retryNow')
                            )}
                        </p>
                    )}

                    <div className="mt-8 flex flex-wrap items-center justify-center gap-3">
                        {/* Back to wherever they were — for an editor who hit a 403,
                            this is the admin page they came from. */}
                        <button
                            type="button"
                            onClick={() => window.history.back()}
                            className="border-brand-teal/20 text-brand-teal hover:bg-brand-teal/5 inline-flex items-center gap-2 rounded-full border px-5 py-2.5 text-sm font-medium transition-colors"
                        >
                            <ArrowLeft className="h-4 w-4 rtl:-scale-x-100" aria-hidden />
                            {t('errors.back')}
                        </button>
                        <Link
                            href="/"
                            className="bg-brand-teal hover:bg-brand-teal/90 inline-flex items-center gap-2 rounded-full px-5 py-2.5 text-sm font-medium text-white transition-colors"
                        >
                            <Home className="h-4 w-4" aria-hidden />
                            {t('errors.home')}
                        </Link>
                    </div>
                </div>
            </main>
        </>
    );
}
