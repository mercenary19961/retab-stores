import { Link } from '@inertiajs/react';
import { Cookie, ShieldCheck } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { applyConsent, choiceForAction, needsConsent, type ConsentAction, type ConsentChoice } from '@/lib/consent';

/** Fire this event (e.g. from a footer "Cookie settings" link) to re-open the banner. */
export const OPEN_CONSENT_EVENT = 'retab:open-consent';

/**
 * Self-hosted cookie-consent banner (brand-themed). No third-party CMP: one
 * mechanism (Google Consent Mode v2) gates every tag inside GTM (GA4, Google Ads,
 * Meta…). The DENIED defaults + repeat-visit re-grant live in app.blade.php before
 * GTM loads; this component only sends the 'update' when the visitor chooses and
 * remembers it in a versioned cookie.
 */
export default function CookieConsent() {
    const { t } = useTranslation();
    const [visible, setVisible] = useState(false);
    const [entered, setEntered] = useState(false); // drives the slide-up
    const [panelOpen, setPanelOpen] = useState(false);
    const [custom, setCustom] = useState<ConsentChoice>({ analytics: false, marketing: false });

    const open = useCallback(() => {
        setVisible(true);
        // Next frame so the transition runs from the off-screen state.
        requestAnimationFrame(() => setEntered(true));
    }, []);

    // Decide AFTER mount (the server has no document; SSR markup must match).
    useEffect(() => {
        if (typeof document !== 'undefined' && needsConsent(document.cookie)) {
            open();
        }
    }, [open]);

    // Let a "Cookie settings" control anywhere re-open the banner.
    useEffect(() => {
        const handler = () => {
            setPanelOpen(false);
            open();
        };
        window.addEventListener(OPEN_CONSENT_EVENT, handler);

        return () => window.removeEventListener(OPEN_CONSENT_EVENT, handler);
    }, [open]);

    const submit = (action: ConsentAction) => {
        applyConsent(choiceForAction(action, custom));
        setEntered(false);
        window.setTimeout(() => setVisible(false), 450); // let the slide-down finish
    };

    if (!visible) return null;

    return (
        <div
            role="dialog"
            aria-live="polite"
            aria-label={t('consent.title')}
            className={`fixed inset-x-0 bottom-0 z-[60] px-4 pb-4 transition-transform duration-500 ease-out sm:pb-6 ${
                entered ? 'translate-y-0' : 'translate-y-[130%]'
            }`}
        >
            <div className="mx-auto max-w-4xl rounded-2xl border border-brand-gold/20 bg-white p-5 shadow-[0_8px_40px_rgba(27,78,83,0.18)] sm:p-6">
                <div className="flex items-start gap-3">
                    <span className="mt-0.5 shrink-0 rounded-full bg-brand-cream p-2 text-brand-teal">
                        <Cookie className="size-5" />
                    </span>
                    <div className="min-w-0">
                        <h2 className="font-heading text-lg font-bold text-brand-teal">{t('consent.title')}</h2>
                        <p className="mt-1 text-sm leading-relaxed text-brand-teal/70">{t('consent.body')}</p>
                        <Link
                            href="/pages/privacy-policy"
                            className="mt-1 inline-block text-sm font-medium text-brand-gold underline underline-offset-2 hover:text-brand-teal"
                        >
                            {t('consent.privacyLink')}
                        </Link>
                    </div>
                </div>

                {panelOpen && (
                    <div className="mt-4 space-y-2 border-t border-brand-gold/15 pt-4">
                        <CategoryRow
                            icon={<ShieldCheck className="size-4" />}
                            label={t('consent.categories.necessary.label')}
                            description={t('consent.categories.necessary.description')}
                            lockedLabel={t('consent.categories.necessary.always')}
                        />
                        <CategoryRow
                            label={t('consent.categories.analytics.label')}
                            description={t('consent.categories.analytics.description')}
                            checked={custom.analytics}
                            onChange={(v) => setCustom((c) => ({ ...c, analytics: v }))}
                        />
                        <CategoryRow
                            label={t('consent.categories.marketing.label')}
                            description={t('consent.categories.marketing.description')}
                            checked={custom.marketing}
                            onChange={(v) => setCustom((c) => ({ ...c, marketing: v }))}
                        />
                    </div>
                )}

                <div className="mt-5 flex flex-col gap-2 sm:flex-row sm:justify-end">
                    <button
                        type="button"
                        onClick={() => setPanelOpen((o) => !o)}
                        className="rounded-full border border-brand-gold/30 px-5 py-2.5 text-sm font-medium text-brand-teal transition-colors hover:bg-brand-gold/10"
                    >
                        {panelOpen ? t('consent.back') : t('consent.customise')}
                    </button>

                    {/* Reject is the visual equal of Accept on purpose (a harder refusal
                        than acceptance is the dark pattern regulators cite). */}
                    <button
                        type="button"
                        onClick={() => submit('reject_all')}
                        className="rounded-full border border-brand-gold/30 px-5 py-2.5 text-sm font-medium text-brand-teal transition-colors hover:bg-brand-gold/10"
                    >
                        {t('consent.rejectAll')}
                    </button>

                    <button
                        type="button"
                        onClick={() => submit(panelOpen ? 'custom' : 'accept_all')}
                        className="rounded-full bg-brand-teal px-5 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-brand-teal/90"
                    >
                        {panelOpen ? t('consent.save') : t('consent.acceptAll')}
                    </button>
                </div>
            </div>
        </div>
    );
}

interface CategoryRowProps {
    label: string;
    description: string;
    icon?: React.ReactNode;
    checked?: boolean;
    onChange?: (value: boolean) => void;
    lockedLabel?: string;
}

function CategoryRow({ label, description, icon, checked, onChange, lockedLabel }: CategoryRowProps) {
    const locked = onChange === undefined;

    return (
        <div className="flex items-start justify-between gap-4 rounded-xl bg-brand-cream/50 p-3">
            <div className="min-w-0">
                <span className="flex items-center gap-1.5 text-sm font-semibold text-brand-teal">
                    {icon}
                    {label}
                </span>
                <p className="mt-0.5 text-xs leading-relaxed text-brand-teal/60">{description}</p>
            </div>

            {locked ? (
                <span className="shrink-0 whitespace-nowrap rounded-full bg-brand-cream px-2.5 py-1 text-[11px] font-medium text-brand-teal">
                    {lockedLabel}
                </span>
            ) : (
                <button
                    type="button"
                    role="switch"
                    aria-checked={checked}
                    aria-label={label}
                    onClick={() => onChange(!checked)}
                    className={`relative h-6 w-11 shrink-0 rounded-full transition-colors ${checked ? 'bg-brand-teal' : 'bg-brand-teal/20'}`}
                >
                    {/* Knob travels on the inline axis so it reads correctly in RTL. */}
                    <span
                        className={`absolute top-0.5 size-5 rounded-full bg-white shadow transition-all ${checked ? 'start-[22px]' : 'start-0.5'}`}
                    />
                </button>
            )}
        </div>
    );
}
