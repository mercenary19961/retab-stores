import * as SelectPrimitive from '@radix-ui/react-select';
import { Check, ChevronDown } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { cn } from '@/lib/utils';

export interface StoreSelectOption {
    value: string;
    label: string;
}

/**
 * Brand-themed dropdown for the storefront. A native <select> can't style its
 * open list (the browser paints it with a blue OS highlight), so this wraps Radix
 * Select — the popup, hover, and check all follow the teal/gold/cream theme and
 * flip correctly in RTL (dir taken from the active language).
 *
 * Trigger defaults to the pill style used across the store toolbar; pass
 * `triggerClassName` to reshape it (e.g. a full-width form field on checkout).
 */
export default function StoreSelect({
    value,
    onValueChange,
    options,
    ariaLabel,
    placeholder,
    triggerClassName,
    contentClassName,
}: {
    value: string;
    onValueChange: (value: string) => void;
    options: StoreSelectOption[];
    ariaLabel?: string;
    placeholder?: string;
    triggerClassName?: string;
    contentClassName?: string;
}) {
    const { i18n } = useTranslation();
    const dir = i18n.dir() as 'ltr' | 'rtl';

    return (
        <SelectPrimitive.Root value={value} onValueChange={onValueChange} dir={dir}>
            <SelectPrimitive.Trigger
                aria-label={ariaLabel}
                className={cn(
                    'inline-flex items-center justify-between gap-2 rounded-full border border-brand-gold/30 bg-white px-4 py-2 text-sm font-medium text-brand-teal transition-colors hover:bg-brand-gold/5 focus:border-brand-teal focus:outline-none data-[state=open]:border-brand-teal',
                    triggerClassName,
                )}
            >
                <SelectPrimitive.Value placeholder={placeholder} />
                <SelectPrimitive.Icon asChild>
                    <ChevronDown className="size-4 text-brand-gold transition-transform" />
                </SelectPrimitive.Icon>
            </SelectPrimitive.Trigger>

            <SelectPrimitive.Portal>
                <SelectPrimitive.Content
                    position="popper"
                    sideOffset={6}
                    className={cn(
                        'z-50 min-w-[var(--radix-select-trigger-width)] overflow-hidden rounded-xl border border-brand-gold/20 bg-white p-1 font-sans text-brand-teal shadow-lg data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95',
                        contentClassName,
                    )}
                >
                    <SelectPrimitive.Viewport>
                        {options.map((option) => (
                            <SelectPrimitive.Item
                                key={option.value}
                                value={option.value}
                                className="relative flex cursor-pointer select-none items-center rounded-lg py-2 pe-8 ps-3 text-sm outline-none transition-colors data-[highlighted]:bg-brand-cream data-[highlighted]:text-brand-teal data-[state=checked]:font-bold"
                            >
                                <SelectPrimitive.ItemText>{option.label}</SelectPrimitive.ItemText>
                                <SelectPrimitive.ItemIndicator className="absolute end-2 inline-flex items-center">
                                    <Check className="size-4 text-brand-teal" />
                                </SelectPrimitive.ItemIndicator>
                            </SelectPrimitive.Item>
                        ))}
                    </SelectPrimitive.Viewport>
                </SelectPrimitive.Content>
            </SelectPrimitive.Portal>
        </SelectPrimitive.Root>
    );
}
