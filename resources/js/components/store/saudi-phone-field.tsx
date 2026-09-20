import { useTranslation } from 'react-i18next';

/**
 * Phone input with a fixed +966 prefix.
 *
 * 🔑 The prefix is CHROME, not part of the value. The field holds what the
 * customer typed and nothing more, so the server still receives `0512345678` or
 * `512345678` and `PhoneNumber::toWhatsApp()` normalises it — the same single
 * normaliser every WhatsApp path already uses. Baking "+966" into the value here
 * would create a second, competing idea of what a stored phone looks like.
 *
 * ⚠️ A non-Saudi number is still accepted: the customer can paste a full
 * international number and it passes through untouched. The prefix is a hint for
 * the common case, not a restriction — the store ships across the GCC.
 */
export default function SaudiPhoneField({
    label,
    value,
    onChange,
    error,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
    error?: string;
}) {
    const { t } = useTranslation();

    return (
        <label className="block">
            <span className="text-sm text-gray-600">
                {label}
                <span className="text-red-500"> *</span>
            </span>
            {/* `dir="ltr"` on the wrapper keeps the prefix to the left of the
                digits in both locales — a phone number reads left-to-right even
                in Arabic, and bidi would otherwise put +966 on the wrong side. */}
            <span dir="ltr" className="focus-within:border-brand-gold mt-1 flex items-stretch overflow-hidden rounded border border-gray-300">
                <span className="flex items-center bg-gray-50 px-3 text-sm text-gray-500" aria-hidden>
                    +966
                </span>
                <input
                    type="tel"
                    inputMode="tel"
                    autoComplete="tel"
                    data-testid="customer_phone"
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    placeholder={t('checkout.phonePlaceholder')}
                    className="min-w-0 flex-1 px-3 py-2 outline-none"
                />
            </span>
            {error && <span className="text-xs text-red-500">{error}</span>}
        </label>
    );
}
