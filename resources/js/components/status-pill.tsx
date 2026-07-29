import { type ReactNode } from 'react';

import { cn } from '@/lib/utils';

export type StatusTone = 'neutral' | 'amber' | 'blue' | 'indigo' | 'green' | 'red' | 'purple' | 'teal';

// Soft-tint status pill: a tinted fill carries the semantic colour, an inset ring
// crisps the edge, and a small leading dot makes the state scannable at a glance
// (the modern dashboard treatment). Dark mode uses translucent tints (…/10) so pills
// sit gently on the dark surface instead of glowing as solid blocks.
const TONES: Record<StatusTone, { pill: string; dot: string }> = {
    neutral: {
        pill: 'bg-neutral-100 text-neutral-600 ring-neutral-500/15 dark:bg-neutral-500/10 dark:text-neutral-300 dark:ring-neutral-400/20',
        dot: 'bg-neutral-400 dark:bg-neutral-400',
    },
    amber: {
        pill: 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-400/25',
        dot: 'bg-amber-500 dark:bg-amber-400',
    },
    blue: {
        pill: 'bg-blue-50 text-blue-700 ring-blue-600/20 dark:bg-blue-500/10 dark:text-blue-300 dark:ring-blue-400/25',
        dot: 'bg-blue-500 dark:bg-blue-400',
    },
    indigo: {
        pill: 'bg-indigo-50 text-indigo-700 ring-indigo-600/20 dark:bg-indigo-500/10 dark:text-indigo-300 dark:ring-indigo-400/25',
        dot: 'bg-indigo-500 dark:bg-indigo-400',
    },
    green: {
        pill: 'bg-green-50 text-green-700 ring-green-600/20 dark:bg-green-500/10 dark:text-green-300 dark:ring-green-400/25',
        dot: 'bg-green-500 dark:bg-green-400',
    },
    red: {
        pill: 'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-500/10 dark:text-red-300 dark:ring-red-400/25',
        dot: 'bg-red-500 dark:bg-red-400',
    },
    purple: {
        pill: 'bg-purple-50 text-purple-700 ring-purple-600/20 dark:bg-purple-500/10 dark:text-purple-300 dark:ring-purple-400/25',
        dot: 'bg-purple-500 dark:bg-purple-400',
    },
    teal: {
        pill: 'bg-teal-50 text-teal-700 ring-teal-600/20 dark:bg-teal-500/10 dark:text-teal-300 dark:ring-teal-400/25',
        dot: 'bg-teal-500 dark:bg-teal-400',
    },
};

export default function StatusPill({
    tone,
    children,
    dot = true,
    className,
}: {
    tone: StatusTone;
    children: ReactNode;
    dot?: boolean;
    className?: string;
}) {
    const t = TONES[tone];

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset',
                t.pill,
                className,
            )}
        >
            {dot && <span className={cn('h-1.5 w-1.5 shrink-0 rounded-full', t.dot)} />}
            {children}
        </span>
    );
}
