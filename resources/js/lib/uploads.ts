/**
 * Background uploads, so a large file does not pin the admin to one screen.
 *
 * 🔑 WHY THIS EXISTS RATHER THAN `router.post`: Inertia's form post owns the page
 * until it resolves, so a 9 MB video meant staring at a dialog for the length of
 * the upload. Client's words: "make the uploading work in parallel, and make the
 * admin roam freely in the admin panel instead of him waiting."
 *
 * 🔑 THE STATE LIVES AT MODULE SCOPE, NOT IN REACT, and that is the whole trick.
 * Inertia remounts the page (and the layout) on every navigation, so an upload
 * tracked in component state would be forgotten the moment the admin moved. Here
 * the request and its progress outlive any component; the tray simply subscribes
 * to whatever is currently in flight.
 *
 * ⚠️ XHR, not `fetch`: only XHR reports upload progress. `fetch` cannot say how
 * far a request body has got, so a percentage would be a lie.
 *
 * ⚠️ An SPA navigation keeps the request alive; a FULL page load (F5, closing the
 * tab, following an external link) kills it. `guardUnload` warns about exactly
 * that case and nothing else.
 */

export type UploadState = 'uploading' | 'done' | 'failed';

export interface Upload {
    id: number;
    /** What is being uploaded, in the admin's own words. */
    label: string;
    /** 0-100. Stays at 100 while the server is still processing the body. */
    progress: number;
    state: UploadState;
    /** Set when it failed, already human-readable. */
    error?: string;
}

let seq = 0;
let uploads: Upload[] = [];
const listeners = new Set<(list: Upload[]) => void>();

function emit(): void {
    // A fresh array each time, or a subscriber comparing by reference sees nothing.
    const snapshot = [...uploads];
    listeners.forEach((fn) => fn(snapshot));
}

export function subscribe(fn: (list: Upload[]) => void): () => void {
    listeners.add(fn);
    fn([...uploads]);

    return () => listeners.delete(fn);
}

export function dismissUpload(id: number): void {
    uploads = uploads.filter((u) => u.id !== id);
    emit();
}

function patch(id: number, changes: Partial<Upload>): void {
    uploads = uploads.map((u) => (u.id === id ? { ...u, ...changes } : u));
    emit();
}

/** Anything still on the wire, so callers can warn before a real page unload. */
export function hasActiveUploads(): boolean {
    return uploads.some((u) => u.state === 'uploading');
}

/**
 * Laravel answers a failed validation with 422 and `{ message, errors: {field: [...]} }`
 * when the request asks for JSON. The first field message is far more useful than
 * the generic "The given data was invalid."
 */
function readError(xhr: XMLHttpRequest, fallback: string): string {
    try {
        const body = JSON.parse(xhr.responseText) as { message?: string; errors?: Record<string, string[]> };
        const first = body.errors ? Object.values(body.errors)[0]?.[0] : null;

        return first ?? body.message ?? fallback;
    } catch {
        return fallback;
    }
}

export function startUpload({
    url,
    body,
    label,
    onSuccess,
    onFailure,
    fallbackError = 'Upload failed.',
}: {
    url: string;
    body: FormData;
    label: string;
    onSuccess?: () => void;
    /** Called with a human-readable reason; the caller decides what to keep. */
    onFailure?: (message: string) => void;
    fallbackError?: string;
}): number {
    const id = ++seq;
    uploads = [...uploads, { id, label, progress: 0, state: 'uploading' }];
    emit();

    const xhr = new XMLHttpRequest();
    xhr.open('POST', url, true);
    // ⚠️ Ask for JSON, or Laravel answers a validation failure with a 302 redirect
    // and the real errors are never seen. `X-Requested-With` is what makes
    // `expectsJson()` true for a form post.
    xhr.setRequestHeader('Accept', 'application/json');
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    // ⚠️ This app has NO <meta name="csrf-token">; the token is the XSRF-TOKEN
    // cookie (same as LanguageContext). A missing token 419s silently.
    xhr.setRequestHeader('X-XSRF-TOKEN', decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? ''));
    xhr.withCredentials = true;

    xhr.upload.onprogress = (e) => {
        if (!e.lengthComputable) return;
        // Capped at 99 while bytes are still moving: 100 should mean the SERVER is
        // done, not that we finished sending. Otherwise it sits at 100% for the
        // whole of a slow store-and-process and looks stuck.
        patch(id, { progress: Math.min(99, Math.round((e.loaded / e.total) * 100)) });
    };

    xhr.onload = () => {
        if (xhr.status >= 200 && xhr.status < 300) {
            patch(id, { progress: 100, state: 'done' });
            onSuccess?.();
            // Tidy itself away; a finished upload is not news for long.
            window.setTimeout(() => dismissUpload(id), 4000);

            return;
        }

        const message = readError(xhr, fallbackError);
        patch(id, { state: 'failed', error: message });
        onFailure?.(message);
    };

    xhr.onerror = () => {
        patch(id, { state: 'failed', error: fallbackError });
        onFailure?.(fallbackError);
    };

    xhr.send(body);

    return id;
}

/**
 * Warn before a real page unload while something is still uploading.
 *
 * ⚠️ Deliberately NOT wired to Inertia navigation: an SPA visit does not unload
 * the document, so the request survives it. Warning there would be a lie and
 * would defeat the entire point of uploading in the background.
 */
export function guardUnload(): () => void {
    const handler = (e: BeforeUnloadEvent) => {
        if (!hasActiveUploads()) return;
        e.preventDefault();
        e.returnValue = '';
    };

    window.addEventListener('beforeunload', handler);

    return () => window.removeEventListener('beforeunload', handler);
}
