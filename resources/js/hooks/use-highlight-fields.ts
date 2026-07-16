import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';

/**
 * Reads `?highlight=field1,field2` from the current URL and, for each, scrolls to
 * and briefly pulses the form field whose container has `id="field-{name}"`.
 * Used when arriving from a change-log entry's "Go to item" button so the admin
 * sees exactly which field(s) that entry changed. No-op when the param is absent.
 */
export function useHighlightFields() {
    const { url } = usePage();

    useEffect(() => {
        if (typeof window === 'undefined') return;
        const param = new URL(url, window.location.origin).searchParams.get('highlight');
        if (!param) return;

        const names = param.split(',').map((s) => s.trim()).filter(Boolean);
        const els = names
            .map((n) => document.getElementById(`field-${n}`))
            .filter((el): el is HTMLElement => el !== null);
        if (els.length === 0) return;

        els[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
        els.forEach((el) => el.classList.add('field-highlight'));
        const timer = setTimeout(() => els.forEach((el) => el.classList.remove('field-highlight')), 3500);
        return () => clearTimeout(timer);
    }, [url]);
}
