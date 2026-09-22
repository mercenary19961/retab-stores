import { useAdminT } from '@/i18n/use-admin-t';
import { dismissUpload, guardUnload, subscribe, type Upload } from '@/lib/uploads';
import { AlertCircle, Check, UploadCloud, X } from 'lucide-react';
import { useEffect, useState } from 'react';

/**
 * What is currently uploading, shown while the admin gets on with something else.
 *
 * 🔑 It reads from the MODULE-level store rather than owning the state, which is
 * what lets an upload survive navigation: Inertia remounts this component on every
 * visit, and it simply re-subscribes to whatever is still in flight.
 *
 * ⚠️ PLACEMENT HAS THREE NEIGHBOURS TO DODGE, and a screenshot caught it sitting
 * on top of the first:
 *   - the SIDEBAR, which is `lg:static w-60`, so a viewport-fixed tray at the
 *     start edge lands squarely on the navigation. Offset past it on `lg`, and
 *     back to the edge when the admin collapses it;
 *   - `mobile-scroll-nav`, also `fixed start-4 bottom-4` but `md:hidden`, so the
 *     tray is raised above it on phones;
 *   - `undo-toast` at `end-6 bottom-6`, which is why this stays on the start side.
 * Nothing here moves page layout, so an upload appearing mid-read never shifts
 * what is being read.
 */
export default function UploadTray({ sidebarCollapsed = false }: { sidebarCollapsed?: boolean }) {
    const { t } = useAdminT();
    const [items, setItems] = useState<Upload[]>([]);

    useEffect(() => subscribe(setItems), []);
    // A hard reload kills an in-flight request; an SPA visit does not.
    useEffect(() => guardUnload(), []);

    if (items.length === 0) return null;

    return (
        <div
            data-testid="upload-tray"
            className={`pointer-events-none fixed bottom-28 z-50 flex w-72 flex-col gap-2 md:bottom-4 ${
                sidebarCollapsed ? 'start-4' : 'start-4 lg:start-[16rem]'
            }`}
        >
            {items.map((u) => (
                <div
                    key={u.id}
                    className={`pointer-events-auto rounded-lg border p-3 shadow-lg backdrop-blur ${
                        u.state === 'failed' ? 'border-red-500/40 bg-red-950/80' : 'border-neutral-700 bg-neutral-900/90'
                    }`}
                >
                    <div className="flex items-start gap-2">
                        {u.state === 'done' ? (
                            <Check className="mt-0.5 size-4 shrink-0 text-emerald-400" />
                        ) : u.state === 'failed' ? (
                            <AlertCircle className="mt-0.5 size-4 shrink-0 text-red-400" />
                        ) : (
                            <UploadCloud className="text-brand-gold mt-0.5 size-4 shrink-0 animate-pulse" />
                        )}

                        <div className="min-w-0 flex-1">
                            <p className="truncate text-xs font-medium text-neutral-200">{u.label}</p>
                            <p className={`mt-0.5 text-[11px] ${u.state === 'failed' ? 'text-red-300' : 'text-neutral-400'}`}>
                                {u.state === 'failed'
                                    ? u.error
                                    : u.state === 'done'
                                      ? t('admin.uploads.done')
                                      : u.progress >= 99
                                        ? t('admin.uploads.processing')
                                        : t('admin.uploads.progress', { n: u.progress })}
                            </p>
                        </div>

                        {/* Only a settled upload can be dismissed: hiding one that is
                            still running would leave it invisible but still going. */}
                        {u.state !== 'uploading' && (
                            <button
                                type="button"
                                onClick={() => dismissUpload(u.id)}
                                aria-label={t('admin.uploads.dismiss')}
                                className="-m-1 rounded p-1 text-neutral-500 transition hover:text-neutral-200"
                            >
                                <X className="size-3.5" />
                            </button>
                        )}
                    </div>

                    {u.state === 'uploading' && (
                        <div className="mt-2 h-1 overflow-hidden rounded-full bg-neutral-800">
                            <div className="bg-brand-gold h-full rounded-full transition-[width] duration-200" style={{ width: `${u.progress}%` }} />
                        </div>
                    )}
                </div>
            ))}
        </div>
    );
}
