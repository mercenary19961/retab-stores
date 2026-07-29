import { type MouseEvent, useState } from 'react';
import { useTranslation } from 'react-i18next';
import ImageLightbox from '@/components/image-lightbox';

interface Props {
    /** Detail-size WebP variants — the main image + hover-magnify. */
    images: string[];
    /** Card-size WebP variants — the small thumbnail strip (a browser downscale of
     *  the 1400px detail into ~100px aliases badly on detailed packaging). */
    imagesThumb: string[];
    /** Full-size originals — used only inside the zoom viewer. Falls back to `images`. */
    imagesFull: string[];
    name: string;
}

/**
 * Product image gallery: thumbnails + a main image that magnifies on hover
 * (desktop pointer) and, on click, opens the shared full-screen zoom viewer
 * showing the original full-resolution photo. RTL-aware.
 */
export default function ProductGallery({ images, imagesThumb, imagesFull, name }: Props) {
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

            {/* Thumbnails — select the main image. Card-size (not the 1400px detail). */}
            {imagesThumb.length > 1 && (
                <div className="grid grid-cols-5 gap-2">
                    {imagesThumb.slice(0, 5).map((url, i) => (
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
                <ImageLightbox
                    open={open}
                    onOpenChange={setOpen}
                    images={images}
                    imagesFull={imagesFull}
                    name={name}
                    active={active}
                    setActive={setActive}
                    labels={{
                        close: t('product.closeViewer'),
                        zoomIn: t('product.zoomIn'),
                        zoomOut: t('product.zoomOut'),
                        resetZoom: t('product.resetZoom'),
                        previous: t('product.previousImage'),
                        next: t('product.nextImage'),
                    }}
                />
            )}
        </div>
    );
}
