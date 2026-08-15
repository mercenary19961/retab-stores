import { ArrowUp } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';

const RADIUS = 20;
const CIRCUMFERENCE = 2 * Math.PI * RADIUS;

/** Fraction of the page that must be scrolled before the button appears. */
const APPEAR_AT = 0.7;

/**
 * Floating scroll-to-top button with a progress ring, ported from nuor-steel's
 * `ui/scroll-to-top.tsx` and re-skinned to the brand palette.
 *
 * Replaces the button that used to sit inside the footer, which could only be
 * reached by scrolling to the very bottom — i.e. exactly where it was least
 * needed. The ring doubles as a read-progress indicator.
 *
 * ⚠️ Pinned to the PHYSICAL left in both locales, not the logical start. Left
 * is the far side from the reading flow in Arabic (the primary locale), which
 * is the conventional home for a floating action, and it is also where it was
 * asked for in English.
 */
export default function ScrollToTop() {
    const [progress, setProgress] = useState(0);
    const [visible, setVisible] = useState(false);

    const update = useCallback(() => {
        const scrollable = document.documentElement.scrollHeight - window.innerHeight;
        const p = scrollable > 0 ? Math.min(1, window.scrollY / scrollable) : 0;

        setProgress(p);
        setVisible(p >= APPEAR_AT);
    }, []);

    useEffect(() => {
        // Guarded for the SSR sidecar, which has no window at module scope.
        if (typeof window === 'undefined') return;

        let raf: number | null = null;
        const onScroll = () => {
            // Coalesce to one measurement per frame: scroll fires far more often
            // than the screen refreshes, and each read forces a layout.
            if (raf) return;
            raf = requestAnimationFrame(() => {
                update();
                raf = null;
            });
        };

        window.addEventListener('scroll', onScroll, { passive: true });
        window.addEventListener('resize', onScroll, { passive: true });
        update();

        return () => {
            window.removeEventListener('scroll', onScroll);
            window.removeEventListener('resize', onScroll);
            if (raf) cancelAnimationFrame(raf);
        };
    }, [update]);

    return (
        <button
            type="button"
            onClick={() => window.scrollTo({ top: 0, behavior: 'smooth' })}
            aria-label="Scroll to top"
            aria-hidden={!visible}
            className={`bg-brand-teal/90 hover:bg-brand-teal fixed bottom-5 left-5 z-40 flex size-11 items-center justify-center rounded-full text-white shadow-lg backdrop-blur-sm transition-all duration-300 ${
                // `pointer-events-none` as well as invisible, or it would keep
                // swallowing taps in the corner while faded out.
                visible ? 'translate-y-0 opacity-100' : 'pointer-events-none translate-y-4 opacity-0'
            }`}
        >
            <svg className="absolute inset-0 size-full -rotate-90" viewBox="0 0 44 44" aria-hidden>
                <circle cx="22" cy="22" r={RADIUS} fill="none" stroke="rgba(255,255,255,0.18)" strokeWidth="2" />
                <circle
                    cx="22"
                    cy="22"
                    r={RADIUS}
                    fill="none"
                    stroke="#af9056"
                    strokeWidth="2"
                    strokeLinecap="round"
                    strokeDasharray={CIRCUMFERENCE}
                    strokeDashoffset={CIRCUMFERENCE * (1 - progress)}
                    className="transition-[stroke-dashoffset] duration-100"
                />
            </svg>
            <ArrowUp className="relative size-4" />
        </button>
    );
}
