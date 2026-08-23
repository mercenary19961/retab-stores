import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Clock, Home, Lock, SearchX, ServerCrash, type LucideIcon } from 'lucide-react';
import { useTranslation } from 'react-i18next';

/**
 * The one branded page every unhandled HTTP error resolves to in production —
 * a 403 an editor reached, a mistyped URL, a 500. Wired up in bootstrap/app.php's
 * withExceptions(); in local/testing the framework's detailed page is kept
 * instead, so this is only ever seen where a raw Symfony page would otherwise be.
 *
 * Deliberately self-contained: it renders NO layout, because a layout needs
 * shared props/context an error response may not carry, and an error page that
 * itself errors is the worst outcome. Plain brand styling, works in the
 * storefront and inside the admin shell alike.
 *
 * Copy is keyed by status, with a sensible generic fallback so a status we did
 * not enumerate still reads as a real page rather than a bare number.
 */

const ICONS: Record<number, LucideIcon> = {
    403: Lock,
    404: SearchX,
    419: Clock,
    500: ServerCrash,
    503: ServerCrash,
};

export default function ErrorPage({ status }: { status: number }) {
    const { t } = useTranslation();

    // Fall back to a generic entry for any status without its own copy, so the
    // page never shows an i18n key.
    const known = [403, 404, 419, 500, 503].includes(status);
    const key = known ? String(status) : 'generic';
    const Icon = ICONS[status] ?? ServerCrash;

    const title = t(`errors.${key}.title`);
    const message = t(`errors.${key}.message`);

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
