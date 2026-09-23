import { useAdminT } from '@/i18n/use-admin-t';
import { router, usePage } from '@inertiajs/react';
import { Check, ChevronDown, ChevronUp, Truck, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

interface ShippingFeeProp {
    amount: number;
    /** An automatic free-shipping window is overriding the fee to 0 right now. */
    free: boolean;
    canEdit: boolean;
}

/**
 * The flat shipping fee, in the admin top bar, editable in place.
 *
 * 🔑 Why this one setting gets a shortcut: it is the number the client changes
 * most often and the only one that changes what every customer pays. Buried in a
 * twenty-field form behind three clicks, a routine change felt like surgery.
 *
 * 🔴 Saving goes through the SAME endpoint, permission and change-log entry as
 * the full settings form (`Admin\SettingController::updateShippingFee`). A quick
 * control that skipped the audit trail would be the worst possible shortcut on
 * precisely the value where "who changed this, and when?" has to be answerable.
 */
export default function ShippingFeeBadge() {
    const { t } = useAdminT();
    const shippingFee = usePage().props.shippingFee as ShippingFeeProp | null;

    const [editing, setEditing] = useState(false);
    const [value, setValue] = useState('');
    const [saving, setSaving] = useState(false);
    const inputRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        if (editing) inputRef.current?.select();
    }, [editing]);

    // Null when the signed-in editor has no settings.view grant — the same gate
    // the settings page itself uses, resolved server-side.
    if (!shippingFee) return null;

    const open = () => {
        if (!shippingFee.canEdit) return;
        setValue(String(shippingFee.amount));
        setEditing(true);
    };

    const save = () => {
        // Nothing typed, or the same number back: close without a round trip.
        if (value.trim() === '' || Number(value) === shippingFee.amount) {
            setEditing(false);

            return;
        }

        setSaving(true);
        router.patch(
            '/admin/shipping-fee',
            { shipping_flat_fee: value },
            {
                preserveScroll: true,
                // The badge reads a SHARED prop, so the layout has to re-resolve
                // it; preserving state would leave the old number on screen after
                // a successful save.
                onFinish: () => {
                    setSaving(false);
                    setEditing(false);
                },
            },
        );
    };

    /**
     * Nudge the fee by one riyal.
     *
     * ⚠️ Reads the CURRENT value rather than tracking a number in state: the field
     * is free text while it is being edited, so it can legitimately be empty or
     * mid-typing. Falling back to the saved fee means the first click on a blank
     * field steps from the real number instead of from zero.
     */
    const step = (delta: number) => {
        const from = value.trim() === '' || Number.isNaN(Number(value)) ? shippingFee.amount : Number(value);

        setValue(String(Math.max(0, from + delta)));
    };

    if (editing) {
        return (
            <span className="flex items-center gap-1 rounded-lg border border-neutral-700 bg-neutral-950 px-2 py-1">
                <Truck className="text-brand-gold h-4 w-4 shrink-0" />
                <input
                    ref={inputRef}
                    type="number"
                    min={0}
                    step="1"
                    value={value}
                    disabled={saving}
                    onChange={(e) => setValue(e.target.value)}
                    // Enter saves and Escape abandons, because a field that can
                    // only be dismissed with the mouse is worse than no shortcut.
                    onKeyDown={(e) => {
                        if (e.key === 'Enter') save();
                        if (e.key === 'Escape') setEditing(false);
                    }}
                    aria-label={t('admin.shippingFee.label')}
                    className="no-native-spinner w-12 bg-transparent text-sm text-white outline-none"
                />
                {/* Replaces the browser's own stepper, which is painted in the UA
                    palette and cannot be restyled — pale grey against a dark bar,
                    jammed against the save and cancel icons.
                    ⚠️ tabIndex -1 and preventDefault on mousedown: these are a
                    mouse convenience only. Keeping focus in the field is what lets
                    Enter and Escape keep working after a click, and the native
                    ArrowUp/ArrowDown stepping is untouched for keyboards. */}
                <span className="flex flex-col leading-none">
                    {[
                        { icon: ChevronUp, delta: 1, label: t('admin.shippingFee.increase') },
                        { icon: ChevronDown, delta: -1, label: t('admin.shippingFee.decrease') },
                    ].map(({ icon: Icon, delta, label }) => (
                        <button
                            key={delta}
                            type="button"
                            tabIndex={-1}
                            disabled={saving}
                            onMouseDown={(e) => e.preventDefault()}
                            onClick={() => step(delta)}
                            aria-label={label}
                            className="text-neutral-500 transition-colors hover:text-white disabled:opacity-40"
                        >
                            <Icon className="h-3 w-3" />
                        </button>
                    ))}
                </span>
                <button
                    type="button"
                    onClick={save}
                    disabled={saving}
                    aria-label={t('admin.common.save')}
                    className="text-emerald-400 hover:text-emerald-300"
                >
                    <Check className="h-4 w-4" />
                </button>
                <button
                    type="button"
                    onClick={() => setEditing(false)}
                    aria-label={t('admin.common.cancel')}
                    className="text-neutral-400 hover:text-white"
                >
                    <X className="h-4 w-4" />
                </button>
            </span>
        );
    }

    return (
        <button
            type="button"
            onClick={open}
            // aria-disabled, not disabled: a view-only editor should still be able
            // to hover and learn WHY it will not open.
            aria-disabled={!shippingFee.canEdit}
            title={shippingFee.canEdit ? t('admin.shippingFee.edit') : t('admin.shippingFee.readOnly')}
            className={`flex items-center gap-1.5 rounded-lg px-2 py-1.5 text-neutral-300 transition-colors ${
                shippingFee.canEdit ? 'hover:bg-neutral-800 hover:text-white' : 'cursor-default'
            }`}
        >
            <Truck className="text-brand-gold h-4 w-4 shrink-0" />
            {/* tabular-nums so the bar does not jitter as the figure changes. */}
            {/* Whole riyals read as whole riyals. Decimals are only shown when
                the value genuinely has them (the full settings form still
                accepts 25.50), so the bar never displays a fake ".00". */}
            <span className="tabular-nums">{Number.isInteger(shippingFee.amount) ? shippingFee.amount : shippingFee.amount.toFixed(2)}</span>
            <span className="hidden text-xs text-neutral-500 sm:inline">{t('admin.shippingFee.currency')}</span>
            {/* ⚠️ Without this the client could set 30, watch every customer pay
                nothing, and reasonably conclude the control is broken. */}
            {shippingFee.free && (
                <span className="rounded bg-emerald-500/15 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-400">
                    {t('admin.shippingFee.freeActive')}
                </span>
            )}
        </button>
    );
}
