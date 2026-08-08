import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { Eye, EyeOff } from 'lucide-react';
import * as React from 'react';

/**
 * Password field with a show/hide toggle.
 *
 * Deliberately a thin wrapper that only owns the `type` attribute and spreads
 * everything else through, so it works BOTH uncontrolled (the login form submits
 * raw DOM values via Inertia's <Form> to stay immune to password-manager autofill
 * desync) and controlled (the admin form drives it with useForm). Turning this
 * into a controlled-only component would reintroduce that autofill bug.
 *
 * Labels are props rather than looked up here on purpose: this renders inside the
 * admin panel too, where a plain useTranslation() resolves against the STOREFRONT
 * i18n instance (see i18n/use-admin-t.ts). Each caller passes strings from the
 * right instance instead.
 */
const PasswordInput = React.forwardRef<HTMLInputElement, React.ComponentProps<'input'> & { showLabel?: string; hideLabel?: string }>(
    ({ className, showLabel = 'Show password', hideLabel = 'Hide password', ...props }, ref) => {
        const [visible, setVisible] = React.useState(false);
        const Icon = visible ? EyeOff : Eye;

        return (
            <div className="relative">
                {/* pe-10 keeps the value clear of the button; logical properties so the
                toggle lands on the correct side under RTL. */}
                <Input {...props} ref={ref} type={visible ? 'text' : 'password'} className={cn('pe-10', className)} />
                <button
                    type="button"
                    onClick={() => setVisible((v) => !v)}
                    aria-label={visible ? hideLabel : showLabel}
                    aria-pressed={visible}
                    title={visible ? hideLabel : showLabel}
                    className="focus-visible:ring-ring absolute inset-y-0 end-0 flex items-center rounded-e-md px-3 text-neutral-500 transition-colors hover:text-neutral-900 focus-visible:ring-2 focus-visible:outline-hidden dark:hover:text-neutral-100"
                >
                    <Icon className="h-4 w-4" aria-hidden="true" />
                </button>
            </div>
        );
    },
);

PasswordInput.displayName = 'PasswordInput';

export default PasswordInput;
