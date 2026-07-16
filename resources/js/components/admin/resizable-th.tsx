import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-react';
import { type MouseEvent, type ReactNode } from 'react';

interface ResizeProps {
    onMouseDown?: (e: MouseEvent) => void;
    onDoubleClick?: () => void;
}

/**
 * A table header cell that carries a fixed width, an optional sort toggle, and a
 * drag-to-resize grip on its inline-end edge. Pair with useResizableColumns.
 */
export default function ResizableTh({
    colKey,
    width,
    resizeProps,
    resizing,
    children,
    sortKey,
    sort,
    direction = 'desc',
    onSort,
    className,
}: {
    colKey: string;
    width: number;
    resizeProps: ResizeProps;
    resizing?: boolean;
    children: ReactNode;
    sortKey?: string;
    sort?: string | null;
    direction?: 'asc' | 'desc';
    onSort?: (col: string) => void;
    className?: string;
}) {
    const sortable = typeof onSort === 'function' && sortKey !== undefined;
    const active = sortable && sort === sortKey;
    const Icon = active ? (direction === 'asc' ? ArrowUp : ArrowDown) : ArrowUpDown;

    return (
        <th style={{ width }} className={`relative overflow-hidden px-4 py-3 font-medium ${className ?? ''}`}>
            {sortable ? (
                <button
                    type="button"
                    onClick={() => onSort!(sortKey!)}
                    className={`inline-flex max-w-full items-center gap-1 hover:text-neutral-200 ${active ? 'text-neutral-200' : ''}`}
                >
                    <span className="truncate">{children}</span>
                    <Icon className="h-3.5 w-3.5 shrink-0 opacity-60" />
                </button>
            ) : (
                <span className="block truncate">{children}</span>
            )}

            {/* Resize grip on the column's inline-end edge (right in LTR, left in RTL). */}
            <span
                {...resizeProps}
                title="Drag to resize, double-click to reset"
                className={`absolute inset-y-0 end-0 z-10 w-1.5 cursor-col-resize touch-none select-none transition-colors ${
                    resizing ? 'bg-brand-gold' : 'bg-transparent hover:bg-brand-gold/60'
                }`}
            />
        </th>
    );
}
