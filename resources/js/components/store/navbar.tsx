import { useLanguage } from '@/contexts/LanguageContext';
import { useLocalized } from '@/lib/localize';
import { Link, usePage } from '@inertiajs/react';
import { Menu, Search, ShoppingBag, User, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
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
    hasOffers?: boolean;
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

/*
 * Header scroll behaviour: it slides away with the page from the first pixel of
 * downward scroll. Coming back is deliberately harder than going away — it takes
 * REVEAL_AFTER px of sustained upward scrolling and then waits REVEAL_DELAY_MS
 * before it starts moving, so it cannot flicker in and out during ordinary reading.
 *
 * 🔴 The header's HEIGHT IS NOW CONSTANT, and that is load-bearing, not a style
 * choice. It used to collapse its padding (~40px) once scrolled, and because the
 * header is `sticky` (still in normal flow) that shortened the whole page, which
 * the browser's scroll anchoring compensated for by *reducing* scrollY by about
 * the same amount. The direction detector below then read that synthetic jump as
 * a genuine scroll UP and re-revealed the header — which is why one wheel notch
 * used to land on "compact and visible" instead of "hidden", and why a second
 * notch was needed to actually hide it. Measured: from y=0, one 120px notch ended
 * at y=80 with the header re-shown.
 *
 * So a height change cannot coexist with direction-based hide/reveal while the
 * header is in flow. Anything reintroducing a collapse, a growing announcement
 * bar, or any other in-flow height change here will bring the artifact back;
 * animate colour/shadow/opacity instead, which are layout-neutral.
 *
 * SHADOW_AT needs no hysteresis for the same reason: a shadow does not affect
 * layout, so it cannot feed back into scroll position.
 *
 * 🔴 WHY IT TRAVELS WITH THE SCROLL INSTEAD OF STAYING PINNED: being `sticky` the
 * header keeps its box in normal flow, and a transform does not remove that box. So
 * translating it away while the viewport is still inside that box just exposes the
 * box — a blank strip of page background above the content (measured: at y=60 the
 * element at the viewport top was the page wrapper, not the hero).
 *
 * That used to be handled by PINNING the header open for its own height (~130px on
 * desktop), which is why hiding it took an unnaturally long scroll: a 120px wheel
 * notch from the top did nothing at all.
 *
 * It now translates by exactly `-scrollY` while inside that band. Its box sits at
 * page 0..H, i.e. viewport -y..H-y, and the header offset by -y lands on precisely
 * the same rectangle, so it COVERS its own box at every position instead of
 * revealing it. Visually that is just a normal header scrolling away, and it starts
 * on the first pixel. Past H the offset caps and it is simply gone, so the handoff
 * to the hidden state is continuous rather than a jump.
 *
 * ⚠️ The offset is written straight to the node in the rAF callback, NOT held in
 * React state: a per-pixel value in state would re-render the whole header on every
 * scroll frame. React still owns `scrolled` (the shadow), which flips rarely.
 *
 * ⚠️ The transition is skipped ONLY while tracking, because a transition there
 * makes the header lag the page and feel rubbery. Every other move — the reveal, and
 * a re-hide from a revealed state mid-band — is a jump and must be animated or it
 * visibly snaps.
 *
 * 🔴 The continuity test compares the current offset against where tracking WOULD
 * have put it last frame, NOT against the scroll delta. The delta version was the
 * first attempt and is subtly wrong: a fast flick has a large delta, so a reveal
 * (offset 130 → 0) measures as "smaller than the scroll" and is treated as tracking,
 * skipping the transition. The symptom is that the header appears instantly, and
 * gets worse the faster you scroll up — a slow scroll animates fine, which is what
 * makes it easy to miss in testing.
 */
const SHADOW_AT = 8;

/** Floor for the travel band, in case the height measurement is unavailable. */
const MIN_TRAVEL_BAND = 24;

/** Minimum scroll delta before a direction change counts, to ignore jitter. */
const DIRECTION_DELTA = 4;

/**
 * Cumulative UPWARD distance required before the header comes back, in px.
 *
 * A single wheel notch is ~100-120px in Chrome, so one notch used to be enough and
 * the header reappeared on the smallest correction. 240px is deliberately about two
 * notches: the reveal now needs a clear intent to go back up, not a nudge.
 *
 * It has to be an ACCUMULATOR rather than a bigger DIRECTION_DELTA. A per-event
 * threshold would break trackpads, which emit a stream of small deltas — a long
 * two-finger swipe would never produce one event over 240px and the header could
 * never come back at all. Summing consecutive upward movement treats a fast wheel
 * flick and a slow trackpad drag the same.
 */
const REVEAL_AFTER = 240;

/**
 * Delay before the reveal starts moving. Applied as a CSS transition-delay on the
 * reveal only (never on the hide, which must feel immediate), so a brief scroll-up
 * mid-gesture does not flash the header: if the direction flips back down inside
 * the delay, the target changes before the element has moved at all.
 */
const REVEAL_DELAY_MS = 1000;

export default function StoreNavbar() {
    const { t } = useTranslation();
    const { toggleLanguage } = useLanguage();
    const localized = useLocalized();
    const page = usePage();
    const props = page.props as SharedProps;
    const url = page.url;

    const navCategories = props.navCategories ?? [];
    const hasOffers = Boolean(props.hasOffers);
    const cartCount = props.cart?.count ?? 0;
    const loggedIn = Boolean(props.auth?.user);
    const accountHref = loggedIn ? '/account' : '/login/whatsapp';

    const [mobileOpen, setMobileOpen] = useState(false);

    // Reveal-on-scroll-up navbar: one scroll down hides it outright, scrolling up
    // slides it straight back in, so navigation is always a flick away. `scrolled`
    // now drives ONLY the drop shadow — see the note above the constants.
    const [scrolled, setScrolled] = useState(false);
    const headerRef = useRef<HTMLElement>(null);

    useEffect(() => {
        const header = headerRef.current;
        if (!header) return;

        let lastY = window.scrollY;
        let ticking = false;
        let revealed = true;
        let offset = 0;
        // Running total of consecutive upward movement, reset the moment the user
        // scrolls down again so slow jitter can never creep up to the threshold.
        let upward = 0;
        // Cached, not read per frame: reading offsetHeight inside the scroll
        // handler would force a layout flush on every tick. The height is constant
        // now, so it only needs re-measuring when the breakpoint changes.
        let band = MIN_TRAVEL_BAND;
        const measure = () => {
            band = Math.max(header.offsetHeight, MIN_TRAVEL_BAND);
        };
        measure();

        const update = () => {
            const y = window.scrollY;
            setScrolled(y > SHADOW_AT);

            if (y > lastY + DIRECTION_DELTA) {
                revealed = false; // scrolling down → let it go
                upward = 0;
            } else if (y < lastY - DIRECTION_DELTA) {
                // Reveal is earned, not immediate: it takes REVEAL_AFTER px of
                // sustained upward scrolling, roughly two wheel notches.
                upward += lastY - y;
                if (upward >= REVEAL_AFTER) revealed = true;
            }

            // Capped at the band: past its own height the header is fully gone, and
            // clamping here is what makes the tracking phase hand over to the hidden
            // state without a visible step.
            const next = revealed ? 0 : Math.min(y, band);

            // Instant ONLY while following the page down 1:1 from wherever the
            // previous frame already left us. The test compares against where
            // tracking would have put us last frame — deliberately NOT against the
            // scroll delta, which was the first attempt and is wrong: a fast flick
            // has a large delta, so a reveal (offset 130 → 0) reads as "smaller
            // than the scroll" and gets no transition. The faster you scrolled up,
            // the harder the header snapped in.
            const continuous = !revealed && Math.abs(offset - Math.min(lastY, band)) <= 1;

            // The delay is on the REVEAL only. A hide has to feel immediate, and
            // delaying it would leave the header sitting over content the user has
            // already scrolled past.
            const delay = revealed ? ` ${REVEAL_DELAY_MS}ms` : '';
            header.style.transition = continuous
                ? 'box-shadow 300ms ease'
                : `transform 400ms ease-out${delay}, opacity 400ms ease-out${delay}, box-shadow 300ms ease`;
            header.style.transform = `translate3d(0, ${-next}px, 0)`;
            // Fades only on the discontinuous moves. During tracking it must stay
            // fully opaque or it would dissolve while scrolling, which reads as a
            // glitch rather than as a header leaving the page; by the time this
            // hits 0 while tracking, the element is already off-screen anyway.
            header.style.opacity = next >= band ? '0' : '1';
            // Only unreachable once it is entirely off-screen; while it is mid-travel
            // it is still partly visible and must stay clickable.
            header.style.pointerEvents = next >= band ? 'none' : '';

            offset = next;
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
        window.addEventListener('resize', measure);
        return () => {
            window.removeEventListener('scroll', onScroll);
            window.removeEventListener('resize', measure);
        };
    }, []);

    const isActive = (href: string) => (href === '/' ? url === '/' : url.startsWith(href));
    const isCategoryActive = (slug: string) => url.includes(`category=${slug}`);

    // Shared classes for a top-level nav link.
    const linkBase = 'rounded-full px-4 py-1.5 text-sm font-medium transition-colors';
    const linkIdle = 'text-brand-gold hover:text-brand-teal';
    const linkActive = 'bg-[#d9d9d9]/25 text-brand-teal';

    return (
        <header ref={headerRef} className={`border-brand-gold/10 sticky top-0 z-40 border-b bg-white ${scrolled ? 'shadow-md' : ''}`}>
            {/* Faint knot watermark (LOGO 2), clipped to the header via its own
                overflow-hidden wrapper so it never clips the nav dropdowns. */}
            <div className="pointer-events-none absolute inset-0 overflow-hidden">
                <img
                    src="/images/brand/navbar-pattern.png"
                    alt=""
                    aria-hidden
                    className="absolute end-0 top-0 h-full w-auto opacity-70 select-none"
                />
            </div>

            <div className="relative mx-auto max-w-[1600px] px-6 lg:px-12">
                {/* Row 1 — utility icons · logo · language. Padding is FIXED on
                    purpose; see the constants note about in-flow height changes. */}
                <div className="grid grid-cols-3 items-center py-3">
                    {/* Start: utility icons (desktop) / hamburger (mobile) */}
                    <div className="flex items-center gap-4 justify-self-start">
                        <button
                            type="button"
                            onClick={() => setMobileOpen(true)}
                            aria-label={t('nav.menu')}
                            className="text-brand-gold hover:text-brand-teal transition-colors md:hidden"
                        >
                            <Menu className="size-6" />
                        </button>
                        {/* Catalogue hosts the product search box. */}
                        <Link
                            href="/shop"
                            aria-label={t('nav.search')}
                            className="text-brand-gold hover:text-brand-teal hidden transition-colors md:inline-flex"
                        >
                            <Search className="size-5" />
                        </Link>
                        <Link
                            href={accountHref}
                            aria-label={t('common.myAccount')}
                            className="text-brand-gold hover:text-brand-teal hidden transition-colors md:inline-flex"
                        >
                            <User className="size-5" />
                        </Link>
                        <Link
                            href="/cart"
                            aria-label={t('common.cart')}
                            className="text-brand-gold hover:text-brand-teal relative hidden transition-colors md:inline-flex"
                        >
                            <ShoppingBag className="size-5" />
                            {cartCount > 0 && (
                                <span className="bg-brand-teal absolute -end-2 -top-2 flex size-4 items-center justify-center rounded-full text-[10px] font-bold text-white">
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
                            className="text-brand-gold hover:text-brand-teal relative transition-colors md:hidden"
                        >
                            <ShoppingBag className="size-6" />
                            {cartCount > 0 && (
                                <span className="bg-brand-teal absolute -end-2 -top-2 flex size-4 items-center justify-center rounded-full text-[10px] font-bold text-white">
                                    {cartCount}
                                </span>
                            )}
                        </Link>
                        <button
                            type="button"
                            data-testid="lang-toggle"
                            onClick={toggleLanguage}
                            className="border-brand-gold/40 text-brand-gold hover:bg-brand-gold/10 rounded-full border px-3 py-1 text-sm transition-colors"
                        >
                            {t('common.switchLanguage')}
                        </button>
                    </div>
                </div>

                {/* Row 2 — primary nav links (desktop). Padding fixed, as above. */}
                <nav className="border-brand-gold/10 hidden items-center justify-between border-t py-2 md:flex">
                    <Link href="/" className={`${linkBase} ${isActive('/') ? linkActive : linkIdle}`}>
                        {t('nav.home')}
                    </Link>

                    {navCategories.map((cat) =>
                        cat.children.length > 0 ? (
                            <div key={cat.id} className="group relative">
                                <Link href="/" className={`${linkBase} ${linkIdle} inline-flex items-center gap-1.5`}>
                                    {localized(cat, 'name')}
                                    <Caret />
                                </Link>
                                <div className="border-brand-gold/15 invisible absolute start-0 top-full z-20 min-w-48 -translate-y-1 rounded-xl border bg-white p-2 opacity-0 shadow-lg transition-all group-focus-within:visible group-focus-within:translate-y-0 group-focus-within:opacity-100 group-hover:visible group-hover:translate-y-0 group-hover:opacity-100">
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

                    {hasOffers && (
                        <Link href="/shop?on_sale=1" className={`${linkBase} ${url.includes('on_sale=1') ? linkActive : linkIdle}`}>
                            {t('nav.offers')}
                        </Link>
                    )}
                    <Link href="/pages/about" className={`${linkBase} ${isActive('/pages/about') ? linkActive : linkIdle}`}>
                        {t('nav.about')}
                    </Link>
                    <Link href="/pages/contact" className={`${linkBase} ${isActive('/pages/contact') ? linkActive : linkIdle}`}>
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

                        <Link href="/" className="text-brand-gold hover:bg-brand-cream rounded-lg px-3 py-2" onClick={() => setMobileOpen(false)}>
                            {t('nav.home')}
                        </Link>

                        {navCategories.map((cat) => (
                            <div key={cat.id} className="py-1">
                                <p className="text-brand-teal px-3 py-1 text-xs font-semibold tracking-wide uppercase">{localized(cat, 'name')}</p>
                                {cat.children.length > 0 ? (
                                    cat.children.map((child) => (
                                        <Link
                                            key={child.id}
                                            href={`/shop?category=${child.slug}`}
                                            className="text-brand-gold hover:bg-brand-cream hover:text-brand-teal block rounded-lg px-5 py-2 text-sm"
                                            onClick={() => setMobileOpen(false)}
                                        >
                                            {localized(child, 'name')}
                                        </Link>
                                    ))
                                ) : (
                                    <Link
                                        href={`/shop?category=${cat.slug}`}
                                        className="text-brand-gold hover:bg-brand-cream block rounded-lg px-5 py-2 text-sm"
                                        onClick={() => setMobileOpen(false)}
                                    >
                                        {localized(cat, 'name')}
                                    </Link>
                                )}
                            </div>
                        ))}

                        {hasOffers && (
                            <Link
                                href="/shop?on_sale=1"
                                className="text-brand-gold hover:bg-brand-cream rounded-lg px-3 py-2"
                                onClick={() => setMobileOpen(false)}
                            >
                                {t('nav.offers')}
                            </Link>
                        )}
                        <Link
                            href="/pages/about"
                            className="text-brand-gold hover:bg-brand-cream rounded-lg px-3 py-2"
                            onClick={() => setMobileOpen(false)}
                        >
                            {t('nav.about')}
                        </Link>
                        <Link
                            href="/pages/contact"
                            className="text-brand-gold hover:bg-brand-cream rounded-lg px-3 py-2"
                            onClick={() => setMobileOpen(false)}
                        >
                            {t('nav.contact')}
                        </Link>

                        <div className="border-brand-gold/15 mt-3 flex items-center gap-3 border-t pt-3">
                            <Link
                                href={accountHref}
                                className="text-brand-gold hover:bg-brand-cream flex-1 rounded-lg px-3 py-2 text-sm"
                                onClick={() => setMobileOpen(false)}
                            >
                                {t('common.myAccount')}
                            </Link>
                            <button
                                type="button"
                                onClick={() => {
                                    toggleLanguage();
                                    setMobileOpen(false);
                                }}
                                className="border-brand-gold/40 text-brand-gold hover:bg-brand-gold/10 rounded-full border px-3 py-1 text-sm"
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
