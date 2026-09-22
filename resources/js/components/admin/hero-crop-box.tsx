import { useEffect, useRef, useState } from 'react';

/**
 * A draggable crop window over the WHOLE picture, showing exactly what the
 * storefront will keep.
 *
 * 🔑 Why a box over the full image rather than dragging the image inside a
 * viewport: the client asked to "move a cropping box that shows what will be
 * displayed", and it is the more honest of the two. Dragging the image hides
 * everything outside the frame, so you are choosing what to keep without being
 * able to see what you are giving up. Here the whole picture stays visible and
 * the window says which part survives.
 *
 * 🔑 The geometry is the exact inverse of CSS `object-cover` + `object-position`,
 * so the box is not an approximation of the result — it IS the result:
 *   cover shows a window of the image sized (T/I, 1) when the image is wider
 *   than the target, or (1, I/T) when it is taller;
 *   `object-position: p%` puts that window's edge at (1 - windowFraction) * p%.
 * Dragging inverts the second line to recover p.
 */
export default function HeroCropBox({
    src,
    aspect,
    focal,
    onFocal,
    label,
    hint,
    fitsHint,
}: {
    src: string;
    /** Target shape of the band this art has to fill, width / height. */
    aspect: number;
    focal: { x: number; y: number };
    onFocal: (x: number, y: number) => void;
    label: string;
    hint: string;
    /** Shown instead of the drag hint when the art already fits and nothing is cut. */
    fitsHint: string;
}) {
    const frame = useRef<HTMLDivElement>(null);
    const [natural, setNatural] = useState<{ w: number; h: number } | null>(null);
    const [dragging, setDragging] = useState(false);

    useEffect(() => {
        setNatural(null);
        const img = new Image();
        img.onload = () => setNatural({ w: img.naturalWidth, h: img.naturalHeight });
        img.src = src;
    }, [src]);

    const imageAspect = natural ? natural.w / natural.h : aspect;

    // What fraction of the picture survives the crop, per axis.
    const wf = imageAspect > aspect ? aspect / imageAspect : 1;
    const hf = imageAspect > aspect ? 1 : imageAspect / aspect;

    // Nothing is lost when the shapes already match, so there is nothing to drag.
    const fits = wf > 0.999 && hf > 0.999;

    const move = (clientX: number, clientY: number) => {
        const box = frame.current?.getBoundingClientRect();
        if (!box) return;

        // Where the pointer sits, as a fraction of the picture.
        const px = (clientX - box.left) / box.width;
        const py = (clientY - box.top) / box.height;

        // Treat the pointer as the window's CENTRE, then convert that centre back
        // into the percentage `object-position` wants.
        const left = px - wf / 2;
        const top = py - hf / 2;

        const x = wf >= 1 ? 50 : (left / (1 - wf)) * 100;
        const y = hf >= 1 ? 50 : (top / (1 - hf)) * 100;

        onFocal(Math.round(Math.min(100, Math.max(0, x))), Math.round(Math.min(100, Math.max(0, y))));
    };

    // Pointer events rather than mouse events, so a stylus or a touch drag works
    // too; capture keeps the drag alive when the pointer leaves the frame.
    const onPointerDown = (e: React.PointerEvent<HTMLDivElement>) => {
        if (fits) return;
        e.preventDefault();
        (e.target as HTMLElement).setPointerCapture?.(e.pointerId);
        setDragging(true);
        move(e.clientX, e.clientY);
    };

    const onPointerMove = (e: React.PointerEvent<HTMLDivElement>) => {
        if (!dragging) return;
        move(e.clientX, e.clientY);
    };

    const stop = () => setDragging(false);

    // ⚠️ Keyboard path. A drag handle that only answers to a pointer is not
    // reachable at all for some people, and this is the only control over what a
    // visitor ends up seeing.
    const onKeyDown = (e: React.KeyboardEvent) => {
        const step = e.shiftKey ? 10 : 2;
        const map: Record<string, [number, number]> = {
            ArrowLeft: [-step, 0],
            ArrowRight: [step, 0],
            ArrowUp: [0, -step],
            ArrowDown: [0, step],
        };
        const d = map[e.key];
        if (!d) return;

        e.preventDefault();
        onFocal(Math.min(100, Math.max(0, focal.x + d[0])), Math.min(100, Math.max(0, focal.y + d[1])));
    };

    // The window's position, straight from object-position's own definition.
    const left = wf >= 1 ? 0 : (1 - wf) * (focal.x / 100);
    const top = hf >= 1 ? 0 : (1 - hf) * (focal.y / 100);

    return (
        <div className="min-w-0">
            <p className="mb-1 text-xs text-neutral-400">{label}</p>

            <div
                ref={frame}
                onPointerDown={onPointerDown}
                onPointerMove={onPointerMove}
                onPointerUp={stop}
                onPointerCancel={stop}
                onKeyDown={onKeyDown}
                role={fits ? undefined : 'slider'}
                aria-label={fits ? undefined : hint}
                aria-valuetext={fits ? undefined : `${focal.x}% ${focal.y}%`}
                tabIndex={fits ? undefined : 0}
                className={`relative w-full touch-none overflow-hidden rounded-lg border border-neutral-700 bg-neutral-950 select-none ${
                    fits ? '' : dragging ? 'cursor-grabbing' : 'cursor-grab'
                } focus:ring-brand-gold focus:ring-2 focus:outline-none`}
                style={{ aspectRatio: `${natural ? natural.w : 16} / ${natural ? natural.h : 9}` }}
            >
                {/* The whole picture, dimmed, so what is being given up stays visible. */}
                <img src={src} alt="" draggable={false} className={`h-full w-full object-contain ${fits ? '' : 'opacity-40'}`} />

                {!fits && (
                    <>
                        {/* The surviving window: undimmed, framed, and showing the
                            same slice the storefront will show. */}
                        <div
                            className="pointer-events-none absolute overflow-hidden border-2 border-white shadow-[0_0_0_9999px_rgba(0,0,0,0.35)]"
                            style={{
                                left: `${left * 100}%`,
                                top: `${top * 100}%`,
                                width: `${wf * 100}%`,
                                height: `${hf * 100}%`,
                            }}
                        >
                            <img
                                src={src}
                                alt=""
                                draggable={false}
                                className="absolute h-full w-full max-w-none object-contain"
                                style={{
                                    width: `${(1 / wf) * 100}%`,
                                    height: `${(1 / hf) * 100}%`,
                                    left: `${-(left / wf) * 100}%`,
                                    top: `${-(top / hf) * 100}%`,
                                }}
                            />
                        </div>
                    </>
                )}
            </div>

            {/* ⚠️ A leftover said `fits ? hint : hint` — the same string either
                way — so a picture that needed no cropping still told the client to
                drag a box that was not there. */}
            <p className="mt-1 text-xs text-neutral-500">{fits ? fitsHint : hint}</p>
        </div>
    );
}
