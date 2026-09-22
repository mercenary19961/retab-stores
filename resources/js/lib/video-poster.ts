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
 * Best-effort by design: any failure resolves to null and the caller carries on
 * without a poster, exactly as before. A missing poster must never block a save.
 */
export async function posterFromVideo(file: File, seconds = 0.1): Promise<File | null> {
    if (typeof document === 'undefined') return null;

    const url = URL.createObjectURL(file);

    try {
        const video = document.createElement('video');
        video.preload = 'auto';
        // Muted + inline, or some browsers refuse to decode without a gesture.
        video.muted = true;
        video.playsInline = true;
        video.src = url;

        const frame = await new Promise<Blob | null>((resolve) => {
            // ⚠️ A hard ceiling: a corrupt or unsupported file can leave every
            // event unfired, and without this the save button would hang forever
            // on a promise that never settles.
            const bail = window.setTimeout(() => resolve(null), 8000);

            const done = (blob: Blob | null) => {
                window.clearTimeout(bail);
                resolve(blob);
            };

            video.onerror = () => done(null);

            video.onloadeddata = () => {
                // Seek a fraction in: frame 0 of a fade-in is often pure black,
                // which would make the poster look like the bug it is fixing.
                video.currentTime = Math.min(seconds, video.duration || seconds);
            };

            video.onseeked = () => {
                try {
                    const canvas = document.createElement('canvas');
                    canvas.width = video.videoWidth;
                    canvas.height = video.videoHeight;
                    if (!canvas.width || !canvas.height) return done(null);

                    const ctx = canvas.getContext('2d');
                    if (!ctx) return done(null);

                    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                    canvas.toBlob((blob) => done(blob), 'image/jpeg', 0.82);
                } catch {
                    done(null);
                }
            };
        });

        if (!frame) return null;

        return new File([frame], 'poster.jpg', { type: 'image/jpeg' });
    } catch {
        return null;
    } finally {
        URL.revokeObjectURL(url);
    }
}
