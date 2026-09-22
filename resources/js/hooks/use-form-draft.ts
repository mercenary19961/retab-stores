import { useEffect, useRef, useState } from 'react';

/**
 * Keeps what someone has typed, so an accidental refresh does not throw it away.
 *
 * Client-reported: an editor filling in a banner or a product refreshed by
 * mistake and lost everything. The form state lives only in React, so a reload
 * is a full reset with no warning and nothing to recover.
 *
 * 🔴 FILES CANNOT BE SAVED, and that limitation is the important thing to be
 * honest about. A `File` is a handle to something the browser is holding, not
 * data we are allowed to serialise, so an uploaded picture or video is gone on
 * refresh no matter what. Everything typed — names, links, dates, order, the
 * crop points — comes back, and the caller tells the user to re-attach the file.
 * Silently restoring a form that LOOKS complete but has lost its artwork would be
 * worse than not restoring at all.
 *
 * ⚠️ Every read and write is wrapped: localStorage throws in a private window and
 * can be disabled outright. A draft is a convenience, so it fails toward "no
 * draft" and never toward a broken form.
 */

/** Drafts older than this are ignored. A week-old draft is confusing, not helpful. */
const MAX_AGE_MS = 24 * 60 * 60 * 1000;

const PREFIX = 'retab.draft.';

/** Anything the browser cannot hand back after a reload is dropped. */
function serialisable<T extends object>(data: T): Partial<T> {
    const out: Record<string, unknown> = {};

    for (const [key, value] of Object.entries(data)) {
        if (value instanceof File || value instanceof Blob) continue;
        if (typeof value === 'function') continue;
        // Arrays of files (the product form's image picker) go the same way.
        if (Array.isArray(value) && value.some((v) => v instanceof File || v instanceof Blob)) continue;
        out[key] = value;
    }

    return out as Partial<T>;
}

export function useFormDraft<T extends object>({
    key,
    data,
    setData,
    active = true,
}: {
    /** Unique per record, e.g. `hero.7` or `product.new` — never shared. */
    key: string;
    data: T;
    setData: (values: T) => void;
    /** False while a dialog is closed, so a shut form never saves or restores. */
    active?: boolean;
}): { restored: boolean; dismiss: () => void; clear: () => void } {
    const storageKey = PREFIX + key;
    const [restored, setRestored] = useState(false);
    // Restore exactly once per (key, opening). Without this the save effect
    // below would immediately re-trigger a restore and the two would loop.
    const tried = useRef<string | null>(null);

    const clear = () => {
        setRestored(false);
        try {
            window.localStorage.removeItem(storageKey);
        } catch {
            /* storage unavailable — nothing to clean up */
        }
    };

    // ---- restore ----------------------------------------------------------
    useEffect(() => {
        if (!active) {
            tried.current = null; // so reopening the same record tries again

            return;
        }
        if (tried.current === storageKey) return;
        tried.current = storageKey;

        try {
            const raw = window.localStorage.getItem(storageKey);
            if (!raw) return;

            const parsed = JSON.parse(raw) as { at: number; values: Partial<T> };
            if (!parsed?.at || Date.now() - parsed.at > MAX_AGE_MS) {
                window.localStorage.removeItem(storageKey);

                return;
            }

            // ⚠️ Merged over the CURRENT data rather than replacing it, so fields
            // the draft predates (a column added since) keep their real defaults
            // instead of becoming undefined.
            setData({ ...data, ...parsed.values });
            setRestored(true);
        } catch {
            /* unreadable draft — behave as though there were none */
        }
        // `data`/`setData` deliberately excluded: this runs on opening, not on
        // every keystroke, and including them would restore over live edits.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [storageKey, active]);

    // ---- save -------------------------------------------------------------
    useEffect(() => {
        if (!active) return;

        // Debounced: this fires on every keystroke, and localStorage writes are
        // synchronous and block the main thread.
        const id = window.setTimeout(() => {
            try {
                window.localStorage.setItem(storageKey, JSON.stringify({ at: Date.now(), values: serialisable(data) }));
            } catch {
                /* quota or private mode — the form still works, just unsaved */
            }
        }, 400);

        return () => window.clearTimeout(id);
    }, [storageKey, data, active]);

    return { restored, dismiss: () => setRestored(false), clear };
}
