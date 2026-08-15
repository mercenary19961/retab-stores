import HardRockMark from '@/components/hardrock-mark';
import { OPEN_CONSENT_EVENT } from '@/components/store/cookie-consent';
import { Link, usePage } from '@inertiajs/react';
import { ArrowUpRight, Cookie } from 'lucide-react';
import { useTranslation } from 'react-i18next';

/**
 * Store footer (from the Figma design). The background artwork is effectively a
 * flat cream, so it's a CSS colour rather than an image. Everything else is real
 * markup: text (translatable, Thmanyah Sans), the brand logo, the two official badges
 * (commercial registration + VAT) and the contact/social icon set.
 *
 * Business details (contact, CR, VAT, social URLs) are admin-editable: they come
 * from the shared `footer` prop (HandleInertiaRequests), which falls back to
 * SettingController::FOOTER_DEFAULTS when a key is unset.
 */
interface FooterSettings {
    contact_phone: string;
    contact_email: string;
    commercial_registration: string;
    vat_number: string;
    social_snapchat: string;
    social_facebook: string;
    social_instagram: string;
    social_x: string;
    social_linkedin: string;
}

const HANDLE = '@RETAB_DATES';

const QUICK_LINKS = [
    { key: 'returnPolicy', href: '/pages/returns-policy' },
    { key: 'contact', href: '/pages/contact' },
    { key: 'branches', href: '/pages/branches' },
    { key: 'dates', href: '/shop' },
] as const;

export default function StoreFooter() {
    const { t } = useTranslation();
    const footer = (usePage().props as unknown as { footer: FooterSettings }).footer;

    const socials = [
        { key: 'snapchat', icon: 'social-snapchat', url: footer.social_snapchat },
        { key: 'facebook', icon: 'social-facebook', url: footer.social_facebook },
        { key: 'instagram', icon: 'social-instagram', url: footer.social_instagram },
        { key: 'x', icon: 'social-x', url: footer.social_x },
        { key: 'linkedin', icon: 'social-linkedin', url: footer.social_linkedin },
    ].filter((s) => s.url);

    return (
        <footer className="bg-gradient-to-b from-[#f6e8d4] via-[#f2e3cd] to-[#efdcc4]">
            {/* Two columns on phones as well as desktop — brand/contact on the
                start side, quick links on the end side — rather than one tall
                stack. Everything steps down with `max-md:` variants. */}
            <div className="mx-auto flex max-w-6xl items-start gap-12 px-6 py-14 max-md:gap-5 max-md:py-6 md:justify-between md:gap-8">
                {/* 🔑 `md:contents` dissolves this wrapper at desktop, so the logo
                    and the company block become direct children of the row again
                    and the original three-column layout is preserved exactly. On
                    phones it groups them into the single start-side column. */}
                <div className="flex min-w-0 flex-1 flex-col items-center gap-4 max-md:items-start md:contents">
                    {/* Brand logo — start side in both directions. Links home, same
                        as the navbar logo: a footer logo is somewhere people expect to
                        be able to click, and it is the only way back to the homepage
                        from down here (the quick links cover everything except home). */}
                    <Link href="/" className="shrink-0 transition-opacity hover:opacity-75" aria-label={t('brand')}>
                        <img src="/images/footer/logo.png" alt={t('footer.companyName')} className="h-auto w-40 max-md:w-24 md:w-48" />
                    </Link>

                    {/* Company / contact block (centre column on desktop) */}
                    <div className="flex flex-col items-center gap-6 text-center max-md:w-full max-md:items-start max-md:gap-2.5 max-md:text-start">
                        <h3 className="font-heading text-brand-teal text-xl font-bold max-md:text-base md:text-2xl">{t('footer.companyName')}</h3>

                        {/* Official badges: commercial registration + VAT.
                        On phones these previously wrapped to one per row, costing a
                        whole extra band of height for two short numbers — shrinking
                        the badge and label lets both sit side by side. */}
                        <div className="flex flex-wrap items-center justify-center gap-x-8 gap-y-4 max-md:justify-start max-md:gap-x-4 max-md:gap-y-2">
                            <div className="flex items-center gap-2 max-md:gap-1.5">
                                <div className="text-start leading-tight">
                                    <div className="text-brand-teal text-sm font-bold max-md:text-[0.7rem]">{t('footer.commercialReg')}</div>
                                    <div dir="ltr" className="text-brand-teal text-xs font-semibold tracking-wide max-md:text-[0.65rem]">
                                        {footer.commercial_registration}
                                    </div>
                                </div>
                                <img
                                    src="/images/footer/badge-commerce.png"
                                    alt={t('footer.commercialReg')}
                                    className="h-12 w-12 object-contain max-md:h-8 max-md:w-8"
                                />
                            </div>
                            <div className="flex items-center gap-2 max-md:gap-1.5">
                                <div className="text-start leading-tight">
                                    <div className="text-brand-teal text-sm font-bold max-md:text-[0.7rem]">{t('footer.vatNumber')}</div>
                                    <div dir="ltr" className="text-brand-teal text-xs font-semibold tracking-wide max-md:text-[0.65rem]">
                                        {footer.vat_number}
                                    </div>
                                </div>
                                <img
                                    src="/images/footer/badge-vat.png"
                                    alt={t('footer.vatNumber')}
                                    className="h-12 w-12 object-contain max-md:h-8 max-md:w-8"
                                />
                            </div>
                        </div>

                        {/* Contact (LTR content) */}
                        <div
                            dir="ltr"
                            className="flex flex-wrap items-center justify-center gap-x-8 gap-y-2 max-md:justify-start max-md:gap-x-4 max-md:gap-y-1"
                        >
                            <a
                                href={`tel:${footer.contact_phone.replace(/\s/g, '')}`}
                                className="text-brand-teal flex items-center gap-2 transition-opacity hover:opacity-75 max-md:gap-1.5 max-md:text-sm"
                            >
                                <img src="/images/footer/icon-phone.png" alt="" className="h-7 w-7 max-md:h-5 max-md:w-5" />
                                <span className="font-semibold">{footer.contact_phone}</span>
                            </a>
                            <a
                                href={`mailto:${footer.contact_email}`}
                                className="text-brand-teal flex items-center gap-2 transition-opacity hover:opacity-75 max-md:gap-1.5 max-md:text-sm"
                            >
                                <img src="/images/footer/icon-email.png" alt="" className="h-7 w-7 max-md:h-5 max-md:w-5" />
                                <span className="font-semibold">{footer.contact_email}</span>
                            </a>
                        </div>

                        {/* Social icons (fixed visual order) */}
                        <div dir="ltr" className="flex items-center justify-center gap-3 max-md:justify-start max-md:gap-2">
                            {socials.map((s) => (
                                <a
                                    key={s.key}
                                    href={s.url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    aria-label={s.key}
                                    className="transition-opacity hover:opacity-75"
                                >
                                    <img src={`/images/footer/${s.icon}.png`} alt={s.key} className="h-9 w-9 max-md:h-7 max-md:w-7" />
                                </a>
                            ))}
                        </div>
                    </div>
                </div>

                {/* Quick links — end side in both directions, a single narrow
                    column so it reads as a nav list rather than a grid. The
                    back-to-top button that used to live here is now the fixed
                    ScrollToTop in the layout, reachable from anywhere. */}
                <div className="flex shrink-0 flex-col items-center gap-4 max-md:items-start max-md:gap-1.5 md:items-start">
                    <h3 className="font-heading text-brand-teal text-xl font-bold max-md:text-sm md:text-2xl">{t('footer.quickLinks')}</h3>
                    <ul className="flex flex-col items-center gap-3 max-md:items-start max-md:gap-1 md:items-start">
                        {QUICK_LINKS.map((l) => (
                            <li key={l.key}>
                                <Link href={l.href} className="font-heading text-brand-gold hover:text-brand-teal transition-colors max-md:text-xs">
                                    {t(`footer.links.${l.key}`)}
                                </Link>
                            </li>
                        ))}
                    </ul>
                </div>
            </div>

            {/* Legal bar, modelled on Cloudflare's: one thin rule-separated row of
                small, low-contrast items that are not part of the site's navigation —
                copyright, the legal page, the cookie control, and the social handle.
                The handle lived under the social icons as bold gold text, which read
                as a heading for the block below it rather than as a byline; at bar
                scale it is clearly an aside.

                The cookie control MOVED here from the quick links rather than being
                duplicated. It is not a page, and a footer that offers the same button
                twice makes the reader look for the difference. */}
            <div className="border-brand-gold/25 border-t">
                <div className="text-brand-teal/70 mx-auto flex max-w-6xl flex-col items-center gap-x-6 gap-y-3 px-6 py-5 text-xs max-md:pb-16 max-sm:gap-y-1.5 max-sm:pt-3 max-sm:text-[0.7rem] sm:flex-row sm:justify-between">
                    {/* Resolved on the client so it can never go stale, which a
                        hardcoded or build-time year would. ⚠️ The SSR sidecar renders
                        in the container's timezone and the visitor's browser in theirs,
                        so for a few hours around New Year the two can disagree by one
                        year and React will patch the text on hydration. Cosmetic, and
                        the alternative (shipping the server's year as a prop) would
                        show a KSA visitor the wrong year for those same hours. */}
                    <p>{t('footer.copyright', { year: new Date().getFullYear(), company: t('footer.companyName') })}</p>

                    <div className="flex flex-wrap items-center justify-center gap-x-6 gap-y-3">
                        <Link href="/pages/privacy-policy" className="hover:text-brand-teal transition-colors">
                            {t('footer.privacyPolicy')}
                        </Link>

                        {/* Re-opens the consent banner so a visitor can change their
                            choice at any time. Deliberately a button, not a link: it
                            navigates nowhere. */}
                        <button
                            type="button"
                            onClick={() => window.dispatchEvent(new Event(OPEN_CONSENT_EVENT))}
                            className="hover:text-brand-teal flex items-center gap-1.5 transition-colors"
                        >
                            <Cookie className="h-4 w-4" aria-hidden />
                            {t('consent.settings')}
                        </button>

                        {/* The handle is the same on every network, so it points at
                            nothing in particular and stays plain text. */}
                        <span dir="ltr" className="tracking-wide">
                            {HANDLE}
                        </span>

                        {/* Build credit. Deliberately the quietest thing in the bar:
                            it inherits the row's colour at rest so it never competes
                            with the client's own brand, and only earns the gold accent
                            on hover — gold rather than the neighbours' teal, so it
                            reads as a different KIND of link (off-site) rather than
                            another Retab page.

                            Links to www, which is where the apex redirects — no point
                            sending every visitor through an extra hop.

                            `rel="noopener"` is not optional on a target=_blank link:
                            without it the opened page gets a handle on this one via
                            window.opener. */}
                        <a
                            href="https://www.hardrock-co.com"
                            target="_blank"
                            rel="noopener noreferrer"
                            className="group hover:text-brand-gold inline-flex items-center gap-1 transition-colors"
                        >
                            {t('footer.builtBy')}
                            {/* Sized off the type (`h-[1.15em]`) rather than a fixed
                                px value, so it stays optically matched to the label if
                                the bar's font size is ever changed. The emblem is
                                taller than wide (155.55 × 219.67 ≈ 0.71), so width is
                                left to `w-auto` instead of being forced square — a
                                square box would squash it. */}
                            <HardRockMark className="h-[1.15em] w-auto" />
                            {/* The underline is a background gradient sized 0% → 100%
                                rather than a border, so it WIPES in instead of just
                                appearing. ⚠️ It has to start from the reading side or
                                the wipe runs backwards in Arabic, hence the rtl
                                position override. */}
                            <span
                                dir="ltr"
                                className="bg-[linear-gradient(currentColor,currentColor)] bg-[length:0%_1px] bg-[position:0_100%] bg-no-repeat font-semibold transition-[background-size] duration-300 group-hover:bg-[length:100%_1px] rtl:bg-[position:100%_100%]"
                            >
                                HardRock
                            </span>
                            {/* Always rendered, so hovering can't shift the row's
                                layout; it just brightens and steps outward. Mirrored
                                under RTL so "away from the text" stays away. */}
                            <ArrowUpRight
                                className="h-3 w-3 opacity-50 transition-all duration-300 group-hover:translate-x-px group-hover:-translate-y-px group-hover:opacity-100 rtl:-scale-x-100 rtl:group-hover:-translate-x-px"
                                aria-hidden
                            />
                        </a>
                    </div>
                </div>
            </div>
        </footer>
    );
}
