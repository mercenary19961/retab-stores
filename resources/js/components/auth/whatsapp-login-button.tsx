import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

/**
 * "Continue with WhatsApp", button-shaped, for checkout.
 *
 * 🔑 Same gate as WhatsAppLoginLink — `whatsappAuth` is false whenever the
 * transport cannot actually deliver a code, and today that is the case, because
 * we are still on Meta's test number and it can only message five allowlisted
 * phones. So this renders nothing in production right now, on purpose: a door
 * that cannot open is worse than no door.
 *
 * A separate component from the link rather than a variant of it, because the
 * two live in different places: the auth pages want a quiet text link under a
 * form, checkout wants a button beside the Google one.
 */
export default function WhatsAppLoginButton({ className = '' }: { className?: string }) {
    const { t } = useTranslation();
    const { whatsappAuth } = usePage<SharedData>().props;

    if (!whatsappAuth) return null;

    return (
        <a
            href="/login/whatsapp"
            data-testid="whatsapp-login"
            className={`flex w-full items-center justify-center gap-2.5 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 ${className}`}
        >
            {/* WhatsApp's mark, inline so it costs no request and cannot 404. */}
            <svg className="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="#25D366" aria-hidden="true">
                <path d="M17.47 14.38c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.17-.17.2-.35.22-.65.07-.3-.15-1.26-.46-2.4-1.48-.89-.79-1.49-1.77-1.66-2.07-.17-.3-.02-.46.13-.61.14-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.08-.15-.67-1.61-.92-2.21-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.8.37-.27.3-1.04 1.02-1.04 2.48s1.07 2.88 1.22 3.08c.15.2 2.1 3.2 5.08 4.49.71.3 1.26.49 1.69.63.71.22 1.36.19 1.87.12.57-.09 1.76-.72 2.01-1.41.25-.7.25-1.29.17-1.42-.07-.13-.27-.2-.57-.35z" />
                <path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2 22l5.25-1.38a9.9 9.9 0 0 0 4.79 1.22h.01c5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2zm0 18.15h-.01a8.2 8.2 0 0 1-4.19-1.15l-.3-.18-3.12.82.83-3.04-.2-.31a8.17 8.17 0 0 1-1.26-4.38c0-4.54 3.7-8.24 8.25-8.24 2.2 0 4.27.86 5.83 2.42a8.19 8.19 0 0 1 2.41 5.83c0 4.54-3.7 8.23-8.24 8.23z" />
            </svg>
            {t('login.withWhatsapp')}
        </a>
    );
}
