import { needsConsent } from '@/lib/consent';
import { useLocalized } from '@/lib/localize';
import { usePage } from '@inertiajs/react';
import { ArrowRight, BadgeCheck, Info, TriangleAlert, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { useTranslation } from 'react-i18next';

interface Announcement {
    id: number;
    message_ar: string;
    message_en: string | null;
    link_url: string | null;
    link_label_ar: string | null;
    link_label_en: string | null;
    tone: string;
}

/**
 * 🔑 Keyed by ID, not a single "seen the banner" flag. A shopper who dismissed
 * last month's promotion must still be shown next month's shipping notice —
 * one shared flag would silently swallow every future announcement for anyone
 * who ever pressed the ✕.
 */
const STORAGE_KEY = 'retab_dismissed_announcements';

/** How long the page gets to settle before this slides in. */
const APPEAR_AFTER_MS = 900;

/**
 * Tone is the severity signal, and it stays inside the brand palette: teal is
 * the house colour, gold is the house accent, and only "good news" reaches
 * outside for a muted green. Colour lives on a hairline rail and the icon chip,
 * never as a full fill — a saturated card is the shouting bar this replaced.
 */
const TONES: Record<string, { rail: string; chip: string; icon: typeof Info }> = {
    info: { rail: 'border-s-brand-teal', chip: 'bg-brand-teal/10 text-brand-teal', icon: Info },
    warning: { rail: 'border-s-brand-gold', chip: 'bg-brand-gold/15 text-brand-gold', icon: TriangleAlert },
    success: { rail: 'border-s-emerald-700', chip: 'bg-emerald-700/10 text-emerald-700', icon: BadgeCheck },
};

function readDismissed(): number[] {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);

        return raw ? (JSON.parse(raw) as number[]) : [];
    } catch {
        // Private windows, blocked storage, or a corrupted value. Showing the
        // announcement again is a far better failure than throwing on every page.
        return [];
    }
}

/**
 * The storefront announcement.
 *
 * 🔑 A FLOATING CARD, not a bar across the top, and the reason is measurable
 * rather than aesthetic: a full-width strip pinned above the navbar pushes every
 * page down and eats around a fifth of a phone viewport before the shopper has
 * seen a single product. This overlays instead, so it costs no layout at all.
 *
 * 🔑 ONE AT A TIME. Two notices competing for the same glance is how both get
 * ignored; dismissing the top one reveals the next, which falls out of taking
 * the first undismissed item rather than needing its own queue.
 *
 * ⚠️ It ANNOUNCES; it does not ENFORCE. "Stuffed dates ship to Riyadh only" tells
 * a shopper in Jeddah something, it does not stop them ordering — the admin
 * rejects that order at the confirm step. Client's decision, 2026-09-21.
 *
 * ⚠️ Icons are lucide, which is the project's single existing icon family. One
 * family per project beats a "better" library imported alongside it.
 */
export default function AnnouncementCard() {
    const { t } = useTranslation();
    const localized = useLocalized();
    const announcements = (usePage().props.announcements ?? []) as Announcement[];

    /*
     * 🔴 PORTALLED TO <body>, and CLIENT-ONLY, which solves two problems at once.
     *
     * The layout wrapper carries `overflow-x-clip`, and a fixed overlay nested
     * inside a clipped or transformed ancestor is exactly how the mobile drawer
     * and the search overlay both broke before — this project's settled answer is
     * to portal. `mounted` then means the server never renders it, so reading
     * localStorage can never cause a hydration mismatch either.
     *
     * Costs nothing visually: an overlay moves no layout, so appearing after
     * mount produces zero CLS, unlike the bar this replaced.
     */
    const [mounted, setMounted] = useState(false);
    const [dismissed, setDismissed] = useState<number[]>([]);
    const [shown, setShown] = useState(false);
    const [consentPending, setConsentPending] = useState(true);

    useEffect(() => {
        setDismissed(readDismissed());
        setMounted(true);
    }, []);

    /*
     * 🔴 Hold back while the cookie banner is up. That banner is `inset-x-0
     * bottom-0` at z-60, so it covers this corner AND outranks it — the card
     * would be buried, not merely crowded, and a first-time visitor would never
     * see the notice at all. It is also a legal gate that has to be actioned, so
     * it genuinely outranks a shipping notice. Reuses the consent module's own
     * predicate rather than re-reading the cookie, so the two cannot disagree.
     */
    useEffect(() => {
        if (typeof document === 'undefined') return;

        const check = () => setConsentPending(needsConsent(document.cookie));

        check();
        // The banner writes the cookie on dismissal without a navigation, so poll
        // briefly rather than leaving the card hidden until the next page load.
        const timer = setInterval(check, 1000);

        return () => clearInterval(timer);
    }, []);

    /*
     * Motion, and it is motivated: the card arrives AFTER the page has settled so
     * it never competes with the hero for the first glance. That is the whole
     * justification; there is nothing perpetual here, no loop, no attention-grab.
     */
    useEffect(() => {
        const timer = setTimeout(() => setShown(true), APPEAR_AFTER_MS);

        return () => clearTimeout(timer);
    }, []);

    const hide = (id: number) => {
        const next = [...dismissed, id];
        setDismissed(next);
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(next));
        } catch {
            // Dismissal simply does not persist. The ✕ still worked for this page.
        }
    };

    // Only the first undismissed one. Dismissing it promotes the next.
    const current = announcements.filter((a) => !dismissed.includes(a.id))[0];

    if (!mounted || consentPending || !current) return null;

    const tone = TONES[current.tone] ?? TONES.info;
    const Icon = tone.icon;
    const message = localized(current, 'message');
    const label = localized(current, 'link_label') || t('announcement.learnMore');

    return createPortal(
        <div
            data-testid="announcement"
            data-tone={current.tone}
            /*
             * 🔴 PHYSICAL right, NOT logical `end`, and this is the one place in
             * the storefront where that is correct. `scroll-to-top` is pinned
             * `fixed bottom-2 left-2` with a PHYSICAL left that never mirrors, so
             * any logical placement here collides in exactly one locale: `start`
             * overlaps it in English, `end` overlaps it in Arabic. Pinning right
             * clears it in both.
             *
             * It reads well either way: bottom-right is the reading edge in
             * Arabic, and the conventional toast corner in English.
             *
             * ⚠️ On phones the card spans the width, so it cannot dodge sideways
             * and is raised above the button's 44px corner zone instead.
             *
             * z-40 sits above page content, below the search overlay (z-50) and
             * the cookie banner (z-[60]) — see the storefront z-scale.
             */
            className={`fixed right-4 bottom-16 left-4 z-40 sm:right-6 sm:bottom-6 sm:left-auto sm:max-w-sm ${
                shown ? 'translate-y-0 opacity-100' : 'pointer-events-none translate-y-3 opacity-0'
            } transition-all duration-500 ease-out motion-reduce:translate-y-0 motion-reduce:opacity-100 motion-reduce:transition-none`}
        >
            {/*
             * rounded-2xl matches the storefront's card radius (pills stay for
             * interactive things), and the shadow is the teal-tinted recipe
             * already used elsewhere in this codebase rather than a black drop.
             */}
            <div
                className={`ring-brand-teal/10 flex items-start gap-3 rounded-2xl border-s-[3px] bg-white p-4 shadow-[0_8px_40px_rgba(27,78,83,0.18)] ring-1 ${tone.rail}`}
            >
                <span className={`flex size-9 shrink-0 items-center justify-center rounded-xl ${tone.chip}`}>
                    <Icon className="size-[18px]" aria-hidden />
                </span>

                <div className="min-w-0 flex-1">
                    <p className="text-brand-teal text-sm leading-relaxed" dir="auto">
                        {message}
                    </p>
                    {current.link_url && (
                        <a
                            href={current.link_url}
                            // ⚠️ An announcement can legitimately point off-site (a
                            // courier's coverage page, a policy). rel is not optional
                            // on a target=_blank link.
                            target="_blank"
                            rel="noopener noreferrer"
                            className="text-brand-gold mt-2 inline-flex items-center gap-1 text-sm font-semibold transition-opacity hover:opacity-75"
                        >
                            {label}
                            {/* Mirrors with the reading direction rather than always
                                pointing right. */}
                            <ArrowRight className="size-4 rtl:-scale-x-100" aria-hidden />
                        </a>
                    )}
                </div>

                <button
                    type="button"
                    onClick={() => hide(current.id)}
                    data-testid="announcement-dismiss"
                    aria-label={t('announcement.dismiss')}
                    /* -m-1 p-1 keeps the hit area at 28px while the 16px icon stays
                       visually light; under the 24px WCAG 2.5.8 floor without it. */
                    className="text-brand-teal/40 hover:text-brand-teal -m-1 shrink-0 rounded-lg p-1 transition-colors"
                >
                    <X className="size-4" />
                </button>
            </div>
        </div>,
        document.body,
    );
}
