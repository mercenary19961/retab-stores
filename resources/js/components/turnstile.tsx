import { usePage } from '@inertiajs/react';
import { forwardRef, useEffect, useImperativeHandle, useRef, useState } from 'react';

declare global {
    interface Window {
        turnstile?: {
            render: (container: HTMLElement, options: TurnstileRenderOptions) => string;
            reset: (widgetId: string) => void;
            remove: (widgetId: string) => void;
        };
    }
}

interface TurnstileRenderOptions {
    sitekey: string;
    callback: (token: string) => void;
    'error-callback'?: (code?: string) => void;
    'expired-callback'?: () => void;
    theme?: 'light' | 'dark' | 'auto';
    size?: 'normal' | 'compact';
}

export interface TurnstileHandle {
    reset: () => void;
}

interface TurnstileProps {
    onVerify: (token: string) => void;
    onError?: (code?: string) => void;
    onExpire?: () => void;
    theme?: 'light' | 'dark' | 'auto';
    className?: string;
}

const SCRIPT_SRC = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';

let scriptLoading: Promise<void> | null = null;

function ensureScript(): Promise<void> {
    if (typeof window === 'undefined') return Promise.resolve();
    if (window.turnstile) return Promise.resolve();
    if (scriptLoading) return scriptLoading;

    scriptLoading = new Promise((resolve, reject) => {
        const existing = document.querySelector<HTMLScriptElement>(`script[src^="${SCRIPT_SRC}"]`);
        if (existing) {
            existing.addEventListener('load', () => resolve());
            existing.addEventListener('error', () => reject(new Error('Failed to load Turnstile')));
            return;
        }
        const s = document.createElement('script');
        s.src = SCRIPT_SRC;
        s.async = true;
        s.defer = true;
        s.onload = () => resolve();
        s.onerror = () => reject(new Error('Failed to load Turnstile'));
        document.head.appendChild(s);
    });

    return scriptLoading;
}

/**
 * Cloudflare Turnstile widget (ported from Sky Amman). Single-use token
 * semantics: after a failed server submission burns the token, parents call
 * `reset()` via ref to re-arm. When TURNSTILE_SITE_KEY isn't configured the
 * widget renders nothing — the server-side TurnstileVerifier is the gate.
 */
export const Turnstile = forwardRef<TurnstileHandle, TurnstileProps>(function Turnstile(
    { onVerify, onError, onExpire, theme = 'light', className },
    ref,
) {
    const containerRef = useRef<HTMLDivElement>(null);
    const widgetIdRef = useRef<string | null>(null);
    const siteKey = (usePage().props as { turnstileSiteKey?: string | null }).turnstileSiteKey ?? undefined;
    const [errored, setErrored] = useState(false);

    useImperativeHandle(ref, () => ({
        reset: () => {
            if (widgetIdRef.current && window.turnstile) {
                window.turnstile.reset(widgetIdRef.current);
            }
        },
    }));

    useEffect(() => {
        if (!siteKey || !containerRef.current || errored) return;
        let mounted = true;

        ensureScript()
            .then(() => {
                if (!mounted || !containerRef.current || !window.turnstile) return;
                widgetIdRef.current = window.turnstile.render(containerRef.current, {
                    sitekey: siteKey,
                    theme,
                    callback: onVerify,
                    'error-callback': (code) => {
                        // Stop CF's internal retry loop spamming siteverify.
                        setErrored(true);
                        onError?.(code);
                    },
                    'expired-callback': () => {
                        onExpire?.();
                    },
                });
            })
            .catch((err) => {
                console.warn('Turnstile failed to load', err);
                setErrored(true);
                onError?.('script-load-failed');
            });

        return () => {
            mounted = false;
            if (widgetIdRef.current && window.turnstile) {
                try {
                    window.turnstile.remove(widgetIdRef.current);
                } catch {
                    /* widget already removed */
                }
                widgetIdRef.current = null;
            }
        };
    // siteKey + theme are stable per page; intentionally omit callbacks from deps
    // so a parent re-render doesn't tear down + re-render the widget.
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [siteKey, theme, errored]);

    if (!siteKey) return null;
    return <div ref={containerRef} className={className} />;
});
