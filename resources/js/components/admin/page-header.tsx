import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { type ReactNode } from 'react';

/**
 * Top-of-page header: an optional back link and title, with the page's primary
 * action (usually Save) pinned to the inline end.
 *
 * Keeping Save up here is the point: a long form should never have to be
 * scrolled to the bottom to submit it. The button reaches its <form> through the
 * HTML `form` attribute, so it can sit outside the element it submits.
 *
 * With a title the actions share the title's row; without one they join the back
 * link, so the primary action is always on the page's first line either way.
 */
export default function PageHeader({
    title,
    back,
    backLabel,
    actions,
}: {
    title?: ReactNode;
    back?: string;
    backLabel?: ReactNode;
    actions?: ReactNode;
}) {
    const backLink = back ? (
        <Link href={back} className="inline-flex items-center gap-1 text-sm text-neutral-500 hover:underline">
            <ArrowLeft className="h-4 w-4 rtl:rotate-180" /> {backLabel}
        </Link>
    ) : null;

    const actionRow = actions ? <div className="flex flex-wrap items-center gap-3">{actions}</div> : null;

    // No title (the page names itself in the top bar): back link and actions share one row.
    if (!title) {
        return (
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                {backLink ?? <span />}
                {actionRow}
            </div>
        );
    }

    return (
        <div className="mb-6">
            {backLink}
            <div className={`flex flex-wrap items-center justify-between gap-3 ${back ? 'mt-1' : ''}`}>
                <h1 className="text-2xl font-bold">{title}</h1>
                {actionRow}
            </div>
        </div>
    );
}
