import { router } from '@inertiajs/react';
import { Search, X } from 'lucide-react';
import { useEffect, useRef, useState, type KeyboardEvent } from 'react';
import { useTranslation } from 'react-i18next';

interface Item {
    label: string;
    sublabel: string;
    url: string;
}
interface Group {
    type: string;
    items: Item[];
}

export default function GlobalSearch() {
    const { t } = useTranslation();
    const [q, setQ] = useState('');
    const [groups, setGroups] = useState<Group[]>([]);
    const [open, setOpen] = useState(false);
    const [active, setActive] = useState(0);
    const [loading, setLoading] = useState(false);
    const inputRef = useRef<HTMLInputElement>(null);
    const boxRef = useRef<HTMLDivElement>(null);

    const flat = groups.flatMap((g) => g.items);

    // Debounced fetch; aborts the in-flight request when the query changes.
    useEffect(() => {
        const query = q.trim();
        if (query.length < 2) {
            setGroups([]);
            setOpen(false);
            return;
        }
        setLoading(true);
        const ctrl = new AbortController();
        const timer = setTimeout(() => {
            fetch(`/admin/search?q=${encodeURIComponent(query)}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                signal: ctrl.signal,
            })
                .then((r) => r.json())
                .then((data) => {
                    setGroups(data.groups ?? []);
                    setActive(0);
                    setOpen(true);
                })
                .catch(() => {})
                .finally(() => setLoading(false));
        }, 220);
        return () => {
            clearTimeout(timer);
            ctrl.abort();
        };
    }, [q]);

    // ⌘K / Ctrl+K focuses the search from anywhere.
    useEffect(() => {
        const onKey = (e: globalThis.KeyboardEvent) => {
            if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                inputRef.current?.focus();
            }
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, []);

    // Close when clicking outside.
    useEffect(() => {
        const onClick = (e: MouseEvent) => {
            if (boxRef.current && !boxRef.current.contains(e.target as Node)) setOpen(false);
        };
        document.addEventListener('mousedown', onClick);
        return () => document.removeEventListener('mousedown', onClick);
    }, []);

    const go = (item: Item) => {
        setOpen(false);
        setQ('');
        setGroups([]);
        router.visit(item.url);
    };

    const onKeyDown = (e: KeyboardEvent) => {
        if (e.key === 'Escape') {
            setOpen(false);
            inputRef.current?.blur();
            return;
        }
        if (!open || flat.length === 0) return;
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setActive((a) => (a + 1) % flat.length);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setActive((a) => (a - 1 + flat.length) % flat.length);
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (flat[active]) go(flat[active]);
        }
    };

    // Flatten with a running index so keyboard highlight lines up with `flat`.
    let running = 0;
    const sections = groups.map((g) => ({
        type: g.type,
        items: g.items.map((item) => ({ item, index: running++ })),
    }));

    return (
        <div ref={boxRef} className="relative w-full max-w-md">
            <div className="flex items-center gap-2 rounded-lg border border-neutral-700 bg-neutral-800 px-3 py-1.5">
                <Search className="h-4 w-4 shrink-0 text-neutral-500" />
                <input
                    ref={inputRef}
                    value={q}
                    onChange={(e) => setQ(e.target.value)}
                    onFocus={() => groups.length > 0 && setOpen(true)}
                    onKeyDown={onKeyDown}
                    placeholder={t('admin.search.placeholder')}
                    className="w-full bg-transparent text-sm text-neutral-100 placeholder:text-neutral-500 focus:outline-none"
                    dir="auto"
                />
                {q ? (
                    <button
                        type="button"
                        onClick={() => {
                            setQ('');
                            setGroups([]);
                            setOpen(false);
                        }}
                        className="shrink-0 text-neutral-500 hover:text-neutral-300"
                        aria-label={t('admin.common.clear')}
                    >
                        <X className="h-4 w-4" />
                    </button>
                ) : (
                    <kbd className="hidden shrink-0 rounded border border-neutral-700 px-1.5 text-xs text-neutral-500 sm:inline">⌘K</kbd>
                )}
            </div>

            {open && (
                <div className="absolute z-50 mt-2 max-h-[70vh] w-full overflow-y-auto rounded-lg border border-neutral-700 bg-neutral-900 py-2 shadow-xl">
                    {flat.length === 0 ? (
                        <p className="px-4 py-3 text-sm text-neutral-500">
                            {loading ? '…' : t('admin.search.noResults')}
                        </p>
                    ) : (
                        sections.map((section) => (
                            <div key={section.type}>
                                <div className="px-4 py-1 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                                    {t(`admin.search.groups.${section.type}`)}
                                </div>
                                {section.items.map(({ item, index }) => (
                                    <button
                                        key={item.url}
                                        type="button"
                                        onMouseEnter={() => setActive(index)}
                                        onClick={() => go(item)}
                                        className={`flex w-full flex-col items-start px-4 py-2 text-start ${index === active ? 'bg-neutral-800' : ''}`}
                                    >
                                        <span className="text-sm text-neutral-100" dir="auto">{item.label}</span>
                                        {item.sublabel && <span className="text-xs text-neutral-500" dir="auto">{item.sublabel}</span>}
                                    </button>
                                ))}
                            </div>
                        ))
                    )}
                </div>
            )}
        </div>
    );
}
