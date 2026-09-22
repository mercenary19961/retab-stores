import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

/**
 * Retab's own page-loading indicator, replacing Inertia's default grey line.
 *
 * 🔑 Brand teal into brand gold with a travelling highlight, so a slow page reads
 * as *this* store working rather than as a generic framework spinner. The default
 * `#4B5563` bar was the one piece of chrome that looked like nobody had chosen it.
 *
 * ⚠️ DELIBERATELY DELAYED (`SHOW_AFTER_MS`). Most admin visits resolve in well
 * under a quarter second, and a bar that flashes on every click is visual noise
 * that makes the panel feel busier, not faster. It appears only when there is
 * genuinely something to wait for.
 *
 * ⚠️ Mounted from the CLIENT entry only, never from `ssr.jsx`: it subscribes to
 * router events and touches `window`, neither of which exists in the sidecar.
 */

const SHOW_AFTER_MS = 250;

export default function RetabProgress() {
    const [visible, setVisible] = useState(false);
    const [done, setDone] = useState(false);

    useEffect(() => {
        let timer = 0;
        let hide = 0;

        const start = () => {
            window.clearTimeout(hide);
            setDone(false);
            timer = window.setTimeout(() => setVisible(true), SHOW_AFTER_MS);
        };

        const finish = () => {
            window.clearTimeout(timer);
            // 🔑 Run the bar to the end before removing it. Yanking a half-drawn bar
            // off screen reads as a cancelled request rather than a finished one.
            setDone(true);
            hide = window.setTimeout(() => {
                setVisible(false);
                setDone(false);
            }, 260);
        };

        const offStart = router.on('start', start);
        const offFinish = router.on('finish', finish);

        return () => {
            window.clearTimeout(timer);
            window.clearTimeout(hide);
            offStart();
            offFinish();
        };
    }, []);

    if (!visible) return null;

    return (
        <div
            // Presentational only: the page's own content announces the new page, and
            // a live region here would make every navigation speak twice.
            aria-hidden="true"
            className="pointer-events-none fixed inset-x-0 top-0 z-[100] h-[3px] overflow-hidden"
        >
            <div className={`retab-progress-bar h-full ${done ? 'retab-progress-done' : ''}`} />
        </div>
    );
}
