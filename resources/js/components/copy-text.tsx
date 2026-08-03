import { Check, Copy } from 'lucide-react';
import { useState } from 'react';

async function writeClipboard(text: string): Promise<boolean> {
    try {
        if (navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(text);
            return true;
        }
    } catch {
        /* fall through to the legacy path below */
    }
    // Fallback for non-secure contexts / browsers without the async clipboard API.
    try {
        const el = document.createElement('textarea');
        el.value = text;
        el.setAttribute('readonly', '');
        el.style.position = 'fixed';
        el.style.opacity = '0';
        document.body.appendChild(el);
        el.select();
        const ok = document.execCommand('copy');
        document.body.removeChild(el);
        return ok;
    } catch {
        return false;
    }
}

/**
 * An inline value that copies itself to the clipboard on a single click and
 * briefly shows a check mark. Language-neutral (icon feedback); the caller
 * passes localized `copyLabel`/`copiedLabel` for the tooltip + aria-label.
 * Text colour/size is inherited, so it drops into any cell or line as-is.
 */
export default function CopyText({
    value,
    display,
    copyLabel,
    copiedLabel,
    className = '',
}: {
    value: string;
    display?: string;
    copyLabel: string;
    copiedLabel: string;
    className?: string;
}) {
    const [copied, setCopied] = useState(false);

    const onCopy = async () => {
        if (await writeClipboard(value)) {
            setCopied(true);
            window.setTimeout(() => setCopied(false), 1500);
        }
    };

    return (
        <button
            type="button"
            onClick={onCopy}
            title={copied ? copiedLabel : copyLabel}
            aria-label={`${copyLabel}: ${value}`}
            className={`group inline-flex max-w-full items-center gap-1 ${className}`}
        >
            <span dir="ltr" className="truncate">
                {display ?? value}
            </span>
            {copied ? (
                <Check className="size-3.5 shrink-0 text-emerald-600" aria-hidden />
            ) : (
                <Copy className="size-3.5 shrink-0 opacity-40 transition group-hover:opacity-90" aria-hidden />
            )}
        </button>
    );
}
