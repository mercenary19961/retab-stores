import { useLocalized } from '@/lib/localize';
import { usePage } from '@inertiajs/react';
import { Info, TriangleAlert, X } from 'lucide-react';
import { useEffect, useState } from 'react';
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

const TONES: Record<string, { bar: string; icon: typeof Info }> = {
    info: { bar: 'bg-brand-teal text-white', icon: Info },
    warning: { bar: 'bg-brand-gold text-white', icon: TriangleAlert },
    success: { bar: 'bg-emerald-700 text-white', icon: Info },
};

function readDismissed(): number[] {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);

        return raw ? (JSON.parse(raw) as number[]) : [];
    } catch {
        // Private windows, blocked storage, or a corrupted value. Showing the
        // banner again is a far better failure than throwing on every page.
        return [];
    }
}

/**
 * The announcement strip above the storefront.
 *
 * ⚠️ It ANNOUNCES; it does not enforce. "Stuffed dates ship to Riyadh only" tells
 * a shopper in Jeddah something — it does not stop them ordering, and the admin
 * rejects that order at the confirm step. Client's decision, 2026-09-21.
 */
export default function AnnouncementBar() {
    const { t } = useTranslation();
    const localized = useLocalized();
    const announcements = (usePage().props.announcements ?? []) as Announcement[];

    /*
     * 🔴 SSR-SAFE ON PURPOSE. `dismissed` starts null (= "not known yet") rather
     * than reading localStorage during render: the SSR sidecar has no localStorage,
     * so reading it inline would make the server and the client disagree and
     * produce a hydration mismatch.
     *
     * The consequence is accepted deliberately: someone who dismissed a banner
     * sees it for one frame before it disappears. The alternative — rendering
     * nothing until mounted — shifts the whole page down for EVERY visitor on
     * every load, which is worse and affects everyone rather than a few.
     */
    const [dismissed, setDismissed] = useState<number[] | null>(null);

    useEffect(() => setDismissed(readDismissed()), []);

    const hide = (id: number) => {
        const next = [...(dismissed ?? []), id];
        setDismissed(next);
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(next));
        } catch {
            // Dismissal simply does not persist. The ✕ still worked for this page.
        }
    };

    const visible = announcements.filter((a) => !(dismissed ?? []).includes(a.id));

    if (visible.length === 0) return null;

    return (
        <div>
            {visible.map((a) => {
                const tone = TONES[a.tone] ?? TONES.info;
                const Icon = tone.icon;
                const message = localized(a, 'message');
                const label = localized(a, 'link_label') || t('announcement.learnMore');

                return (
                    <div key={a.id} data-testid="announcement" data-tone={a.tone} className={`${tone.bar} px-4 py-2 text-sm`}>
                        <div className="mx-auto flex max-w-[1600px] items-center gap-3">
                            <Icon className="hidden h-4 w-4 shrink-0 sm:block" aria-hidden />
                            <p className="min-w-0 flex-1 text-center sm:text-start">
                                {message}
                                {a.link_url && (
                                    <a
                                        href={a.link_url}
                                        className="ms-2 font-semibold underline underline-offset-2"
                                        // ⚠️ An announcement can legitimately point off-site
                                        // (a courier's coverage page, a policy). rel is not
                                        // optional on a target=_blank link.
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        {label}
                                    </a>
                                )}
                            </p>
                            <button
                                type="button"
                                onClick={() => hide(a.id)}
                                data-testid="announcement-dismiss"
                                aria-label={t('announcement.dismiss')}
                                /* p-1 -m-1: the icon is 16px, which is under the 24px
                                   WCAG 2.5.8 floor on its own. Same trick as the navbar
                                   search button. */
                                className="-m-1 shrink-0 rounded p-1 opacity-70 transition-opacity hover:opacity-100"
                            >
                                <X className="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                );
            })}
        </div>
    );
}
