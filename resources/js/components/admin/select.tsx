import { ChevronDown } from 'lucide-react';
import { type ReactNode, type SelectHTMLAttributes } from 'react';

/**
 * The one dropdown used across the admin panel. A native <select> (accessible +
 * keyboard-friendly) restyled to the dark theme with a brand-gold chevron and
 * focus ring; the open options panel is themed via the `.admin-select` rule in
 * app.css. RTL-aware — the chevron sits on the inline-end and padding follows.
 *
 * `className` styles the outer wrapper (width/margins); pass e.g. "w-full" or
 * "w-full sm:w-auto".
 */
interface Props extends SelectHTMLAttributes<HTMLSelectElement> {
    children: ReactNode;
}

export default function Select({ children, className = 'w-full', ...props }: Props) {
    return (
        <div className={`relative inline-flex ${className}`}>
            <select
                {...props}
                className="admin-select w-full cursor-pointer appearance-none rounded-lg border border-neutral-300 bg-white py-2 pe-9 ps-3 text-sm text-neutral-800 transition-colors hover:border-neutral-400 focus:border-brand-gold focus:ring-2 focus:ring-brand-gold/30 focus:outline-none disabled:cursor-not-allowed disabled:opacity-60 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100 dark:hover:border-neutral-600"
            >
                {children}
            </select>
            <ChevronDown className="pointer-events-none absolute inset-y-0 end-3 my-auto h-4 w-4 text-brand-gold" />
        </div>
    );
}
