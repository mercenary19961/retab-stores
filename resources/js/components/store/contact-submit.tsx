import { Turnstile, type TurnstileHandle } from '@/components/turnstile';
import { useForm, usePage } from '@inertiajs/react';
import { AlertCircle, ChevronDown } from 'lucide-react';
import { useId, useRef, useState, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';

const INQUIRY_KEYS = ['order', 'product', 'complaint', 'partnership', 'other'] as const;

interface FormData {
    first_name: string;
    last_name: string;
    email: string;
    phone: string;
    inquiry_type: string;
    message: string;
    'cf-turnstile-response': string;
    [key: string]: string;
}

/**
 * Contact Us → "أرسل رسالتك" (Figma: "contact-us-submit").
 *
 * Geometry read straight from the SVG's rects (no PNG composite this time), so
 * these numbers are exact rather than measured:
 *
 *   container 1090×… of the 1440 frame, x 182.5..1272.5   → 75.69% of the frame
 *   row1 half-fields 445 wide each, gap 200                → 40.83% / 18.35% of the
 *                                                             container
 *   button 446×94, radius 47 (fully pill), centred on the CONTAINER's own centre,
 *   width matching a row1 half-field almost exactly (446 vs 445 — reused, not
 *   coincidence)
 *
 * Every label is drawn TWICE in the source file at the identical size — once above
 * the field, once again inside it — because Figma's mock placeholder text reuses
 * the label layer. That is why one `t()` key serves as both the <label> and the
 * <input placeholder>.
 *
 * All six field/placeholder paths and the heading are filled WHITE (not gold) in
 * the SVG, unlike contact-info's cards — so labels, placeholder text, and typed
 * input value are all white here; only the field's own translucent fill is gold.
 *
 * 🔴 Two deliberate departures from what the file draws:
 *  1. The button is filled #D9D9D9 (flat light grey) with no visible label in the
 *     source — reads as an un-skinned Figma placeholder (every other interactive
 *     surface on this site picks a real brand colour) rather than a considered
 *     choice, so it is built here as a white pill with brand-teal text, matching
 *     the site's established light-on-dark button convention, with real copy
 *     ("إرسال الرسالة" / "Send Message").
 *  2. The inquiry-type <select> has no design-time options (native controls don't
 *     carry them) — the five options here are invented to fit a dates storefront
 *     contact form (order / product / complaint / partnership / other) and are
 *     easy to relabel in i18n (`contact.form.inquiryTypes`) without touching this
 *     component or the backend, which stores the keys, not the labels.
 *
 * 🔴 SIZE, on request: the design's fields (93px, 32px type) were built 1:1 first,
 * then the client asked for the section to fit the screen better. Everything below
 * is now a deliberately SMALLER, more conventional form scale — not a fraction of
 * the design's own numbers the way the About sections were compressed. Field height
 * lands around 50px at the widest breakpoint (comfortably above the ~44px tap-
 * target guidance the earlier "don't shrink a functional form" note was protecting),
 * cut from the original ~82px. Section height dropped from ~1560px to ~800px at
 * 1440px width — verified by measurement, not estimated.
 *
 * 🔑 The Logo 2 watermark, per request, replaces what the SVG actually embeds: a
 * large rotated (150°) instance of the FULL logo lockup (mark + "Retab"/"رطاب"
 * wordmark) at 8% opacity, exported as a flattened raster and used as an SVG
 * pattern fill — the same mechanism as why-us's background plate. Reproducing
 * that exactly would need a new asset; the existing pre-faded Logo 2 KNOT (already
 * used in journey/why-us/contact-info) is used instead for one consistent
 * watermark treatment across the page. ⚠️ Unlike contact-info's, this placement
 * was NOT diff-verified against a bare plate — there wasn't one to diff against —
 * so treat the size/position as a judgement call, not a measurement.
 *
 * ⚠️ Validation/error design (none in the source — a native form mock has no error
 * state to trace). An invalid field gets a red border + a red label + a small
 * AlertCircle + message underneath, all keyed off the SAME `errors.<field>` Inertia
 * already gives every other form on the site — no new state, just a fuller render
 * of what was already there. `aria-invalid` + `aria-describedby` wire each error to
 * its control for real assistive-tech support (React's `useId()` keeps the ids
 * stable across SSR hydration, unlike a random-number id).
 *
 * A Turnstile failure is a FORM-level problem, not one field's — see
 * ContactMessageController for why it is keyed `errors.turnstile`, not
 * `errors.message` (that would have attached a bot-check failure to the message
 * textarea, making a perfectly valid message look invalid). It renders as its own
 * banner above the button instead of under any field.
 */
export default function ContactSubmit() {
    const { t } = useTranslation();
    const authed = Boolean((usePage().props as { auth?: { user?: unknown } }).auth?.user);
    const turnstileRef = useRef<TurnstileHandle>(null);
    const [done, setDone] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm<FormData>({
        first_name: '',
        last_name: '',
        email: '',
        phone: '',
        inquiry_type: '',
        message: '',
        'cf-turnstile-response': '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/contact', {
            preserveScroll: true,
            onSuccess: () => {
                setDone(true);
                reset();
            },
            // A failed guest submission (bad/expired Turnstile token) must re-arm the
            // widget — a burned single-use token left in place would fail forever.
            onError: () => turnstileRef.current?.reset(),
        });
    };

    // Shared field chrome. `hasError` swaps the border (and, via Field below, the
    // label) to red — the fill stays the same gold tint in both states so an error
    // reads as "look here" rather than repainting the whole field a clashing colour.
    const fieldClass = (hasError: boolean) =>
        `bg-brand-gold/[0.28] border w-full rounded-[clamp(0.4rem,0.5vw,0.55rem)] px-[clamp(0.85rem,1.1vw,1.1rem)] py-[clamp(0.55rem,0.9vw,0.85rem)] text-[clamp(0.875rem,1.1vw,1.0625rem)] leading-tight text-white placeholder-white/60 transition-colors focus:outline-none ${
            hasError ? 'border-red-400 focus:border-red-300' : 'border-brand-gold focus:border-white/80'
        }`;
    const labelClass = (hasError: boolean) =>
        `block px-[clamp(0.85rem,1.1vw,1.1rem)] text-start text-[clamp(0.8rem,0.95vw,0.95rem)] font-bold ${hasError ? 'text-red-300' : 'text-white'}`;

    if (done) {
        return (
            <section className="bg-brand-teal relative w-full overflow-hidden py-[clamp(2rem,3.5vw,4rem)]">
                <div className="border-brand-gold/30 relative mx-auto flex w-[75.69%] max-w-[1090px] flex-col items-center gap-3 rounded-2xl border bg-white/5 px-6 py-14 text-center">
                    <p className="font-heading text-[clamp(1.1rem,1.9vw,1.6rem)] font-bold text-white">{t('contact.form.thanks')}</p>
                </div>
            </section>
        );
    }

    return (
        <section className="bg-brand-teal relative w-full overflow-hidden py-[clamp(1.5rem,2.5vw,2.75rem)]">
            {/* Logo 2 knot watermark — see the doc comment above for why this
                replaces the SVG's literal (full-lockup) embedded pattern. */}
            <img
                src="/images/contact/watermark.webp"
                alt=""
                aria-hidden
                className="pointer-events-none absolute top-0 left-0 w-[45%] max-w-[649px] opacity-90 select-none"
                loading="lazy"
            />

            <div className="relative mx-auto w-[75.69%] max-w-[1090px]">
                {/* text-end, not text-start: this heading sits at the container's
                    INLINE-END edge (right in AR, left in EN) — unlike the section
                    headings elsewhere on this page, which read from the start. The
                    design's own heading right-edge doesn't line up with any other
                    element (the same drift contact-info's heading had), so it is
                    anchored to the field container instead, the one reproducible
                    relationship. */}
                <h2 className="text-brand-gold font-heading text-end text-[clamp(1.5rem,2.6vw,2.6rem)] leading-[1.15] font-black">
                    {t('contact.form.heading')}
                </h2>

                {/* `noValidate`, and deliberately NO `required` on any control below:
                    the browser's own validation popup would intercept submission
                    before it ever reaches `onSubmit`, so the server request — and
                    with it every `errors.*` this component renders — would never
                    fire. Matches every other form on this site (RequestSection,
                    return-request, checkout): server-validated only, so one error
                    path serves both a first-time submit and a resubmit. */}
                <form onSubmit={submit} className="mt-[clamp(1rem,2vw,2rem)] space-y-[clamp(0.9rem,1.4vw,1.375rem)]" noValidate>
                    {/* Row 1 — two columns. Plain reading order (first name, then last
                        name): under dir=rtl the grid places the first child on the
                        visual RIGHT on its own, so first_name lands right (matching
                        the design) without any physical left/right hardcoding. */}
                    <div className="grid grid-cols-2 gap-[18.35%]">
                        <Field
                            id="first_name"
                            label={t('contact.form.firstName')}
                            error={errors.first_name}
                            labelClass={labelClass}
                            fieldClass={fieldClass}
                        >
                            {(cls, aria) => (
                                <input
                                    {...aria}
                                    type="text"
                                    value={data.first_name}
                                    onChange={(e) => setData('first_name', e.target.value)}
                                    placeholder={t('contact.form.firstName')}
                                    autoComplete="given-name"
                                    className={cls}
                                />
                            )}
                        </Field>
                        <Field
                            id="last_name"
                            label={t('contact.form.lastName')}
                            error={errors.last_name}
                            labelClass={labelClass}
                            fieldClass={fieldClass}
                        >
                            {(cls, aria) => (
                                <input
                                    {...aria}
                                    type="text"
                                    value={data.last_name}
                                    onChange={(e) => setData('last_name', e.target.value)}
                                    placeholder={t('contact.form.lastName')}
                                    autoComplete="family-name"
                                    className={cls}
                                />
                            )}
                        </Field>
                    </div>

                    <Field id="email" label={t('contact.form.email')} error={errors.email} labelClass={labelClass} fieldClass={fieldClass}>
                        {(cls, aria) => (
                            <input
                                {...aria}
                                type="email"
                                dir="ltr"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                placeholder={t('contact.form.email')}
                                autoComplete="email"
                                className={`${cls} text-end`}
                            />
                        )}
                    </Field>

                    <Field id="phone" label={t('contact.form.phone')} error={errors.phone} labelClass={labelClass} fieldClass={fieldClass}>
                        {(cls, aria) => (
                            <input
                                {...aria}
                                type="tel"
                                dir="ltr"
                                inputMode="tel"
                                value={data.phone}
                                onChange={(e) => setData('phone', e.target.value)}
                                placeholder={t('contact.form.phone')}
                                autoComplete="tel"
                                className={`${cls} text-end`}
                            />
                        )}
                    </Field>

                    <Field
                        id="inquiry_type"
                        label={t('contact.form.inquiryType')}
                        error={errors.inquiry_type}
                        labelClass={labelClass}
                        fieldClass={fieldClass}
                    >
                        {(cls, aria) => (
                            <div className="relative">
                                {/* Native <select>: the design's chevron sits at the
                                    field's physical LEFT — the inline-END side in RTL —
                                    which is exactly where a native control's own affordance
                                    sits by convention, so this is a real <select> rather
                                    than a custom listbox. appearance-none drops the
                                    browser's own arrow so the one below (positioned
                                    logically via `end-*`) is the only one shown, and stays
                                    correctly placed under both directions. */}
                                <select
                                    {...aria}
                                    value={data.inquiry_type}
                                    onChange={(e) => setData('inquiry_type', e.target.value)}
                                    className={`${cls} appearance-none ${data.inquiry_type ? '' : 'text-white/60'}`}
                                >
                                    <option value="" disabled>
                                        {t('contact.form.inquiryType')}
                                    </option>
                                    {INQUIRY_KEYS.map((key) => (
                                        <option key={key} value={key} className="text-brand-teal">
                                            {t(`contact.form.inquiryTypes.${key}`)}
                                        </option>
                                    ))}
                                </select>
                                <ChevronDown
                                    aria-hidden
                                    className="pointer-events-none absolute end-[clamp(0.85rem,1.1vw,1.1rem)] top-1/2 size-4 -translate-y-1/2 text-white/70"
                                />
                            </div>
                        )}
                    </Field>

                    <Field id="message" label={t('contact.form.message')} error={errors.message} labelClass={labelClass} fieldClass={fieldClass}>
                        {(cls, aria) => (
                            <textarea
                                {...aria}
                                value={data.message}
                                onChange={(e) => setData('message', e.target.value)}
                                placeholder={t('contact.form.message')}
                                rows={3}
                                className={`${cls} resize-none`}
                            />
                        )}
                    </Field>

                    {/* Guests only — a signed-in visitor already carries an
                        accountable session, matching the Coming-Soon "I want this"
                        convention. Renders nothing until a site key is configured, so
                        dev/staging stays frictionless. */}
                    {!authed && (
                        <div className="flex justify-center">
                            <Turnstile ref={turnstileRef} onVerify={(token) => setData('cf-turnstile-response', token)} />
                        </div>
                    )}

                    {/* Form-level banner — a Turnstile failure isn't any one field's
                        fault, so it doesn't borrow a field's error slot (see the doc
                        comment). Same visual language as a field error (red, an icon,
                        a message), just at the width of the whole form instead. */}
                    {errors.turnstile && (
                        <div
                            role="alert"
                            className="flex items-center justify-center gap-2 rounded-lg border border-red-400 bg-red-500/10 px-4 py-2.5 text-center"
                        >
                            <AlertCircle aria-hidden className="size-4 shrink-0 text-red-300" />
                            <span className="text-sm text-red-200">{errors.turnstile}</span>
                        </div>
                    )}

                    {/* Button width matches a row1 half-field almost exactly (446 vs
                        445 in the design) — reused rather than a fresh number. White
                        fill + teal text: see the doc comment on why this replaces the
                        design's unstyled #D9D9D9 placeholder. */}
                    <div className="flex justify-center pt-[clamp(0.3rem,0.7vw,0.75rem)]">
                        <button
                            type="submit"
                            disabled={processing}
                            className="hover:bg-brand-cream text-brand-teal w-[40.9%] min-w-[200px] rounded-full bg-white px-6 py-[clamp(0.55rem,0.9vw,0.85rem)] text-[clamp(0.85rem,1vw,1rem)] font-bold transition-colors disabled:opacity-60"
                        >
                            {processing ? t('contact.form.sending') : t('contact.form.submit')}
                        </button>
                    </div>
                </form>
            </div>
        </section>
    );
}

interface FieldAria {
    id: string;
    'aria-invalid': boolean;
    'aria-describedby': string | undefined;
}

/**
 * Label + control + error, sharing one horizontal inset so the label, the field,
 * and the error message all align on the same edge. `children` is a render
 * function rather than a plain node so the control can receive both the computed
 * className (error-aware) and the aria wiring (id / aria-invalid /
 * aria-describedby) without every call site re-deriving them.
 */
function Field({
    id,
    label,
    error,
    labelClass,
    fieldClass,
    children,
}: {
    id: string;
    label: string;
    error?: string;
    labelClass: (hasError: boolean) => string;
    fieldClass: (hasError: boolean) => string;
    children: (className: string, aria: FieldAria) => ReactNode;
}) {
    // useId(), not a random number: this renders through the SSR sidecar, and a
    // Math.random()-based id would mismatch between the server-rendered markup and
    // the client's hydration pass.
    const reactId = useId();
    const errorId = `${id}-error-${reactId}`;
    const hasError = Boolean(error);

    return (
        <div>
            <label htmlFor={`${id}-${reactId}`} className={labelClass(hasError)}>
                {label}
            </label>
            <div className="mt-[clamp(0.3rem,0.4vw,0.4rem)]">
                {children(fieldClass(hasError), {
                    id: `${id}-${reactId}`,
                    'aria-invalid': hasError,
                    'aria-describedby': hasError ? errorId : undefined,
                })}
            </div>
            {error && (
                <p
                    id={errorId}
                    role="alert"
                    className="mt-1 flex items-center gap-1.5 px-[clamp(0.85rem,1.1vw,1.1rem)] text-start text-xs text-red-300"
                >
                    <AlertCircle aria-hidden className="size-3.5 shrink-0" />
                    {error}
                </p>
            )}
        </div>
    );
}
