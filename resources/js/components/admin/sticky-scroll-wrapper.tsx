import { useCallback, useEffect, useRef, useState, type ReactNode } from 'react';

/**
 * Wraps a wide table's horizontal scroll and mirrors it into a thin scrollbar
 * pinned to the bottom of the viewport, so you can scroll a tall+wide table
 * sideways without first scrolling to its bottom. The floating bar auto-hides
 * once the table's real bottom (and its native scrollbar) comes into view.
 */
export default function StickyScrollWrapper({ children, className }: { children: ReactNode; className?: string }) {
    const contentRef = useRef<HTMLDivElement>(null);
    const barRef = useRef<HTMLDivElement>(null);
    const sentinelRef = useRef<HTMLDivElement>(null);
    const syncing = useRef(false);

    const [overflow, setOverflow] = useState(false);
    const [bottomVisible, setBottomVisible] = useState(false);
    const [contentWidth, setContentWidth] = useState(0);
    const [pos, setPos] = useState({ left: 0, width: 0 });

    const update = useCallback(() => {
        const el = contentRef.current;
        if (!el) return;
        setOverflow(el.scrollWidth > el.clientWidth + 1);
        setContentWidth(el.scrollWidth);
        const rect = el.getBoundingClientRect();
        setPos({ left: rect.left, width: rect.width });
    }, []);

    const onContentScroll = useCallback(() => {
        if (syncing.current || !barRef.current || !contentRef.current) return;
        syncing.current = true;
        barRef.current.scrollLeft = contentRef.current.scrollLeft;
        requestAnimationFrame(() => (syncing.current = false));
    }, []);

    const onBarScroll = useCallback(() => {
        if (syncing.current || !barRef.current || !contentRef.current) return;
        syncing.current = true;
        contentRef.current.scrollLeft = barRef.current.scrollLeft;
        requestAnimationFrame(() => (syncing.current = false));
    }, []);

    useEffect(() => {
        const el = contentRef.current;
        const sentinel = sentinelRef.current;
        if (!el) return;

        update();
        el.addEventListener('scroll', onContentScroll);
        window.addEventListener('resize', update);
        const ro = new ResizeObserver(update);
        ro.observe(el);

        let io: IntersectionObserver | null = null;
        if (sentinel) {
            io = new IntersectionObserver((e) => setBottomVisible(e[0]?.isIntersecting ?? false), { threshold: 0 });
            io.observe(sentinel);
        }

        return () => {
            el.removeEventListener('scroll', onContentScroll);
            window.removeEventListener('resize', update);
            ro.disconnect();
            io?.disconnect();
        };
    }, [update, onContentScroll]);

    const show = overflow && !bottomVisible;

    // Align the freshly-shown bar with the content's current horizontal scroll.
    useEffect(() => {
        if (show && barRef.current && contentRef.current) {
            barRef.current.scrollLeft = contentRef.current.scrollLeft;
        }
    }, [show]);

    return (
        <div className="relative">
            <div ref={contentRef} className={`overflow-x-auto ${className ?? ''}`}>
                {children}
            </div>

            {/* Detects when the table's own bottom scrollbar scrolls into view. */}
            <div ref={sentinelRef} className="h-px w-full" />

            {show && (
                <div
                    ref={barRef}
                    onScroll={onBarScroll}
                    className="fixed bottom-0 z-40 overflow-x-auto overflow-y-hidden border-t border-neutral-700 bg-neutral-900/95"
                    style={{ left: pos.left, width: pos.width, height: 16 }}
                >
                    <div style={{ width: contentWidth, height: 1 }} />
                </div>
            )}
        </div>
    );
}
