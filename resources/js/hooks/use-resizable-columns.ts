import { usePage } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';

export interface ColumnDef {
    key: string;
    defaultWidth: number;
    minWidth?: number;
    maxWidth?: number;
}

type Widths = Record<string, number>;

/**
 * Drag-to-resize table columns, persisted to the admin's account (cross-device)
 * via PUT /admin/preferences/table-widths and hydrated from the shared
 * `tablePrefs` prop. RTL-aware (the grip sits on the column's inline-end).
 * Double-click a grip to reset that column; `resetAll()` restores defaults.
 */
export function useResizableColumns({
    tableKey,
    columns,
    enabled = true,
}: {
    tableKey: string;
    columns: ColumnDef[];
    enabled?: boolean;
}) {
    const saved = ((usePage().props as { tablePrefs?: Record<string, Widths> }).tablePrefs ?? {})[tableKey];

    const defaults = useMemo(
        () => Object.fromEntries(columns.map((c) => [c.key, c.defaultWidth])) as Widths,
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [columns.map((c) => `${c.key}:${c.defaultWidth}`).join(',')],
    );
    const constraints = useMemo(
        () => Object.fromEntries(columns.map((c) => [c.key, { min: c.minWidth ?? 80, max: c.maxWidth ?? 800 }])),
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [columns.map((c) => c.key).join(',')],
    );

    const [widths, setWidths] = useState<Widths>(() => ({ ...defaults, ...(saved ?? {}) }));
    const [resizing, setResizing] = useState<string | null>(null);
    const widthsRef = useRef(widths);
    useEffect(() => {
        widthsRef.current = widths;
    }, [widths]);

    const persist = (next: Widths) => {
        const xsrf = document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1];
        fetch('/admin/preferences/table-widths', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': xsrf ? decodeURIComponent(xsrf) : '',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ table: tableKey, widths: next }),
        }).catch(() => {});
    };

    const startResize = (key: string, startX: number, rtl: boolean) => {
        const startWidth = widthsRef.current[key];
        const { min, max } = constraints[key];
        setResizing(key);
        document.body.style.userSelect = 'none';
        document.body.style.cursor = 'col-resize';

        const onMove = (e: MouseEvent) => {
            const delta = (rtl ? -1 : 1) * (e.clientX - startX);
            const w = Math.round(Math.max(min, Math.min(max, startWidth + delta)));
            setWidths((prev) => ({ ...prev, [key]: w }));
        };
        const onUp = () => {
            document.removeEventListener('mousemove', onMove);
            document.removeEventListener('mouseup', onUp);
            document.body.style.userSelect = '';
            document.body.style.cursor = '';
            setResizing(null);
            persist(widthsRef.current);
        };
        document.addEventListener('mousemove', onMove);
        document.addEventListener('mouseup', onUp);
    };

    const resetColumn = (key: string) => {
        const next = { ...widthsRef.current, [key]: defaults[key] };
        setWidths(next);
        persist(next);
    };
    const resetAll = () => {
        setWidths(defaults);
        persist(defaults);
    };

    const getResizeHandleProps = (key: string) =>
        enabled
            ? {
                  onMouseDown: (e: React.MouseEvent) => {
                      e.preventDefault();
                      e.stopPropagation();
                      // Read the effective direction from the rendered grip so the
                      // drag maths flip correctly under the Arabic (RTL) admin.
                      const rtl = getComputedStyle(e.currentTarget as HTMLElement).direction === 'rtl';
                      startResize(key, e.clientX, rtl);
                  },
                  onDoubleClick: () => resetColumn(key),
              }
            : {};

    const tableWidth = Object.values(widths).reduce((a, b) => a + b, 0);
    const isDefault = columns.every((c) => widths[c.key] === c.defaultWidth);

    return { widths, tableWidth, resizing, isDefault, getResizeHandleProps, resetAll, enabled };
}
