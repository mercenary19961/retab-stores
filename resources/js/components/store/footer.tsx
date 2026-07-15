import { Link } from '@inertiajs/react';
import { ArrowUp } from 'lucide-react';
import { useTranslation } from 'react-i18next';

/**
 * Store footer (from the Figma design). The background artwork is effectively a
 * flat cream, so it's a CSS colour rather than an image. Everything else is real
 * markup: text (translatable, Tajawal), the brand logo, the two official badges
 * (commercial registration + VAT) and the contact/social icon set.
 *
 * Static business details (contact, CR, VAT) live here as constants for now;
 * they could move to admin `settings` later. Social URLs are best-guesses from
 * the @RETAB_DATES handle — verify them with the client before launch.
 */
const PHONE_DISPLAY = '+966 5 5088 3845';
const PHONE_TEL = '+966550883845';
const EMAIL = 'Info@retab.com.sa';
const COMMERCIAL_REG = '7001744098';
const VAT_NUMBER = '300789485500003';
const HANDLE = '@RETAB_DATES';

const SOCIALS = [
    { key: 'snapchat', icon: 'social-snapchat', url: 'https://www.snapchat.com/add/retab_dates' },
    { key: 'facebook', icon: 'social-facebook', url: 'https://www.facebook.com/retab_dates' },
    { key: 'instagram', icon: 'social-instagram', url: 'https://www.instagram.com/retab_dates' },
    { key: 'x', icon: 'social-x', url: 'https://x.com/retab_dates' },
    { key: 'linkedin', icon: 'social-linkedin', url: 'https://www.linkedin.com/company/retab_dates' },
] as const;

const QUICK_LINKS = [
    { key: 'returnPolicy', href: '/pages/returns-policy' },
    { key: 'contact', href: '/pages/contact' },
    { key: 'branches', href: '/pages/branches' },
    { key: 'dates', href: '/shop' },
] as const;

export default function StoreFooter() {
    const { t } = useTranslation();

    const scrollTop = () => window.scrollTo({ top: 0, behavior: 'smooth' });

    return (
        <footer className="mt-12 bg-gradient-to-b from-[#f6e8d4] via-[#f2e3cd] to-[#efdcc4]">
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
                                <div dir="ltr" className="text-xs font-semibold tracking-wide text-brand-teal">{COMMERCIAL_REG}</div>
                            </div>
                            <img src="/images/footer/badge-commerce.png" alt={t('footer.commercialReg')} className="h-12 w-12 object-contain" />
                        </div>
                        <div className="flex items-center gap-2">
                            <div className="text-start leading-tight">
                                <div className="text-sm font-bold text-brand-teal">{t('footer.vatNumber')}</div>
                                <div dir="ltr" className="text-xs font-semibold tracking-wide text-brand-teal">{VAT_NUMBER}</div>
                            </div>
                            <img src="/images/footer/badge-vat.png" alt={t('footer.vatNumber')} className="h-12 w-12 object-contain" />
                        </div>
                    </div>

                    {/* Contact (LTR content) */}
                    <div dir="ltr" className="flex flex-wrap items-center justify-center gap-x-8 gap-y-2">
                        <a href={`tel:${PHONE_TEL}`} className="flex items-center gap-2 text-brand-teal transition-opacity hover:opacity-75">
                            <img src="/images/footer/icon-phone.png" alt="" className="h-7 w-7" />
                            <span className="font-semibold">{PHONE_DISPLAY}</span>
                        </a>
                        <a href={`mailto:${EMAIL}`} className="flex items-center gap-2 text-brand-teal transition-opacity hover:opacity-75">
                            <img src="/images/footer/icon-email.png" alt="" className="h-7 w-7" />
                            <span className="font-semibold">{EMAIL}</span>
                        </a>
                    </div>

                    {/* Social icons (fixed visual order) */}
                    <div dir="ltr" className="flex items-center justify-center gap-3">
                        {SOCIALS.map((s) => (
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
