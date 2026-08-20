import { router } from '@inertiajs/react';
import { type LucideIcon } from 'lucide-react';
import { useState } from 'react';

import HintTooltip from '@/components/admin/hint-tooltip';
import StatusPill, { type StatusTone } from '@/components/status-pill';
import { cn } from '@/lib/utils';

/**
 * A StatusPill that doubles as a one-click on/off toggle. Fires a request to `url`
 * (the controller back-redirects, so Inertia refreshes the row) and shows a busy
 * state while in flight. `hint` renders a styled tooltip on hover/focus. When
 * `disabled`, the action no-ops but the button stays hoverable (aria-disabled, not
 * the native attribute) so the hint still explains why. The flip is trivially
 * reversible, so there's no confirm step.
 */
export default function StatusToggle({
    tone,
    icon,
    label,
    url,
    method = 'patch',
    disabled = false,
    hint,
}: {
    tone: StatusTone;
    icon?: LucideIcon;
    label: string;
    url: string;
    method?: 'patch' | 'post' | 'put';
    disabled?: boolean;
    hint?: string;
}) {
    const [busy, setBusy] = useState(false);
    const blocked = disabled || busy;

    const toggle = () => {
        if (blocked) return;
        setBusy(true);
        router[method](url, {}, { preserveScroll: true, preserveState: true, onFinish: () => setBusy(false) });
    };

    const button = (
        <button
            type="button"
            onClick={toggle}
            aria-disabled={blocked || undefined}
            aria-label={hint ?? label}
            className={cn(
                'focus-visible:ring-brand-gold/50 inline-flex rounded-md transition focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-1 dark:focus-visible:ring-offset-neutral-900',
                disabled ? 'cursor-not-allowed opacity-60' : 'cursor-pointer hover:opacity-75 active:scale-95',
                busy && 'opacity-60',
            )}
        >
            <StatusPill tone={tone} icon={icon}>
                {label}
            </StatusPill>
        </button>
    );

    return hint ? <HintTooltip label={hint}>{button}</HintTooltip> : button;
}
