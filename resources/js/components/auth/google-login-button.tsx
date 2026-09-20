import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

/**
 * "Continue with Google".
 *
 * 🔑 Same shape as WhatsAppLoginLink, and for the same reason: the important part
 * is the GATE, not the markup. Google must never be offered while no OAuth client
 * is configured, or the customer lands on a 404 having been promised a sign-in.
 * Keeping that decision here means a third caller cannot reintroduce a dead door.
 *
 * Renders nothing when unavailable, so callers need no conditional of their own.
 *
 * ⚠️ A plain <a>, not an Inertia <Link>: this leaves the SPA for Google's servers,
 * and an Inertia visit would try to parse an OAuth redirect as a page response.
 */
export default function GoogleLoginButton({ className = '' }: { className?: string }) {
    const { t } = useTranslation();
    const { googleAuth } = usePage<SharedData>().props;

    if (!googleAuth) return null;

    return (
        <a
            href="/auth/google"
            data-testid="google-login"
            className={`flex w-full items-center justify-center gap-2.5 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 ${className}`}
        >
            {/* Google's own mark, inline so it costs no request and cannot 404.
                Four-colour original: Google's brand rules forbid recolouring it. */}
            <svg className="h-5 w-5 shrink-0" viewBox="0 0 24 24" aria-hidden="true">
                <path
                    fill="#4285F4"
                    d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z"
                />
                <path
                    fill="#34A853"
                    d="M12 23c2.97 0 5.46-.98 7.28-2.65l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84A11 11 0 0 0 12 23z"
                />
                <path fill="#FBBC05" d="M5.84 14.11a6.6 6.6 0 0 1 0-4.22V7.05H2.18a11 11 0 0 0 0 9.9l3.66-2.84z" />
                <path
                    fill="#EA4335"
                    d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1a11 11 0 0 0-9.82 6.05l3.66 2.84c.87-2.6 3.3-4.51 6.16-4.51z"
                />
            </svg>
            {t('login.withGoogle')}
        </a>
    );
}
