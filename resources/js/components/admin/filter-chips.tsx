import { cn } from '@/lib/utils';

export interface FilterChip {
    /** null is the "All" chip. */
    value: string | null;
    label: string;
    count?: number;
}

/**
 * The status filter row above a list.
 *
 * 🔑 Deliberately `rounded-full` while status pills are `rounded-md`: round now
 * means "press me", square means "read me". They sit inches apart on every list
 * page, so the shape is what tells them apart at a glance.
 *
 * ⚠️ The active chip is brand teal, not the stark white it used to be. On a dark
 * panel `bg-white` was the single brightest thing on the page — brighter than the
 * amber status pills — which put a filter selection above work that needs doing.
 * Three pages had each hand-rolled that same markup.
 */
export default function FilterChips({
    options,
    value,
    onChange,
    className,
}: {
    options: FilterChip[];
    value: string | null | undefined;
    onChange: (value: string | null) => void;
    className?: string;
}) {
    return (
        <div className={cn('flex flex-wrap gap-2', className)}>
            {options.map((o) => {
                const active = (value ?? null) === o.value;

                return (
                    <button
                        key={o.value ?? '__all'}
                        type="button"
                        onClick={() => onChange(o.value)}
                        aria-pressed={active}
                        className={cn(
                            'focus-visible:ring-brand-gold/50 rounded-full border px-3 py-1 text-sm transition-colors focus:outline-none focus-visible:ring-2',
                            active
                                ? 'border-brand-teal bg-brand-teal font-medium text-white'
                                : 'border-neutral-300 text-neutral-600 hover:border-neutral-400 dark:border-neutral-700 dark:text-neutral-300 dark:hover:border-neutral-500',
                        )}
                    >
                        {o.label}
                        {o.count ? <span className={cn('ms-1', active ? 'text-white/70' : 'text-neutral-500')}>{o.count}</span> : null}
                    </button>
                );
            })}
        </div>
    );
}
