import { Link, usePage } from '@inertiajs/react';
import { ArrowUp } from 'lucide-react';
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

    const scrollTop = () => window.scrollTo({ top: 0, behavior: 'smooth' });

    return (
        <footer className="bg-gradient-to-b from-[#f6e8d4] via-[#f2e3cd] to-[#efdcc4]">
            <div className="mx-auto flex max-w-6xl flex-col items-center gap-12 px-6 py-14 md:flex-row md:items-start md:justify-between md:gap-8">
                {/* Brand logo — rightmost in RTL, leftmost in LTR */}
                <div className="shrink-0">
                    <img
                        src="/images/footer/logo.png"
                        alt={t('footer.companyName')}
                        className="h-auto w-40 md:w-48"
                    />
                </div>

                {/* Company / contact block (centre) */}
                <div className="flex flex-col items-center gap-6 text-center">
                    <h3 className="font-heading text-xl font-bold text-brand-teal md:text-2xl">
                        {t('footer.companyName')}
                    </h3>

                    {/* Official badges: commercial registration + VAT */}
                    <div className="flex flex-wrap items-center justify-center gap-x-8 gap-y-4">
                        <div className="flex items-center gap-2">
                            <div className="text-start leading-tight">
                                <div className="text-sm font-bold text-brand-teal">{t('footer.commercialReg')}</div>
                                <div dir="ltr" className="text-xs font-semibold tracking-wide text-brand-teal">{footer.commercial_registration}</div>
                            </div>
                            <img src="/images/footer/badge-commerce.png" alt={t('footer.commercialReg')} className="h-12 w-12 object-contain" />
                        </div>
                        <div className="flex items-center gap-2">
                            <div className="text-start leading-tight">
                                <div className="text-sm font-bold text-brand-teal">{t('footer.vatNumber')}</div>
                                <div dir="ltr" className="text-xs font-semibold tracking-wide text-brand-teal">{footer.vat_number}</div>
                            </div>
                            <img src="/images/footer/badge-vat.png" alt={t('footer.vatNumber')} className="h-12 w-12 object-contain" />
                        </div>
                    </div>

                    {/* Contact (LTR content) */}
                    <div dir="ltr" className="flex flex-wrap items-center justify-center gap-x-8 gap-y-2">
                        <a href={`tel:${footer.contact_phone.replace(/\s/g, '')}`} className="flex items-center gap-2 text-brand-teal transition-opacity hover:opacity-75">
                            <img src="/images/footer/icon-phone.png" alt="" className="h-7 w-7" />
                            <span className="font-semibold">{footer.contact_phone}</span>
                        </a>
                        <a href={`mailto:${footer.contact_email}`} className="flex items-center gap-2 text-brand-teal transition-opacity hover:opacity-75">
                            <img src="/images/footer/icon-email.png" alt="" className="h-7 w-7" />
                            <span className="font-semibold">{footer.contact_email}</span>
                        </a>
                    </div>

                    {/* Social icons (fixed visual order) */}
                    <div dir="ltr" className="flex items-center justify-center gap-3">
                        {socials.map((s) => (
                            <a key={s.key} href={s.url} target="_blank" rel="noopener noreferrer" aria-label={s.key} className="transition-opacity hover:opacity-75">
                                <img src={`/images/footer/${s.icon}.png`} alt={s.key} className="h-9 w-9" />
                            </a>
                        ))}
                    </div>
                    <span dir="ltr" className="text-sm font-bold tracking-wide text-brand-gold">{HANDLE}</span>
                </div>

                {/* Quick links (leftmost in RTL) + back-to-top */}
                <div className="flex flex-col items-center gap-4 md:items-start">
                    <h3 className="font-heading text-xl font-bold text-brand-teal md:text-2xl">{t('footer.quickLinks')}</h3>
                    <ul className="flex flex-col items-center gap-3 md:items-start">
                        {QUICK_LINKS.map((l) => (
                            <li key={l.key}>
                                <Link href={l.href} className="font-heading text-brand-gold transition-colors hover:text-brand-teal">
                                    {t(`footer.links.${l.key}`)}
                                </Link>
                            </li>
                        ))}
                    </ul>
                    <button
                        type="button"
                        onClick={scrollTop}
                        aria-label={t('footer.backToTop')}
                        className="mt-2 flex h-12 w-12 items-center justify-center rounded-full bg-brand-gold text-white shadow-md transition-colors hover:bg-brand-teal"
                    >
                        <ArrowUp className="h-6 w-6" />
                    </button>
                </div>
            </div>
        </footer>
    );
}
