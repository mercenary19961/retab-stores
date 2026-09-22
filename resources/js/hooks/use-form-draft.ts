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

/** A comparable snapshot of the values worth persisting. */
function snapshot<T extends object>(v: T): string {
    return JSON.stringify(serialisable(v));
}

/** One piece of unfinished work sitting in this browser. */
export interface DraftSummary {
    /** The record it belongs to: `new`, or an id like `7`. */
    id: string;
    /** When it was last typed into. */
    at: number;
}

/**
 * What unfinished work is waiting for a given form, e.g. `listDrafts('hero')`.
 *
 * 🔑 This exists because a draft that only reappears once you reopen the dialog is
 * invisible: a refresh closes the modal, the page looks untouched, and the client
 * reasonably concludes their work is gone. A page needs to be able to ASK whether
 * anything is waiting, without mounting the form.
 *
 * ⚠️ Expired entries are deleted as they are found, so the resume affordance can
 * never offer something the hook would then silently refuse to restore.
 */
export function listDrafts(family: string): DraftSummary[] {
    const scope = PREFIX + family + '.';
    const out: DraftSummary[] = [];

    try {
        // Collected first: removing while iterating by index skips entries.
        const keys: string[] = [];
        for (let i = 0; i < window.localStorage.length; i++) {
            const k = window.localStorage.key(i);
            if (k && k.startsWith(scope)) keys.push(k);
        }

        for (const k of keys) {
            try {
                const parsed = JSON.parse(window.localStorage.getItem(k) ?? '') as { at?: number };
                if (!parsed?.at || Date.now() - parsed.at > MAX_AGE_MS) {
                    window.localStorage.removeItem(k);
                    continue;
                }
                out.push({ id: k.slice(scope.length), at: parsed.at });
            } catch {
                window.localStorage.removeItem(k); // unreadable, so of no use to anyone
            }
        }
    } catch {
        /* storage unavailable - behave as though there were no drafts */
    }

    return out.sort((a, b) => b.at - a.at);
}

/** Throw one draft away, e.g. after its record turns out to be gone. */
export function discardDraft(family: string, id: string): void {
    try {
        window.localStorage.removeItem(PREFIX + family + '.' + id);
    } catch {
        /* storage unavailable - nothing to clean up */
    }
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

    /*
     * 🔴 A DRAFT OF AN UNTOUCHED FORM IS WORSE THAN NO DRAFT: it makes the page
     * offer to resume work nobody did, and it re-appeared the instant "Start over"
     * blanked the fields, because clearing storage does not stop the save effect
     * from writing the blanked form straight back.
     *
     * So nothing is written until the data actually differs from how the form
     * opened. `clear()` re-baselines to the current values, which is what makes
     * discarding and saving stick instead of immediately re-creating a draft.
     */
    const baseline = useRef<string | null>(null);
    /*
     * ⚠️ Re-baselining inside `clear()` is too EARLY, and that is subtle:
     * "Start over" clears and then blanks the fields, so baselining against the
     * values being discarded made the blanking itself look like fresh typing and
     * wrote the draft straight back. The flag defers it to the next render, by
     * which time the form holds whatever it was reset to.
     */
    const rebase = useRef(false);
    // Restore exactly once per (key, opening). Without this the save effect
    // below would immediately re-trigger a restore and the two would loop.
    const tried = useRef<string | null>(null);

    const clear = () => {
        setRestored(false);
        rebase.current = true;
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
            baseline.current = null;
            rebase.current = false;

            return;
        }
        if (tried.current === storageKey) return;
        tried.current = storageKey;

        // How the form looks BEFORE restoring: typing is measured against this.
        baseline.current = snapshot(data);

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

        // Just discarded or just saved: adopt whatever the form now holds as the
        // new "untouched" state and write nothing this round.
        if (rebase.current) {
            rebase.current = false;
            baseline.current = snapshot(data);

            return;
        }

        // Debounced: this fires on every keystroke, and localStorage writes are
        // synchronous and block the main thread.
        const id = window.setTimeout(() => {
            const values = snapshot(data);
            // Nothing typed yet (or just discarded): leave storage alone.
            if (baseline.current === null || values === baseline.current) return;

            try {
                window.localStorage.setItem(storageKey, JSON.stringify({ at: Date.now(), values: JSON.parse(values) }));
            } catch {
                /* quota or private mode — the form still works, just unsaved */
            }
        }, 400);

        return () => window.clearTimeout(id);
    }, [storageKey, data, active]);

    return { restored, dismiss: () => setRestored(false), clear };
}
