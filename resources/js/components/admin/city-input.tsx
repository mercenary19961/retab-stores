import { exactCity, searchCities, type SaudiCity } from '@/lib/saudi-cities';
import { MapPin } from 'lucide-react';
import { useEffect, useId, useRef, useState } from 'react';

/**
 * Destination city field with Saudi-city suggestions, searchable in Arabic or
 * English.
 *
 * 🔑 Whichever language you search in, the ENGLISH name is what gets submitted.
 * OTO matches the city string on their side and their documented examples are
 * English transliterations, so sending Arabic would be a guess about a matcher we
 * cannot see. Typing «جدة» and pressing Enter therefore submits "Jeddah", resolved
 * through `exactCity()`.
 *
 * ⚠️ Deliberately NOT a select. The field stays free text and an unrecognised value
 * is submitted as typed, so a city missing from our list is an annoyance rather
 * than a wall. That is also why there is no "invalid city" state: we do not know
 * OTO's full vocabulary, so refusing a value on our own authority would be wrong.
 */
export default function CityInput({
    value,
    onChange,
    onSubmit,
    placeholder,
    label,
    className = '',
}: {
    value: string;
    onChange: (value: string) => void;
    /** Fired on select, or on Enter. Always receives the English city name. */
    onSubmit: (value: string) => void;
    placeholder?: string;
    label: string;
    className?: string;
}) {
    const listId = useId();
    const wrap = useRef<HTMLDivElement>(null);
    const [open, setOpen] = useState(false);
    const [highlight, setHighlight] = useState(0);
    const [suggestions, setSuggestions] = useState<SaudiCity[]>([]);

    // Recomputed on every keystroke: the whole list is ~95 entries, so filtering it
    // costs nothing and a debounce would only add lag.
    useEffect(() => {
        setSuggestions(searchCities(value));
        setHighlight(0);
    }, [value]);

    // Closing on an outside click has to be on the document, because the field can
    // lose focus to something that is not a sibling.
    useEffect(() => {
        if (!open) return;
        const onDown = (e: MouseEvent) => {
            if (!wrap.current?.contains(e.target as Node)) setOpen(false);
        };
        document.addEventListener('mousedown', onDown);

        return () => document.removeEventListener('mousedown', onDown);
    }, [open]);

    const choose = (city: SaudiCity) => {
        onChange(city.en);
        setOpen(false);
        onSubmit(city.en);
    };

    /** Enter with nothing highlighted: resolve Arabic to English, else send as typed. */
    const submitRaw = () => {
        const resolved = exactCity(value)?.en ?? value;
        if (resolved !== value) onChange(resolved);
        setOpen(false);
        onSubmit(resolved);
    };

    const onKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            if (!open) {
                setOpen(true);

                return;
            }
            const step = e.key === 'ArrowDown' ? 1 : -1;
            setHighlight((h) => (h + step + suggestions.length) % Math.max(suggestions.length, 1));

            return;
        }

        if (e.key === 'Enter') {
            e.preventDefault();
            if (open && suggestions[highlight]) {
                choose(suggestions[highlight]);
            } else {
                submitRaw();
            }

            return;
        }

        if (e.key === 'Escape' && open) {
            // Only swallow it while the list is open, so Escape still reaches the
            // page's own handler (which closes the carrier detail view) otherwise.
            e.stopPropagation();
            setOpen(false);
        }
    };

    return (
        <div ref={wrap} className={`relative ${className}`}>
            <span className="mb-1 flex items-center gap-1.5 text-xs font-medium text-neutral-400">
                <MapPin className="h-3.5 w-3.5" />
                {label}
            </span>
            <input
                role="combobox"
                aria-expanded={open}
                aria-controls={listId}
                aria-autocomplete="list"
                aria-label={label}
                autoComplete="off"
                value={value}
                onChange={(e) => {
                    onChange(e.target.value);
                    setOpen(true);
                }}
                onFocus={() => setOpen(true)}
                onKeyDown={onKeyDown}
                placeholder={placeholder}
                // The value is an English city name, so the field reads left to right
                // even while the panel is in Arabic.
                dir="ltr"
                className="focus:border-brand-gold focus:ring-brand-gold w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-900 shadow-sm transition-colors placeholder:text-neutral-400 focus:ring-1 focus:outline-none dark:border-neutral-700 dark:bg-neutral-950 dark:text-neutral-100"
            />

            {open && suggestions.length > 0 && (
                <ul
                    id={listId}
                    role="listbox"
                    className="absolute z-20 mt-1 max-h-72 w-full overflow-y-auto rounded-lg border border-neutral-200 bg-white py-1 shadow-xl dark:border-neutral-700 dark:bg-neutral-900"
                >
                    {suggestions.map((city, i) => (
                        <li key={city.en}>
                            <button
                                type="button"
                                role="option"
                                aria-selected={i === highlight}
                                // onMouseDown, not onClick: the input's blur would
                                // otherwise close the list before the click landed.
                                onMouseDown={(e) => {
                                    e.preventDefault();
                                    choose(city);
                                }}
                                onMouseEnter={() => setHighlight(i)}
                                className={`flex w-full items-center justify-between gap-3 px-3 py-1.5 text-start text-sm transition-colors ${
                                    i === highlight ? 'bg-[#1b4e53] text-white' : 'text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-800'
                                }`}
                            >
                                <span dir="ltr">{city.en}</span>
                                <span className={`text-xs ${i === highlight ? 'text-white/70' : 'text-neutral-500'}`} dir="rtl">
                                    {city.ar}
                                </span>
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
