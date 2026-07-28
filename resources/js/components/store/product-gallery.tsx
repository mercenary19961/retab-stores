import * as DialogPrimitive from '@radix-ui/react-dialog';
import { ChevronLeft, ChevronRight, Maximize2, X, ZoomIn, ZoomOut } from 'lucide-react';
import { type Dispatch, type MouseEvent, type SetStateAction, useCallback, useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

interface Props {
    /** Detail-size WebP variants — used for the gallery + hover-magnify + thumbnails. */
    images: string[];
    /** Full-size originals — used only inside the zoom viewer. Falls back to `images`. */
    imagesFull: string[];
    name: string;
}

const MIN_SCALE = 1;
const MAX_SCALE = 4;
const clamp = (n: number, lo: number, hi: number) => Math.min(hi, Math.max(lo, n));

/**
 * Product image gallery: thumbnails + a main image that magnifies on hover
 * (desktop pointer) and, on click, opens a full-screen viewer showing the
 * original full-resolution photo with zoom (buttons / wheel / click), cursor
 * panning, and swap between the product's other images. RTL-aware.
 */
export default function ProductGallery({ images, imagesFull, name }: Props) {
    const { t } = useTranslation();
    const [active, setActive] = useState(0);
    const [open, setOpen] = useState(false);
    // Fall back to the 🌴 placeholder when an image URL fails to load.
    const [broken, setBroken] = useState<Record<string, true>>({});
    const markBroken = (url: string) => setBroken((b) => ({ ...b, [url]: true }));

    // Hover-magnify state for the main image.
    const [hovering, setHovering] = useState(false);
    const [origin, setOrigin] = useState('50% 50%');
    const track = (e: MouseEvent<HTMLElement>) => {
        const r = e.currentTarget.getBoundingClientRect();
        setOrigin(`${((e.clientX - r.left) / r.width) * 100}% ${((e.clientY - r.top) / r.height) * 100}%`);
    };

    const has = images.length > 0;
    const current = images[active];

    return (
        <div className="space-y-3">
            {/* Main image — hover to magnify, click to open the full-size viewer. */}
            <div className="overflow-hidden rounded-2xl border border-brand-gold/15 bg-white shadow-sm">
                {has && !broken[current] ? (
                    <button
                        type="button"
                        onClick={() => setOpen(true)}
                        onMouseEnter={() => setHovering(true)}
                        onMouseLeave={() => {
                            setHovering(false);
                            setOrigin('50% 50%');
                        }}
                        onMouseMove={track}
                        aria-label={t('product.openFullImage')}
                        className="block w-full cursor-zoom-in"
                    >
                        <img
                            src={current}
                            alt={name}
                            loading="eager"
                            onError={() => markBroken(current)}
                            style={{ transform: hovering ? 'scale(1.8)' : 'scale(1)', transformOrigin: origin }}
                            className="aspect-square w-full object-cover transition-transform duration-200 ease-out"
                        />
                    </button>
                ) : (
                    <div className="flex aspect-square items-center justify-center bg-brand-cream text-7xl">🌴</div>
                )}
            </div>

            {/* Thumbnails — select the main image. */}
            {images.length > 1 && (
                <div className="grid grid-cols-5 gap-2">
                    {images.slice(0, 5).map((url, i) => (
                        <button
                            key={url}
                            type="button"
                            onClick={() => setActive(i)}
                            aria-label={`${name} ${i + 1}`}
                            className={`overflow-hidden rounded-lg border-2 transition ${i === active ? 'border-brand-teal' : 'border-transparent hover:border-brand-gold/40'}`}
                        >
                            {broken[url] ? (
                                <div className="flex aspect-square w-full items-center justify-center bg-brand-cream text-lg">🌴</div>
                            ) : (
                                <img src={url} alt="" loading="lazy" onError={() => markBroken(url)} className="aspect-square w-full object-cover" />
                            )}
                        </button>
                    ))}
                </div>
            )}

            {has && (
                <Lightbox open={open} onOpenChange={setOpen} images={images} imagesFull={imagesFull} name={name} active={active} setActive={setActive} />
            )}
        </div>
    );
}

function Lightbox({
    open,
    onOpenChange,
    images,
    imagesFull,
    name,
    active,
    setActive,
}: {
    open: boolean;
    onOpenChange: (v: boolean) => void;
    images: string[];
    imagesFull: string[];
    name: string;
    active: number;
    setActive: Dispatch<SetStateAction<number>>;
}) {
    const { t } = useTranslation();
    const [scale, setScale] = useState(1);
    const [origin, setOrigin] = useState('50% 50%');
    const [broken, setBroken] = useState<Record<number, true>>({});

    const count = imagesFull.length;
    // Prefer the original; if it fails, fall back to the detail variant.
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
    const nav = 'absolute top-1/2 z-10 flex size-11 -translate-y-1/2 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20';

    return (
        <DialogPrimitive.Root open={open} onOpenChange={onOpenChange}>
            <DialogPrimitive.Portal>
                <DialogPrimitive.Overlay className="fixed inset-0 z-50 bg-black/90 data-[state=open]:animate-in data-[state=open]:fade-in-0" />
                <DialogPrimitive.Content
                    aria-describedby={undefined}
                    onOpenAutoFocus={(e) => e.preventDefault()}
                    className="fixed inset-0 z-50 flex flex-col outline-none"
                >
                    <DialogPrimitive.Title className="sr-only">{name}</DialogPrimitive.Title>

                    {/* Toolbar */}
                    <div className="flex items-center justify-end gap-1 p-3">
                        <button type="button" onClick={() => zoom(-0.5)} aria-label={t('product.zoomOut')} className={ctrl}>
                            <ZoomOut className="size-5" />
                        </button>
                        <button type="button" onClick={() => zoom(0.5)} aria-label={t('product.zoomIn')} className={ctrl}>
                            <ZoomIn className="size-5" />
                        </button>
                        <button type="button" onClick={reset} aria-label={t('product.resetZoom')} className={ctrl}>
                            <Maximize2 className="size-5" />
                        </button>
                        <DialogPrimitive.Close aria-label={t('product.closeViewer')} className={ctrl}>
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
                            <button type="button" onClick={() => go(-1)} aria-label={t('product.previousImage')} className={`${nav} start-2`}>
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
                                className={`max-h-full max-w-full select-none object-contain transition-transform duration-100 ${zoomed ? 'cursor-zoom-out' : 'cursor-zoom-in'}`}
                            />
                        )}

                        {count > 1 && (
                            <button type="button" onClick={() => go(1)} aria-label={t('product.nextImage')} className={`${nav} end-2`}>
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
