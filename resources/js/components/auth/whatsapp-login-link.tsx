import TextLink from '@/components/text-link';
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

/**
 * "Sign in with WhatsApp" cross-link for the email auth pages.
 *
 * 🔑 A component rather than six inline lines in each page, because the important
 * part is the GATE, not the markup: WhatsApp must never be offered while the
 * transport cannot deliver a code. Keeping that decision in one place means a third
 * auth page cannot reintroduce a door to a form that silently does not work.
 *
 * Renders nothing when unavailable, so the caller needs no conditional of its own.
 */
export default function WhatsAppLoginLink() {
    const { t } = useTranslation();
    const { whatsappAuth } = usePage<SharedData>().props;

    if (!whatsappAuth) return null;

    return (
        <div className="text-center text-sm text-neutral-500">
            <TextLink href="/login/whatsapp">{t('login.withWhatsapp')}</TextLink>
        </div>
    );
}
