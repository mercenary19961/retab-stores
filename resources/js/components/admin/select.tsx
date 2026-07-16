import { Check, ChevronDown } from 'lucide-react';
import { useEffect, useRef, useState, type KeyboardEvent, type ReactNode } from 'react';

export interface SelectOption {
    value: string;
    label: ReactNode;
}

/**
 * The one dropdown used across the admin panel. A custom (non-native) listbox so
 * it's fully brand-styled — a native <select> can't override the OS option-
 * highlight (the blue bar) with CSS. Keyboard + click accessible (ARIA listbox),
 * closes on outside-click / Escape, RTL-safe. Selected = brand-gold, hovered =
 * neutral. `className` styles the wrapper width (e.g. "w-full" / "w-full sm:w-auto").
 */
interface Props {
    value: string;
    onChange: (value: string) => void;
    options: SelectOption[];
    placeholder?: ReactNode;
    className?: string;
    disabled?: boolean;
    id?: string;
}

export default function Select({ value, onChange, options, placeholder, className = 'w-full', disabled, id }: Props) {
    const [open, setOpen] = useState(false);
    const [active, setActive] = useState(0);
    const rootRef = useRef<HTMLDivElement>(null);
    const listRef = useRef<HTMLUListElement>(null);

    const selected = options.find((o) => o.value === value) ?? null;

    useEffect(() => {
        if (!open) return;
        const onDown = (e: MouseEvent) => {
            if (rootRef.current && !rootRef.current.contains(e.target as Node)) setOpen(false);
        };
        document.addEventListener('mousedown', onDown);
        return () => document.removeEventListener('mousedown', onDown);
    }, [open]);

    // Keep the active option scrolled into view while navigating.
    useEffect(() => {
        if (open && listRef.current) {
            (listRef.current.children[active] as HTMLElement | undefined)?.scrollIntoView({ block: 'nearest' });
        }
    }, [active, open]);

    const openList = () => {
        if (disabled) return;
        const idx = options.findIndex((o) => o.value === value);
        setActive(idx >= 0 ? idx : 0);
        setOpen(true);
    };

    const commit = (idx: number) => {
        const opt = options[idx];
        if (opt) onChange(opt.value);
        setOpen(false);
    };

    const onKeyDown = (e: KeyboardEvent) => {
        if (disabled) return;
        if (!open) {
            if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(e.key)) {
                e.preventDefault();
                openList();
            }
            return;
        }
        switch (e.key) {
            case 'ArrowDown': e.preventDefault(); setActive((a) => Math.min(options.length - 1, a + 1)); break;
            case 'ArrowUp': e.preventDefault(); setActive((a) => Math.max(0, a - 1)); break;
            case 'Home': e.preventDefault(); setActive(0); break;
            case 'End': e.preventDefault(); setActive(options.length - 1); break;
            case 'Enter':
            case ' ': e.preventDefault(); commit(active); break;
            case 'Escape': e.preventDefault(); setOpen(false); break;
            case 'Tab': setOpen(false); break;
        }
    };

    return (
        <div ref={rootRef} className={`relative ${className}`}>
            <button
                type="button"
                id={id}
                disabled={disabled}
                onClick={() => (open ? setOpen(false) : openList())}
                onKeyDown={onKeyDown}
                aria-haspopup="listbox"
                aria-expanded={open}
                className="flex w-full items-center justify-between gap-2 rounded-lg border border-neutral-300 bg-white py-2 pe-2 ps-3 text-start text-sm text-neutral-800 transition-colors hover:border-neutral-400 focus:border-brand-gold focus:ring-2 focus:ring-brand-gold/30 focus:outline-none disabled:cursor-not-allowed disabled:opacity-60 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100 dark:hover:border-neutral-600"
            >
                <span className={`truncate ${selected ? '' : 'text-neutral-400'}`}>{selected ? selected.label : placeholder}</span>
                <ChevronDown className={`h-4 w-4 shrink-0 text-brand-gold transition-transform ${open ? 'rotate-180' : ''}`} />
            </button>

            {open && (
                <ul
                    ref={listRef}
                    role="listbox"
                    className="absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-lg border border-neutral-700 bg-neutral-900 p-1 shadow-xl"
                >
                    {options.map((opt, i) => {
                        const isSel = opt.value === value;
                        const isActive = i === active;
                        return (
                            <li
                                key={opt.value}
                                role="option"
                                aria-selected={isSel}
                                onMouseEnter={() => setActive(i)}
                                onClick={() => commit(i)}
                                className={`flex cursor-pointer items-center justify-between gap-2 rounded px-3 py-1.5 text-sm transition-colors ${
                                    isSel
                                        ? 'bg-brand-gold/20 text-brand-gold'
                                        : isActive
                                          ? 'bg-neutral-800 text-neutral-100'
                                          : 'text-neutral-300'
                                }`}
                            >
                                <span className="truncate">{opt.label}</span>
                                {isSel && <Check className="h-3.5 w-3.5 shrink-0" />}
                            </li>
                        );
                    })}
                </ul>
            )}
        </div>
    );
}
