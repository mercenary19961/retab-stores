import { router } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, ArrowUp, RotateCw, Save, Undo2, type LucideIcon } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { useAdminT } from '@/i18n/use-admin-t';

interface MenuState {
    x: number;
    y: number;
    canSave: boolean;
    canUndo: boolean;
}

// The page's enabled primary "Save/Create" button (primary variant = bg-brand-teal),
// and the "Undo last save" affordance if one is showing.
const findSave = () => document.querySelector<HTMLButtonElement>('button[type="submit"].bg-brand-teal:not([disabled])');
const findUndo = () => document.querySelector<HTMLElement>('[data-undo]');

/**
 * A page-level right-click menu for the admin: quick navigation plus Save/Undo
 * that drive whatever the current page exposes. Right-clicking inside a text
 * field keeps the browser's native menu (copy/paste).
 */
export default function AdminContextMenu() {
    const { t } = useAdminT();
    const [menu, setMenu] = useState<MenuState | null>(null);
    const ref = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const onCtx = (e: MouseEvent) => {
            const target = e.target as HTMLElement;
            if (target.closest('input, textarea, select, [contenteditable="true"]')) return;
            e.preventDefault();
            setMenu({
                x: Math.max(8, Math.min(e.clientX, window.innerWidth - 220)),
                y: Math.max(8, Math.min(e.clientY, window.innerHeight - 280)),
                canSave: !!findSave(),
                canUndo: !!findUndo(),
            });
        };
        document.addEventListener('contextmenu', onCtx);
        return () => document.removeEventListener('contextmenu', onCtx);
    }, []);

    const close = useCallback(() => setMenu(null), []);

    useEffect(() => {
        if (!menu) return;
        const onDown = (e: MouseEvent) => ref.current && !ref.current.contains(e.target as Node) && close();
        const onKey = (e: KeyboardEvent) => e.key === 'Escape' && close();
        window.addEventListener('mousedown', onDown);
        window.addEventListener('keydown', onKey);
        window.addEventListener('scroll', close, true);
        window.addEventListener('resize', close);
        return () => {
            window.removeEventListener('mousedown', onDown);
            window.removeEventListener('keydown', onKey);
            window.removeEventListener('scroll', close, true);
            window.removeEventListener('resize', close);
        };
    }, [menu, close]);

    if (!menu) return null;

    const run = (fn: () => void) => {
        close();
        fn();
    };

    type Item = { key: string; icon: LucideIcon; enabled: boolean; onClick: () => void };
    const groups: Item[][] = [
        [
            { key: 'back', icon: ArrowLeft, enabled: true, onClick: () => window.history.back() },
            { key: 'forward', icon: ArrowRight, enabled: true, onClick: () => window.history.forward() },
            { key: 'reload', icon: RotateCw, enabled: true, onClick: () => router.reload() },
        ],
        [
            { key: 'save', icon: Save, enabled: menu.canSave, onClick: () => findSave()?.click() },
            { key: 'undo', icon: Undo2, enabled: menu.canUndo, onClick: () => findUndo()?.click() },
        ],
        [
            { key: 'top', icon: ArrowUp, enabled: true, onClick: () => document.querySelector('main')?.scrollTo({ top: 0, behavior: 'smooth' }) },
        ],
    ];

    return (
        <div
            ref={ref}
            role="menu"
            style={{ top: menu.y, left: menu.x, zIndex: 60 }}
            className="fixed min-w-[200px] overflow-hidden rounded-lg border border-neutral-700 bg-neutral-900 py-1 text-sm text-neutral-200 shadow-2xl"
        >
            {groups.map((group, gi) => (
                <div key={gi} className={gi > 0 ? 'mt-1 border-t border-neutral-800 pt-1' : ''}>
                    {group.map((item) => {
                        const Icon = item.icon;
                        return (
                            <button
                                key={item.key}
                                type="button"
                                role="menuitem"
                                disabled={!item.enabled}
                                onClick={() => run(item.onClick)}
                                className={`flex w-full items-center gap-3 px-4 py-2 text-start transition-colors ${
                                    item.enabled ? 'hover:bg-neutral-800' : 'cursor-not-allowed opacity-40'
                                }`}
                            >
                                <Icon className="h-4 w-4 shrink-0 text-neutral-400" />
                                {t(`admin.contextMenu.${item.key}`)}
                            </button>
                        );
                    })}
                </div>
            ))}
        </div>
    );
}
