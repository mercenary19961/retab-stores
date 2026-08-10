import { type MouseEvent } from 'react';

/**
 * Cursor-following spotlight for a group of cards.
 *
 * Attach the two handlers to the CONTAINER and mark each card with
 * `data-spotlight` + the `spotlight-card` class (see `app.css`). One delegated
 * listener covers the whole group, which is why this is a plain module rather than
 * a hook: a hook would have to be called per card, forcing every card in a `.map()`
 * to become its own component just to host it.
 *
 * ⚠️ `getBoundingClientRect()` is read inside the rAF callback, never in the
 * handler. Reading it per `mousemove` forces a synchronous layout on every event,
 * and mousemove fires far more often than the screen refreshes — so the work is
 * coalesced to one measurement and one style write per frame.
 */

let frame = 0;
let pending: { card: HTMLElement; clientX: number; clientY: number } | null = null;

function flush(): void {
    frame = 0;
    const next = pending;
    pending = null;
    if (!next) return;

    const rect = next.card.getBoundingClientRect();
    next.card.style.setProperty('--spot-x', `${next.clientX - rect.left}px`);
    next.card.style.setProperty('--spot-y', `${next.clientY - rect.top}px`);
}

export function onSpotlightMove(event: MouseEvent<HTMLElement>): void {
    const card = (event.target as HTMLElement).closest<HTMLElement>('[data-spotlight]');
    if (!card) return;

    pending = { card, clientX: event.clientX, clientY: event.clientY };
    if (!frame) frame = requestAnimationFrame(flush);
}

export function onSpotlightLeave(event: MouseEvent<HTMLElement>): void {
    // Drop the coordinates so a re-entry starts from the card's centre rather than
    // wherever the pointer last left it.
    event.currentTarget.querySelectorAll<HTMLElement>('[data-spotlight]').forEach((card) => {
        card.style.removeProperty('--spot-x');
        card.style.removeProperty('--spot-y');
    });
    pending = null;
}
