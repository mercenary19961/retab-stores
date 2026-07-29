import { usePage } from '@inertiajs/react';
import { AlertTriangle, Check, X } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';

import { useAdminT } from '@/i18n/use-admin-t';
import { cn } from '@/lib/utils';

interface Flash {
    success?: string | null;
    error?: string | null;
    id?: string | null;
}

type ToastType = 'success' | 'error';
interface ToastData {
    key: number;
    type: ToastType;
    message: string;
}

let seq = 0;
const MAX = 4; // cap the stack so a burst of actions can't flood the corner
const AUTO_DISMISS_MS = 4000;

/**
 * Overlay flash toasts for the admin panel — the non-shifting replacement for the
 * in-flow banner. Pinned to the top inline-end corner (top-left in RTL), success
 * auto-dismisses, errors linger until closed. Driven by the shared `flash` prop; a
 * per-response `flash.id` nonce means even an identical repeated message re-pops.
 */
export default function AdminToasts() {
    const { t } = useAdminT();
    const flash = (usePage().props as { flash?: Flash }).flash;
    const [toasts, setToasts] = useState<ToastData[]>([]);

    const remove = useCallback((key: number) => setToasts((list) => list.filter((x) => x.key !== key)), []);

    useEffect(() => {
        const next: ToastData[] = [];
        if (flash?.success) next.push({ key: ++seq, type: 'success', message: flash.success });
        if (flash?.error) next.push({ key: ++seq, type: 'error', message: flash.error });
        if (next.length) setToasts((list) => [...list, ...next].slice(-MAX));
        // Keyed on the nonce so the same text twice still fires; other flash fields are stable.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [flash?.id]);

    if (toasts.length === 0) return null;

    return (
        <div className="pointer-events-none fixed top-4 end-4 z-[100] flex w-[min(24rem,calc(100%-2rem))] flex-col gap-2">
            {toasts.map((toast) => (
                <ToastItem key={toast.key} toast={toast} onDismiss={() => remove(toast.key)} dismissLabel={t('admin.common.dismiss')} />
            ))}
        </div>
    );
}

function ToastItem({ toast, onDismiss, dismissLabel }: { toast: ToastData; onDismiss: () => void; dismissLabel: string }) {
    const [show, setShow] = useState(false);
    const [hovered, setHovered] = useState(false);
    const isError = toast.type === 'error';

    // Enter transition (mount hidden, flip to shown on the next frame).
    useEffect(() => {
        const r = requestAnimationFrame(() => setShow(true));
        return () => cancelAnimationFrame(r);
    }, []);

    const dismiss = useCallback(() => {
        setShow(false);
        setTimeout(onDismiss, 200); // let the leave transition play before unmount
    }, [onDismiss]);

    // Success auto-dismisses; errors stay. Hovering pauses (and resets) the timer.
    useEffect(() => {
        if (isError || hovered) return;
        const timer = setTimeout(dismiss, AUTO_DISMISS_MS);
        return () => clearTimeout(timer);
    }, [isError, hovered, dismiss]);

    const Icon = isError ? AlertTriangle : Check;

    return (
        <div
            role="status"
            onMouseEnter={() => setHovered(true)}
            onMouseLeave={() => setHovered(false)}
            className={cn(
                'pointer-events-auto flex w-full items-start gap-3 rounded-xl border bg-white px-4 py-3 text-sm shadow-lg ring-1 ring-black/5 transition-all duration-200 motion-reduce:transition-none dark:bg-neutral-900 dark:ring-white/10',
                isError ? 'border-red-200 dark:border-red-900/60' : 'border-green-200 dark:border-green-900/60',
                show ? 'translate-y-0 opacity-100' : '-translate-y-2 opacity-0',
            )}
        >
            <span
                className={cn(
                    'mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full',
                    isError ? 'bg-red-100 text-red-600 dark:bg-red-500/15 dark:text-red-400' : 'bg-green-100 text-green-600 dark:bg-green-500/15 dark:text-green-400',
                )}
            >
                <Icon className="h-3.5 w-3.5" />
            </span>
            <span dir="auto" className="min-w-0 flex-1 text-neutral-700 dark:text-neutral-200">
                {toast.message}
            </span>
            <button
                type="button"
                onClick={dismiss}
                aria-label={dismissLabel}
                className="-me-1 shrink-0 rounded p-0.5 text-neutral-400 transition-colors hover:text-neutral-700 dark:hover:text-neutral-200"
            >
                <X className="h-4 w-4" />
            </button>
        </div>
    );
}
