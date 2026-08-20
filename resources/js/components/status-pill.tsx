import { type LucideIcon } from 'lucide-react';
import { type ReactNode } from 'react';

import { cn } from '@/lib/utils';

/**
 * Tones are named for WHAT THE READER SHOULD DO, never for a colour — that is the
 * whole point. When tones were colour-named every page picked its own palette and
 * a single order row ended up carrying six different hues, at which point colour
 * stopped meaning anything. A semantic name forces the author to answer "does this
 * need action?" instead of "which colour do I like?".
 *
 *   attention — a decision or verification is owed BY STAFF. The only loud tone.
 *   active    — in flight / live / on. Nobody needs to act.
 *   done      — finished successfully.
 *   stopped   — cancelled, failed, rejected. Finished badly, but not actionable.
 *   idle      — draft, hidden, off, dormant. Nothing is happening.
 *
 * 🔑 `attention` is deliberately rationed: it maps to the states that appear in the
 * dashboard's action queue (awaiting confirmation, a transfer to verify, a return
 * to review). Everything else stays quiet, which is exactly what lets the amber
 * ones be seen. Widening it is how this becomes wallpaper again.
 *
 * Colour lives in the ICON, not the fill — every tone shares one faint surface so
 * a table reads calm, and the icon plus the word carry the identity. `attention`
 * is the single exception that also tints its fill.
 *
 * ⚠️ Dark values only, no `dark:` variants. The admin shell is hardcoded `dark`
 * (`admin-layout.tsx`) and nothing outside it renders a pill, so a light palette
 * here would be code that has never once been rendered — worse than absent, since
 * it reads as supported. Revisit this file if a light admin theme is ever built.
 */
export type StatusTone = 'idle' | 'active' | 'done' | 'attention' | 'stopped';

const TONES: Record<StatusTone, { pill: string; accent: string }> = {
    idle: {
        pill: 'bg-neutral-500/10 text-neutral-300 ring-neutral-400/15',
        accent: 'text-neutral-500',
    },
    active: {
        pill: 'bg-[#1b4e53]/45 text-neutral-200 ring-[#4d9198]/30',
        accent: 'text-[#79bfc4]',
    },
    done: {
        pill: 'bg-neutral-500/10 text-neutral-200 ring-emerald-400/20',
        accent: 'text-emerald-400',
    },
    attention: {
        pill: 'bg-amber-500/12 text-amber-200 ring-amber-400/30',
        accent: 'text-amber-400',
    },
    stopped: {
        pill: 'bg-neutral-500/10 text-neutral-300 ring-red-400/25',
        accent: 'text-red-400',
    },
};

export default function StatusPill({
    tone,
    icon: Icon,
    children,
    dot,
    title,
    className,
}: {
    tone: StatusTone;
    /** Carries the identity, so quiet tones stay distinguishable without shouting. */
    icon?: LucideIcon;
    children: ReactNode;
    /** Falls back to a coloured dot when a state has no sensible icon. */
    dot?: boolean;
    title?: string;
    className?: string;
}) {
    const t = TONES[tone];
    const showDot = dot ?? !Icon;

    return (
        <span
            title={title}
            // Lets a browser check assert the whole panel's status vocabulary at once
            // (and is what E2E should select on, rather than a colour class).
            data-status-tone={tone}
            className={cn(
                // rounded-md, not rounded-full: filter chips in this panel are pills,
                // so a squarer corner says "this is a state you read" rather than
                // "this is a control you press".
                'inline-flex items-center gap-1.5 rounded-md px-2 py-0.5 text-xs font-medium whitespace-nowrap ring-1 ring-inset',
                t.pill,
                className,
            )}
        >
            {Icon && <Icon className={cn('h-3.5 w-3.5 shrink-0', t.accent)} aria-hidden />}
            {showDot && <span className={cn('h-1.5 w-1.5 shrink-0 rounded-full bg-current', t.accent)} />}
            {children}
        </span>
    );
}
