import { ArrowDown, ArrowUp } from 'lucide-react';
import { useEffect, useRef, useState, type RefObject } from 'react';
import { useTranslation } from 'react-i18next';

const LINGER_MS = 4000;

/**
 * Mobile-only floating scroll-to-top / scroll-to-bottom buttons for the admin.
 * They appear while the admin is scrolling the main content and linger for 4s
 * after scrolling stops, then fade out. Hidden on md+ (desktop has the wheel /
 * keyboard). The scroll container is the layout's <main>, not the window, so the
 * listener + scrollTo target that element. The button at the current edge (very
 * top / very bottom) is disabled since it would be a no-op.
 */
export default function MobileScrollNav({ scrollRef }: { scrollRef: RefObject<HTMLElement | null> }) {
    const { t } = useTranslation();
    const [visible, setVisible] = useState(false);
    const [atTop, setAtTop] = useState(true);
    const [atBottom, setAtBottom] = useState(false);
    const hideTimer = useRef<number | null>(null);

    useEffect(() => {
        const el = scrollRef.current;
        if (!el) return;

        const update = () => {
            setAtTop(el.scrollTop <= 4);
            setAtBottom(el.scrollTop + el.clientHeight >= el.scrollHeight - 4);
        };

        const onScroll = () => {
            update();
            setVisible(true);
            if (hideTimer.current) window.clearTimeout(hideTimer.current);
            hideTimer.current = window.setTimeout(() => setVisible(false), LINGER_MS);
        };

        update();
        el.addEventListener('scroll', onScroll, { passive: true });
        return () => {
            el.removeEventListener('scroll', onScroll);
            if (hideTimer.current) window.clearTimeout(hideTimer.current);
        };
    }, [scrollRef]);

    const scrollToY = (top: number) => scrollRef.current?.scrollTo({ top, behavior: 'smooth' });

    const btn =
        'flex size-11 items-center justify-center rounded-full bg-neutral-800/90 text-neutral-100 shadow-lg ring-1 ring-white/10 backdrop-blur transition-colors hover:bg-neutral-700 disabled:pointer-events-none disabled:opacity-30';

    return (
        <div
            className={`fixed start-4 bottom-4 z-20 flex flex-col gap-2 transition-opacity duration-300 md:hidden ${
                visible ? 'opacity-100' : 'pointer-events-none opacity-0'
            }`}
        >
            <button type="button" onClick={() => scrollToY(0)} disabled={atTop} aria-label={t('admin.common.scrollToTop')} className={btn}>
                <ArrowUp className="h-5 w-5" />
            </button>
            <button
                type="button"
                onClick={() => scrollToY(scrollRef.current?.scrollHeight ?? 0)}
                disabled={atBottom}
                aria-label={t('admin.common.scrollToBottom')}
                className={btn}
            >
                <ArrowDown className="h-5 w-5" />
            </button>
        </div>
    );
}
