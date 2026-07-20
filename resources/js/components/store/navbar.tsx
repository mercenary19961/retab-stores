import { useLanguage } from '@/contexts/LanguageContext';
import { useLocalized } from '@/lib/localize';
import { Link, usePage } from '@inertiajs/react';
import { Menu, Search, ShoppingBag, User, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

interface NavCategory {
    id: number;
    name_ar: string;
    name_en: string | null;
    slug: string;
    children: { id: number; name_ar: string; name_en: string | null; slug: string }[];
}

interface SharedProps {
    navCategories?: NavCategory[];
    cart?: { count?: number };
    auth?: { user?: unknown };
    [key: string]: unknown;
}

/** The teal filled caret from the Figma (Polygon 2). */
function Caret() {
    return (
        <svg width="9" height="8" viewBox="0 0 13 12" fill="none" aria-hidden className="text-brand-teal">
            <path
                d="M8.06506 10.5C7.29526 11.8333 5.37076 11.8333 4.60096 10.5L0.270827 3C-0.498974 1.66666 0.463279 0 2.00288 0L10.6631 0C12.2027 0 13.165 1.66667 12.3952 3L8.06506 10.5Z"
                fill="currentColor"
            />
        </svg>
    );
}

export default function StoreNavbar() {
    const { t } = useTranslation();
    const { toggleLanguage } = useLanguage();
    const localized = useLocalized();
    const page = usePage();
    const props = page.props as SharedProps;
    const url = page.url;

    const navCategories = props.navCategories ?? [];
    const cartCount = props.cart?.count ?? 0;
    const loggedIn = Boolean(props.auth?.user);
    const accountHref = loggedIn ? '/account' : '/login/whatsapp';

    const [mobileOpen, setMobileOpen] = useState(false);

    // Reveal-on-scroll-up navbar: hide when scrolling down, fade + slide back in
    // when scrolling up (or near the top) so navigation is always a flick away.
    const [show, setShow] = useState(true);
    const [scrolled, setScrolled] = useState(false);

    useEffect(() => {
        let lastY = window.scrollY;
        let ticking = false;
        const update = () => {
            const y = window.scrollY;
            setScrolled(y > 8);
            if (y < 80) {
                setShow(true); // always visible near the top
            } else if (y > lastY + 4) {
                setShow(false); // scrolling down → hide
            } else if (y < lastY - 4) {
                setShow(true); // scrolling up → reveal
            }
            lastY = y;
            ticking = false;
        };
        const onScroll = () => {
            if (!ticking) {
                ticking = true;
                requestAnimationFrame(update);
            }
        };
        window.addEventListener('scroll', onScroll, { passive: true });
        return () => window.removeEventListener('scroll', onScroll);
    }, []);

    const isActive = (href: string) => (href === '/' ? url === '/' : url.startsWith(href));
    const isCategoryActive = (slug: string) => url.includes(`category=${slug}`);

    // Shared classes for a top-level nav link.
    const linkBase = 'rounded-full px-4 py-1.5 text-sm font-medium transition-colors';
    const linkIdle = 'text-brand-gold hover:text-brand-teal';
    const linkActive = 'bg-[#d9d9d9]/25 text-brand-teal';

    return (
        <header
            className={`sticky top-0 z-40 border-b border-brand-gold/10 bg-white transition-all duration-700 ${
                show ? 'translate-y-0 opacity-100' : 'pointer-events-none -translate-y-full opacity-0'
            } ${scrolled ? 'shadow-md' : ''}`}
        >
            {/* Faint knot watermark (LOGO 2), clipped to the header via its own
                overflow-hidden wrapper so it never clips the nav dropdowns. */}
            <div className="pointer-events-none absolute inset-0 overflow-hidden">
                <img
                    src="/images/brand/navbar-pattern.png"
                    alt=""
                    aria-hidden
                    className="absolute top-0 end-0 h-full w-auto select-none opacity-70"
                />
            </div>

            <div className="relative mx-auto max-w-[1600px] px-6 lg:px-12">
                {/* Row 1 — utility icons · logo · language. Padding collapses once
                    scrolled so the floating navbar is vertically compact; full at top. */}
                <div className={`grid grid-cols-3 items-center transition-[padding] duration-300 ${scrolled ? 'py-0' : 'py-3'}`}>
                    {/* Start: utility icons (desktop) / hamburger (mobile) */}
                    <div className="flex items-center gap-4 justify-self-start">
                        <button
                            type="button"
                            onClick={() => setMobileOpen(true)}
                            aria-label={t('nav.menu')}
                            className="text-brand-gold transition-colors hover:text-brand-teal md:hidden"
                        >
                            <Menu className="size-6" />
                        </button>
                        {/* Catalogue hosts the product search box. */}
                        <Link
                            href="/shop"
                            aria-label={t('nav.search')}
                            className="hidden text-brand-gold transition-colors hover:text-brand-teal md:inline-flex"
                        >
                            <Search className="size-5" />
                        </Link>
                        <Link
                            href={accountHref}
                            aria-label={t('common.myAccount')}
                            className="hidden text-brand-gold transition-colors hover:text-brand-teal md:inline-flex"
                        >
                            <User className="size-5" />
                        </Link>
                        <Link
                            href="/cart"
                            aria-label={t('common.cart')}
                            className="relative hidden text-brand-gold transition-colors hover:text-brand-teal md:inline-flex"
                        >
                            <ShoppingBag className="size-5" />
                            {cartCount > 0 && (
                                <span className="absolute -end-2 -top-2 flex size-4 items-center justify-center rounded-full bg-brand-teal text-[10px] font-bold text-white">
                                    {cartCount}
                                </span>
                            )}
                        </Link>
                    </div>

                    {/* Center: logo */}
                    <Link href="/" className="justify-self-center" aria-label={t('brand')}>
                        <img src="/images/brand/logo.png" alt={t('brand')} className="h-14 w-auto" />
                    </Link>

                    {/* End: language toggle (desktop) / cart (mobile) */}
                    <div className="flex items-center gap-3 justify-self-end">
                        <Link
                            href="/cart"
                            aria-label={t('common.cart')}
                            className="relative text-brand-gold transition-colors hover:text-brand-teal md:hidden"
                        >
                            <ShoppingBag className="size-6" />
                            {cartCount > 0 && (
                                <span className="absolute -end-2 -top-2 flex size-4 items-center justify-center rounded-full bg-brand-teal text-[10px] font-bold text-white">
                                    {cartCount}
                                </span>
                            )}
                        </Link>
                        <button
                            type="button"
                            onClick={toggleLanguage}
                            className="rounded-full border border-brand-gold/40 px-3 py-1 text-sm text-brand-gold transition-colors hover:bg-brand-gold/10"
                        >
                            {t('common.switchLanguage')}
                        </button>
                    </div>
                </div>

                {/* Row 2 — primary nav links (desktop). Padding collapses when scrolled. */}
                <nav className={`hidden items-center justify-between border-t border-brand-gold/10 transition-[padding] duration-300 md:flex ${scrolled ? 'py-0' : 'py-2'}`}>
                    <Link href="/" className={`${linkBase} ${isActive('/') ? linkActive : linkIdle}`}>
                        {t('nav.home')}
                    </Link>

                    {navCategories.map((cat) =>
                        cat.children.length > 0 ? (
                            <div key={cat.id} className="group relative">
                                <Link
                                    href="/"
                                    className={`${linkBase} ${linkIdle} inline-flex items-center gap-1.5`}
                                >
                                    {localized(cat, 'name')}
                                    <Caret />
                                </Link>
                                <div className="invisible absolute top-full start-0 z-20 min-w-48 -translate-y-1 rounded-xl border border-brand-gold/15 bg-white p-2 opacity-0 shadow-lg transition-all group-focus-within:visible group-focus-within:translate-y-0 group-focus-within:opacity-100 group-hover:visible group-hover:translate-y-0 group-hover:opacity-100">
                                    {cat.children.map((child) => (
                                        <Link
                                            key={child.id}
                                            href={`/shop?category=${child.slug}`}
                                            className={`block rounded-lg px-3 py-2 text-sm transition-colors ${
                                                isCategoryActive(child.slug)
                                                    ? 'bg-brand-cream text-brand-teal'
                                                    : 'text-brand-gold hover:bg-brand-cream hover:text-brand-teal'
                                            }`}
                                        >
                                            {localized(child, 'name')}
                                        </Link>
                                    ))}
                                </div>
                            </div>
                        ) : (
                            <Link
                                key={cat.id}
                                href={`/shop?category=${cat.slug}`}
                                className={`${linkBase} ${isCategoryActive(cat.slug) ? linkActive : linkIdle}`}
                            >
                                {localized(cat, 'name')}
                            </Link>
                        ),
                    )}

                    <Link
                        href="/shop?on_sale=1"
                        className={`${linkBase} ${url.includes('on_sale=1') ? linkActive : linkIdle}`}
                    >
                        {t('nav.offers')}
                    </Link>
                    <Link href="/pages/about" className={`${linkBase} ${isActive('/pages/about') ? linkActive : linkIdle}`}>
                        {t('nav.about')}
                    </Link>
                    <Link
                        href="/pages/contact"
                        className={`${linkBase} ${isActive('/pages/contact') ? linkActive : linkIdle}`}
                    >
                        {t('nav.contact')}
                    </Link>
                </nav>
            </div>

            {/* Mobile drawer */}
            {mobileOpen && (
                <div className="fixed inset-0 z-50 md:hidden">
                    <div className="absolute inset-0 bg-black/40" onClick={() => setMobileOpen(false)} />
                    <div className="absolute inset-y-0 start-0 flex w-72 max-w-[80%] flex-col gap-1 overflow-y-auto bg-white p-4 shadow-xl">
                        <div className="mb-2 flex items-center justify-between">
                            <img src="/images/brand/logo.png" alt={t('brand')} className="h-10 w-auto" />
                            <button
                                type="button"
                                onClick={() => setMobileOpen(false)}
                                aria-label={t('nav.closeMenu')}
                                className="text-brand-gold hover:text-brand-teal"
                            >
                                <X className="size-6" />
                            </button>
                        </div>

                        <Link href="/" className="rounded-lg px-3 py-2 text-brand-gold hover:bg-brand-cream" onClick={() => setMobileOpen(false)}>
                            {t('nav.home')}
                        </Link>

                        {navCategories.map((cat) => (
                            <div key={cat.id} className="py-1">
                                <p className="px-3 py-1 text-xs font-semibold uppercase tracking-wide text-brand-teal">
                                    {localized(cat, 'name')}
                                </p>
                                {cat.children.length > 0 ? (
                                    cat.children.map((child) => (
                                        <Link
                                            key={child.id}
                                            href={`/shop?category=${child.slug}`}
                                            className="block rounded-lg px-5 py-2 text-sm text-brand-gold hover:bg-brand-cream hover:text-brand-teal"
                                            onClick={() => setMobileOpen(false)}
                                        >
                                            {localized(child, 'name')}
                                        </Link>
                                    ))
                                ) : (
                                    <Link
                                        href={`/shop?category=${cat.slug}`}
                                        className="block rounded-lg px-5 py-2 text-sm text-brand-gold hover:bg-brand-cream"
                                        onClick={() => setMobileOpen(false)}
                                    >
                                        {localized(cat, 'name')}
                                    </Link>
                                )}
                            </div>
                        ))}

                        <Link href="/shop?on_sale=1" className="rounded-lg px-3 py-2 text-brand-gold hover:bg-brand-cream" onClick={() => setMobileOpen(false)}>
                            {t('nav.offers')}
                        </Link>
                        <Link href="/pages/about" className="rounded-lg px-3 py-2 text-brand-gold hover:bg-brand-cream" onClick={() => setMobileOpen(false)}>
                            {t('nav.about')}
                        </Link>
                        <Link href="/pages/contact" className="rounded-lg px-3 py-2 text-brand-gold hover:bg-brand-cream" onClick={() => setMobileOpen(false)}>
                            {t('nav.contact')}
                        </Link>

                        <div className="mt-3 flex items-center gap-3 border-t border-brand-gold/15 pt-3">
                            <Link href={accountHref} className="flex-1 rounded-lg px-3 py-2 text-sm text-brand-gold hover:bg-brand-cream" onClick={() => setMobileOpen(false)}>
                                {t('common.myAccount')}
                            </Link>
                            <button
                                type="button"
                                onClick={() => {
                                    toggleLanguage();
                                    setMobileOpen(false);
                                }}
                                className="rounded-full border border-brand-gold/40 px-3 py-1 text-sm text-brand-gold hover:bg-brand-gold/10"
                            >
                                {t('common.switchLanguage')}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </header>
    );
}
