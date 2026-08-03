import * as DialogPrimitive from '@radix-ui/react-dialog';
import { ChevronLeft, ChevronRight, Maximize2, X, ZoomIn, ZoomOut } from 'lucide-react';
import { type Dispatch, type MouseEvent, type SetStateAction, useCallback, useEffect, useState } from 'react';

const MIN_SCALE = 1;
const MAX_SCALE = 4;
const clamp = (n: number, lo: number, hi: number) => Math.min(hi, Math.max(lo, n));

export interface LightboxLabels {
    close: string;
    zoomIn: string;
    zoomOut: string;
    resetZoom: string;
    previous: string;
    next: string;
}

/**
 * Full-screen image viewer: an image stage with zoom (buttons / wheel / click),
 * cursor panning, thumbnail + arrow-key swap between images, and Escape /
 * backdrop / X to close. RTL-aware and language-agnostic — the caller supplies
 * `labels`. Shared by the storefront product gallery and the admin products list.
 */
export default function ImageLightbox({
    open,
    onOpenChange,
    images,
    imagesFull,
    name,
    active,
    setActive,
    labels,
}: {
    open: boolean;
    onOpenChange: (v: boolean) => void;
    /** Detail-size images — the thumbnail strip + stage fallback. */
    images: string[];
    /** Full-size images for the stage. Falls back to `images` per index. */
    imagesFull: string[];
    name: string;
    active: number;
    setActive: Dispatch<SetStateAction<number>>;
    labels: LightboxLabels;
}) {
    const [scale, setScale] = useState(1);
    const [origin, setOrigin] = useState('50% 50%');
    const [broken, setBroken] = useState<Record<number, true>>({});

    const count = imagesFull.length;
    // Prefer the full image; if it fails, fall back to the detail variant.
    const src = broken[active] ? images[active] : (imagesFull[active] ?? images[active]);
    const zoomed = scale > 1;

    const reset = useCallback(() => {
        setScale(1);
        setOrigin('50% 50%');
    }, []);

    const go = useCallback((dir: number) => setActive((i) => (i + dir + count) % count), [count, setActive]);
    const zoom = (delta: number) => setScale((s) => clamp(s + delta, MIN_SCALE, MAX_SCALE));

    // Reset the zoom whenever the shown image changes or the viewer (re)opens.
    useEffect(() => reset(), [active, open, reset]);

    // Keyboard: arrows swap images, +/- zoom (Escape is handled by Radix).
    useEffect(() => {
        if (!open) return;
        const onKey = (e: KeyboardEvent) => {
            if (e.key === 'ArrowRight') go(1);
            else if (e.key === 'ArrowLeft') go(-1);
            else if (e.key === '+' || e.key === '=') zoom(0.5);
            else if (e.key === '-') zoom(-0.5);
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [open, go]);

    const pan = (e: MouseEvent<HTMLElement>) => {
        if (!zoomed) return;
        const r = e.currentTarget.getBoundingClientRect();
        setOrigin(`${((e.clientX - r.left) / r.width) * 100}% ${((e.clientY - r.top) / r.height) * 100}%`);
    };

    const ctrl = 'flex size-10 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20';
    const nav =
        'absolute top-1/2 z-10 flex size-11 -translate-y-1/2 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20';

    return (
        <DialogPrimitive.Root open={open} onOpenChange={onOpenChange}>
            <DialogPrimitive.Portal>
                <DialogPrimitive.Overlay className="data-[state=open]:animate-in data-[state=open]:fade-in-0 fixed inset-0 z-50 bg-black/90" />
                <DialogPrimitive.Content
                    aria-describedby={undefined}
                    onOpenAutoFocus={(e) => e.preventDefault()}
                    className="fixed inset-0 z-50 flex flex-col outline-none"
                >
                    <DialogPrimitive.Title className="sr-only">{name}</DialogPrimitive.Title>

                    {/* Toolbar */}
                    <div className="flex items-center justify-end gap-1 p-3">
                        <button type="button" onClick={() => zoom(-0.5)} aria-label={labels.zoomOut} className={ctrl}>
                            <ZoomOut className="size-5" />
                        </button>
                        <button type="button" onClick={() => zoom(0.5)} aria-label={labels.zoomIn} className={ctrl}>
                            <ZoomIn className="size-5" />
                        </button>
                        <button type="button" onClick={reset} aria-label={labels.resetZoom} className={ctrl}>
                            <Maximize2 className="size-5" />
                        </button>
                        <DialogPrimitive.Close aria-label={labels.close} className={ctrl}>
                            <X className="size-5" />
                        </DialogPrimitive.Close>
                    </div>

                    {/* Image stage — click empty area to close. */}
                    <div
                        className="relative flex flex-1 items-center justify-center overflow-hidden px-4 pb-4"
                        onClick={(e) => {
                            if (e.target === e.currentTarget) onOpenChange(false);
                        }}
                    >
                        {count > 1 && (
                            <button type="button" onClick={() => go(-1)} aria-label={labels.previous} className={`${nav} start-2`}>
                                <ChevronLeft className="size-6 rtl:rotate-180" />
                            </button>
                        )}

                        {broken[active] && !images[active] ? (
                            <div className="text-7xl">🌴</div>
                        ) : (
                            <img
                                src={src}
                                alt={name}
                                draggable={false}
                                onError={() => setBroken((b) => ({ ...b, [active]: true }))}
                                onClick={() => setScale((s) => (s > 1 ? 1 : 2.5))}
                                onMouseMove={pan}
                                onWheel={(e) => zoom(e.deltaY < 0 ? 0.4 : -0.4)}
                                style={{ transform: `scale(${scale})`, transformOrigin: origin }}
                                className={`max-h-full max-w-full object-contain transition-transform duration-100 select-none ${zoomed ? 'cursor-zoom-out' : 'cursor-zoom-in'}`}
                            />
                        )}

                        {count > 1 && (
                            <button type="button" onClick={() => go(1)} aria-label={labels.next} className={`${nav} end-2`}>
                                <ChevronRight className="size-6 rtl:rotate-180" />
                            </button>
                        )}
                    </div>

                    {/* Thumbnail strip */}
                    {count > 1 && (
                        <div className="flex items-center justify-center gap-2 overflow-x-auto p-3">
                            {images.map((url, i) => (
                                <button
                                    key={url}
                                    type="button"
                                    onClick={() => setActive(i)}
                                    aria-label={`${name} ${i + 1}`}
                                    className={`size-14 shrink-0 overflow-hidden rounded-lg border-2 transition ${i === active ? 'border-white' : 'border-white/30 hover:border-white/60'}`}
                                >
                                    <img src={url} alt="" className="size-full object-cover" />
                                </button>
                            ))}
                        </div>
                    )}
                </DialogPrimitive.Content>
            </DialogPrimitive.Portal>
        </DialogPrimitive.Root>
    );
}
