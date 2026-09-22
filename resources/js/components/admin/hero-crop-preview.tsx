import { useEffect, useState } from 'react';

import HeroCropBox from './hero-crop-box';

/** The bands the storefront hero renders art into, and the sizes that fit them exactly. */
const DESKTOP = { aspect: 2, w: 1920, h: 960 };
const PHONE = { aspect: 4 / 5, w: 1080, h: 1350 };

/**
 * Shows the client EXACTLY what the storefront will keep from their artwork, on
 * both surfaces, and lets them drag the crop window to choose.
 *
 * 🔴 The bug this replaces: the phone panel took a `phoneSrc` prop that the page
 * NEVER PASSED, so it silently fell back to the desktop file. A client who
 * uploaded phone art saw it ignored and reasonably concluded the upload was
 * broken. The panels are now fed explicitly and independently.
 *
 * 🔑 Two files means TWO focal points. A point chosen on a wide desktop banner
 * says nothing about where a 9:16 social export should sit, so each surface
 * carries its own — which is also why the phone panel is a full editor rather
 * than a thumbnail.
 */
export default function HeroCropPreview({
    src,
    phoneSrc,
    focal,
    onFocal,
    focalMobile,
    onFocalMobile,
    t,
}: {
    /** Object URL of a freshly chosen file, or the stored URL of existing art. */
    src: string | null;
    phoneSrc: string | null;
    focal: { x: number; y: number };
    onFocal: (x: number, y: number) => void;
    focalMobile: { x: number; y: number };
    onFocalMobile: (x: number, y: number) => void;
    t: (key: string, opts?: Record<string, unknown>) => string;
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

    // How far the desktop art is from the band it has to fill.
    const ratio = natural ? natural.w / natural.h : null;
    const off = ratio !== null && Math.abs(ratio - DESKTOP.aspect) > 0.25;

    return (
        <div className="rounded-lg border border-neutral-800 bg-neutral-950/60 p-3">
            <div className="grid gap-4 sm:grid-cols-[1fr_auto]">
                <HeroCropBox
                    src={src}
                    aspect={DESKTOP.aspect}
                    focal={focal}
                    onFocal={onFocal}
                    label={t('admin.hero.cropDesktop')}
                    hint={t('admin.hero.cropDrag')}
                    fitsHint={t('admin.hero.cropFits')}
                />

                <div className="w-full sm:w-44">
                    {/*
                     * ⚠️ Falls back to the DESKTOP file when there is no phone art,
                     * because that is exactly what the storefront does. Showing
                     * something prettier here would be a comforting lie.
                     */}
                    <HeroCropBox
                        src={phoneSrc || src}
                        aspect={PHONE.aspect}
                        focal={phoneSrc ? focalMobile : focal}
                        onFocal={phoneSrc ? onFocalMobile : onFocal}
                        label={phoneSrc ? t('admin.hero.cropPhone') : t('admin.hero.cropPhoneShared')}
                        hint={t('admin.hero.cropDrag')}
                        fitsHint={t('admin.hero.cropFits')}
                    />
                </div>
            </div>

            <div className="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
                <span className="text-neutral-500">{t('admin.hero.recommended', { w: DESKTOP.w, h: DESKTOP.h })}</span>
                {natural && (
                    <span className={off ? 'text-amber-400' : 'text-neutral-500'}>
                        {t('admin.hero.yours', { w: natural.w, h: natural.h })}
                        {/* Says WHAT gets trimmed, not merely that something will. */}
                        {off ? ` — ${t(ratio! < DESKTOP.aspect ? 'admin.hero.trimsSides' : 'admin.hero.trimsEdges')}` : ''}
                    </span>
                )}
                {!phoneSrc && <span className="text-neutral-500">{t('admin.hero.recommendedPhone', { w: PHONE.w, h: PHONE.h })}</span>}
            </div>
        </div>
    );
}
