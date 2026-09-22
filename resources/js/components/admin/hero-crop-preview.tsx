import { useEffect, useState } from 'react';

/** The band the storefront hero renders art into, on each device. */
const DESKTOP = { label: '2:1', ratio: '2 / 1', w: 1920, h: 960 };
const PHONE = { label: '4:5', ratio: '4 / 5', w: 1080, h: 1350 };

/**
 * Shows the client EXACTLY how their artwork will be cropped, and lets them say
 * which part must survive it.
 *
 * 🔴 Why it exists: the hero is a fixed 2:1 band with `object-cover`, so anything
 * that is not 2:1 is cut — centred, which is the wrong guess for most photographs.
 * The client uploaded an image, saw it cropped, and had no way to know it would be
 * or to do anything about it. Both halves of that are fixed here: the crop is
 * visible before saving, and clicking sets the focal point.
 *
 * ⚠️ The phone panel uses the DESKTOP art on purpose when no phone art is given,
 * because that is precisely what the storefront does — showing the uncropped
 * original there would be a comforting lie.
 */
export default function HeroCropPreview({
    src,
    focal,
    onFocal,
    t,
    phoneSrc,
}: {
    /** Object URL of a freshly chosen file, or the stored URL of existing art. */
    src: string | null;
    focal: { x: number; y: number };
    onFocal: (x: number, y: number) => void;
    t: (key: string, opts?: Record<string, unknown>) => string;
    phoneSrc?: string | null;
}) {
    const [natural, setNatural] = useState<{ w: number; h: number } | null>(null);

    useEffect(() => {
        setNatural(null);
        if (!src) return;

        const img = new Image();
        img.onload = () => setNatural({ w: img.naturalWidth, h: img.naturalHeight });
        img.src = src;
    }, [src]);

    if (!src) {
        return (
            <p className="rounded-lg border border-dashed border-neutral-700 px-4 py-6 text-center text-xs text-neutral-500">
                {t('admin.hero.cropEmpty')}
            </p>
        );
    }

    // How far the chosen picture is from the band it has to fill. Anything
    // materially off 2:1 will visibly lose its top and bottom, or its sides.
    const ratio = natural ? natural.w / natural.h : null;
    const off = ratio !== null && Math.abs(ratio - 2) > 0.25;

    const pick = (e: React.MouseEvent<HTMLDivElement>) => {
        const r = e.currentTarget.getBoundingClientRect();
        const x = Math.round(((e.clientX - r.left) / r.width) * 100);
        const y = Math.round(((e.clientY - r.top) / r.height) * 100);
        onFocal(Math.min(100, Math.max(0, x)), Math.min(100, Math.max(0, y)));
    };

    const panel = (band: typeof DESKTOP, source: string, clickable: boolean) => (
        <div className="min-w-0 flex-1">
            <p className="mb-1 text-xs text-neutral-400">{t(clickable ? 'admin.hero.cropDesktop' : 'admin.hero.cropPhone')}</p>
            <div
                onClick={clickable ? pick : undefined}
                role={clickable ? 'button' : undefined}
                tabIndex={clickable ? 0 : undefined}
                aria-label={clickable ? t('admin.hero.focalHint') : undefined}
                className={`relative overflow-hidden rounded-lg border border-neutral-700 bg-neutral-950 ${clickable ? 'cursor-crosshair' : ''}`}
                style={{ aspectRatio: band.ratio }}
            >
                <img src={source} alt="" className="h-full w-full object-cover" style={{ objectPosition: `${focal.x}% ${focal.y}%` }} />
                {clickable && (
                    // The marker sits where the crop will centre. Pointer-events off
                    // so it can never swallow the click that moves it.
                    <span
                        className="pointer-events-none absolute z-10 h-5 w-5 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white shadow-[0_0_0_2px_rgba(0,0,0,0.5)]"
                        style={{ left: `${focal.x}%`, top: `${focal.y}%` }}
                    />
                )}
            </div>
        </div>
    );

    return (
        <div className="rounded-lg border border-neutral-800 bg-neutral-950/60 p-3">
            <div className="flex gap-3">
                {panel(DESKTOP, src, true)}
                <div className="w-24 shrink-0 sm:w-28">{panel(PHONE, phoneSrc || src, false)}</div>
            </div>

            <p className="mt-2 text-xs text-neutral-500">{t('admin.hero.focalHint')}</p>

            <div className="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
                <span className="text-neutral-500">
                    {t('admin.hero.recommended', { w: DESKTOP.w, h: DESKTOP.h })}
                    {phoneSrc ? '' : ` · ${t('admin.hero.recommendedPhone', { w: PHONE.w, h: PHONE.h })}`}
                </span>
                {natural && (
                    <span className={off ? 'text-amber-400' : 'text-neutral-500'}>
                        {t('admin.hero.yours', { w: natural.w, h: natural.h })}
                        {off ? ` — ${t('admin.hero.willCrop')}` : ''}
                    </span>
                )}
            </div>
        </div>
    );
}
