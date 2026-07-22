import { useEffect, useRef, useState } from 'react';

/**
 * Square product image with a branded placeholder (white + faint Retab logo).
 * The placeholder sits PERMANENTLY behind the photo, so while the photo is still
 * downloading — including after a filter/sort swaps this node's src, when `loaded`
 * can briefly lag the real pixels — the card shows the logo instead of a blank
 * white square. The photo simply fades in over the placeholder once its pixels
 * arrive, covering it. The same placeholder covers the genuine no-image case, so
 * every card looks uniform and nothing ever "pops" into empty space.
 *
 * The logo is already loaded by the navbar, so the placeholder paints instantly.
 * `loading="lazy"` keeps offscreen photos off the initial load.
 */
export default function ProductImage({ src, alt }: { src: string | null; alt: string }) {
    const [loaded, setLoaded] = useState(false);
    const ref = useRef<HTMLImageElement>(null);

    // A cached photo can finish before React binds onLoad — catch it on mount so
    // the placeholder doesn't stay stuck over an already-painted image.
    useEffect(() => {
        if (ref.current?.complete && ref.current.naturalWidth > 0) {
            setLoaded(true);
        }
    }, []);

    return (
        <div className="relative aspect-square w-full overflow-hidden rounded-[23%] bg-white shadow-sm transition-shadow group-hover:shadow-md">
            <div
                aria-hidden
                className="absolute inset-0 flex items-center justify-center bg-white"
            >
                <img src="/images/brand/logo.png" alt="" className="w-1/2 max-w-[7rem] opacity-20" />
            </div>

            {src && (
                <img
                    ref={ref}
                    src={src}
                    alt={alt}
                    loading="lazy"
                    decoding="async"
                    onLoad={() => setLoaded(true)}
                    className={`relative h-full w-full object-cover transition-opacity duration-500 ${
                        loaded ? 'opacity-100' : 'opacity-0'
                    }`}
                />
            )}
        </div>
    );
}
