import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-react';
import { type ReactNode } from 'react';

/**
 * A sortable table header cell for admin tables. Clicking toggles asc/desc via
 * `onSort(col)`; the arrow reflects the active column + direction.
 */
export default function SortableTh({
    col,
    sort,
    direction,
    onSort,
    children,
    className,
}: {
    col: string;
    sort: string | null;
    direction: 'asc' | 'desc';
    onSort: (col: string) => void;
    children: ReactNode;
    className?: string;
}) {
    const active = sort === col;
    const Icon = active ? (direction === 'asc' ? ArrowUp : ArrowDown) : ArrowUpDown;
    return (
        <th className={`px-4 py-3 font-medium ${className ?? ''}`}>
            <button
                type="button"
                onClick={() => onSort(col)}
                className={`inline-flex items-center gap-1 hover:text-neutral-200 ${active ? 'text-neutral-200' : ''}`}
            >
                {children}
                <Icon className="h-3.5 w-3.5 opacity-60" />
            </button>
        </th>
    );
}
