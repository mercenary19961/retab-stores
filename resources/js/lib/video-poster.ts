/**
 * Grab the first frame of a chosen video file as a JPEG, in the browser.
 *
 * 🔴 WHY THIS EXISTS: a <video> with no poster paints NOTHING until enough of the
 * file has buffered, so a posterless hero video is a flat block of background
 * colour for however long the download takes. The client hit exactly that and
 * reported the video as "not showing"; it was playing fine, just invisible.
 *
 * Making the poster a required upload would have been the lazy fix. The file is
 * already on their machine at that moment, so we can take the frame ourselves and
 * they never have to think about it.
 *
 * 🔑 It reports WHY it failed, and the caller needs that distinction:
 *   - `decode` / `error` / `timeout` with no dimensions means this browser cannot
 *     play the file at all, so the crop editor must not try to render it either;
 *   - `canvas` means the video is fine and only the still could not be taken.
 * Collapsing those into a bare null is what left the client staring at two black
 * boxes with no way to tell a broken upload from a browser limitation.
 */

export interface PosterResult {
    /** The captured still, or null if one could not be taken. */
    poster: File | null;
    /** null on success, otherwise a short machine-readable cause. */
    reason: 'error' | 'timeout' | 'canvas' | null;
    /** 0 when metadata never arrived, i.e. the browser could not open the file. */
    width: number;
    height: number;
    /** The element's own MediaError code, when it reported one. */
    mediaError: number | null;
}

export async function posterFromVideo(file: File, seconds = 0.1): Promise<PosterResult> {
    const fail = (reason: PosterResult['reason'], width = 0, height = 0, mediaError: number | null = null): PosterResult => ({
        poster: null,
        reason,
        width,
        height,
        mediaError,
    });

    if (typeof document === 'undefined') return fail('error');

    const url = URL.createObjectURL(file);

    try {
        const video = document.createElement('video');
        video.preload = 'auto';
        // Muted + inline, or some browsers refuse to decode without a gesture.
        video.muted = true;
        video.playsInline = true;
        video.src = url;

        const outcome = await new Promise<PosterResult>((resolve) => {
            // ⚠️ A hard ceiling: a corrupt or unsupported file can leave every
            // event unfired, and without this the save button would hang forever
            // on a promise that never settles.
            const bail = window.setTimeout(() => resolve(fail('timeout', video.videoWidth, video.videoHeight, video.error?.code ?? null)), 8000);

            const done = (result: PosterResult) => {
                window.clearTimeout(bail);
                resolve(result);
            };

            video.onerror = () => done(fail('error', video.videoWidth, video.videoHeight, video.error?.code ?? null));

            video.onloadeddata = () => {
                // Seek a fraction in: frame 0 of a fade-in is often pure black,
                // which would make the poster look like the bug it is fixing.
                video.currentTime = Math.min(seconds, video.duration || seconds);
            };

            video.onseeked = () => {
                const w = video.videoWidth;
                const h = video.videoHeight;

                try {
                    const canvas = document.createElement('canvas');
                    canvas.width = w;
                    canvas.height = h;
                    if (!w || !h) return done(fail('canvas', w, h));

                    const ctx = canvas.getContext('2d');
                    if (!ctx) return done(fail('canvas', w, h));

                    ctx.drawImage(video, 0, 0, w, h);
                    canvas.toBlob(
                        (blob) =>
                            done(
                                blob
                                    ? {
                                          poster: new File([blob], 'poster.jpg', { type: 'image/jpeg' }),
                                          reason: null,
                                          width: w,
                                          height: h,
                                          mediaError: null,
                                      }
                                    : fail('canvas', w, h),
                            ),
                        'image/jpeg',
                        0.82,
                    );
                } catch {
                    done(fail('canvas', w, h));
                }
            };
        });

        return outcome;
    } catch {
        return fail('error');
    } finally {
        URL.revokeObjectURL(url);
    }
}
