import { router } from '@inertiajs/react';
import { useState } from 'react';

import StatusPill, { type StatusTone } from '@/components/status-pill';
import { cn } from '@/lib/utils';

/**
 * A StatusPill that doubles as a one-click on/off toggle. Fires a request to `url`
 * (the controller back-redirects, so Inertia refreshes the row) and shows a busy
 * state while in flight; `disabled` renders a not-allowed cursor + no action. The
 * flip is trivially reversible, so there's no confirm step.
 */
export default function StatusToggle({
    tone,
    label,
    url,
    method = 'patch',
    disabled = false,
    title,
}: {
    tone: StatusTone;
    label: string;
    url: string;
    method?: 'patch' | 'post' | 'put';
    disabled?: boolean;
    title?: string;
}) {
    const [busy, setBusy] = useState(false);

    const toggle = () => {
        if (disabled || busy) return;
        setBusy(true);
        router[method](url, {}, { preserveScroll: true, preserveState: true, onFinish: () => setBusy(false) });
    };

    return (
        <button
            type="button"
            onClick={toggle}
            disabled={disabled || busy}
            title={title}
            aria-label={title ?? label}
            className={cn(
                'inline-flex rounded-full transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-gold/50 focus-visible:ring-offset-1 dark:focus-visible:ring-offset-neutral-900',
                disabled ? 'cursor-not-allowed opacity-60' : 'cursor-pointer hover:opacity-75 active:scale-95',
                busy && 'opacity-60',
            )}
        >
            <StatusPill tone={tone}>{label}</StatusPill>
        </button>
    );
}
