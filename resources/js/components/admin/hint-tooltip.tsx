import * as TooltipPrimitive from '@radix-ui/react-tooltip';
import { type ReactNode } from 'react';

/**
 * A small hover/focus hint bubble. Rendered in a portal so a table's overflow
 * can't clip it. Wrap any focusable trigger and pass a short `label`. The trigger
 * should stay reachable on hover even when it's aria-disabled (don't use the
 * native `disabled` attribute) so the "why is this off?" hint still shows.
 */
export default function HintTooltip({
    label,
    children,
    side = 'top',
}: {
    label: string;
    children: ReactNode;
    side?: 'top' | 'bottom' | 'left' | 'right';
}) {
    return (
        <TooltipPrimitive.Provider delayDuration={150} disableHoverableContent>
            <TooltipPrimitive.Root>
                <TooltipPrimitive.Trigger asChild>{children}</TooltipPrimitive.Trigger>
                <TooltipPrimitive.Portal>
                    <TooltipPrimitive.Content
                        side={side}
                        sideOffset={6}
                        className="z-50 max-w-[15rem] select-none rounded-md bg-neutral-900 px-2 py-1 text-xs font-medium text-white shadow-lg animate-in fade-in-0 zoom-in-95 dark:bg-neutral-700"
                    >
                        {label}
                        <TooltipPrimitive.Arrow className="fill-neutral-900 dark:fill-neutral-700" />
                    </TooltipPrimitive.Content>
                </TooltipPrimitive.Portal>
            </TooltipPrimitive.Root>
        </TooltipPrimitive.Provider>
    );
}
