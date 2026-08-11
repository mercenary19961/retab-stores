import { Turnstile, type TurnstileHandle } from '@/components/turnstile';
import { useForm, usePage } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { useRef, useState, type ReactNode } from 'react';
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
 *   field height 93 (message field 290 — 3.118× taller), radius 11.5 (0.1237 of
 *   height)                                                fill brand-gold @ 28%,
 *                                                             1px solid brand-gold border
 *   button 446×94, radius 47 (fully pill), centred on the CONTAINER's own centre,
 *   width matching a row1 half-field almost exactly (446 vs 445 — reused, not
 *   coincidence)
 *
 * Every label is drawn TWICE in the source file at the identical size — once above
 * the field, once again inside it — because Figma's mock placeholder text reuses
 * the label layer. That is why one `t()` key serves as both the <label> and the
 * <input placeholder>, and why there is exactly one measured type size for both:
 * height-derived across all six labels agrees at ≈32px at weight 700 (labels have
 * kashida in the source, so — as with contact-info — only height is trustworthy;
 * width disagrees on every one of them).
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
 * ⚠️ NOT height-compressed the way the decorative About sections were. Those were
 * shortened because a photo/card grid has no functional floor; input fields do —
 * shrinking them below a comfortable size (WCAG's ~44px guidance) would make the
 * FORM harder to use to make the SECTION shorter, the wrong trade for a functional
 * page. Sizes below scale with clamp()s built directly from the design's own
 * pixel values, so they still shrink gracefully on narrow phones via the vw term.
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

    // Shared field chrome: translucent gold fill over the teal section, matching
    // the design's fill-opacity 0.28 fill + solid 1px border exactly.
    const fieldClass =
        'bg-brand-gold/[0.28] border border-brand-gold text-white placeholder-white/60 w-full rounded-[clamp(0.5rem,0.8vw,0.719rem)] px-[clamp(0.9rem,1.53vw,1.375rem)] py-[clamp(0.7rem,1.36vw,1.225rem)] text-[clamp(0.9rem,2.25vw,2.025rem)] leading-tight transition-colors focus:outline-none focus:border-white/80';
    const labelClass = 'text-white text-[clamp(0.9rem,2.25vw,2.025rem)] font-bold text-start block px-[clamp(0.9rem,1.53vw,1.375rem)]';

    if (done) {
        return (
            <section className="bg-brand-teal relative w-full overflow-hidden py-[clamp(2.5rem,6vw,5.5rem)]">
                <div className="border-brand-gold/30 relative mx-auto flex w-[75.69%] max-w-[1090px] flex-col items-center gap-3 rounded-2xl border bg-white/5 px-6 py-16 text-center">
                    <p className="font-heading text-[clamp(1.2rem,2.6vw,2rem)] font-bold text-white">{t('contact.form.thanks')}</p>
                </div>
            </section>
        );
    }

    return (
        <section className="bg-brand-teal relative w-full overflow-hidden py-[clamp(2rem,4.44vw,4rem)]">
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
                <h2 className="text-brand-gold font-heading text-end text-[clamp(1.75rem,4.375vw,3.9375rem)] leading-[1.15] font-black">
                    {t('contact.form.heading')}
                </h2>

                <form onSubmit={submit} className="mt-[clamp(1.5rem,5.7vw,5.75rem)] space-y-[clamp(1.25rem,3.45vw,3.125rem)]" noValidate>
                    {/* Row 1 — two columns. Plain reading order (first name, then last
                        name): under dir=rtl the grid places the first child on the
                        visual RIGHT on its own, so first_name lands right (matching
                        the design) without any physical left/right hardcoding. */}
                    <div className="grid grid-cols-2 gap-[18.35%]">
                        <Field label={t('contact.form.firstName')} error={errors.first_name} labelClass={labelClass}>
                            <input
                                type="text"
                                value={data.first_name}
                                onChange={(e) => setData('first_name', e.target.value)}
                                placeholder={t('contact.form.firstName')}
                                autoComplete="given-name"
                                required
                                className={fieldClass}
                            />
                        </Field>
                        <Field label={t('contact.form.lastName')} error={errors.last_name} labelClass={labelClass}>
                            <input
                                type="text"
                                value={data.last_name}
                                onChange={(e) => setData('last_name', e.target.value)}
                                placeholder={t('contact.form.lastName')}
                                autoComplete="family-name"
                                required
                                className={fieldClass}
                            />
                        </Field>
                    </div>

                    <Field label={t('contact.form.email')} error={errors.email} labelClass={labelClass}>
                        <input
                            type="email"
                            dir="ltr"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            placeholder={t('contact.form.email')}
                            autoComplete="email"
                            required
                            className={`${fieldClass} text-end`}
                        />
                    </Field>

                    <Field label={t('contact.form.phone')} error={errors.phone} labelClass={labelClass}>
                        <input
                            type="tel"
                            dir="ltr"
                            inputMode="tel"
                            value={data.phone}
                            onChange={(e) => setData('phone', e.target.value)}
                            placeholder={t('contact.form.phone')}
                            autoComplete="tel"
                            required
                            className={`${fieldClass} text-end`}
                        />
                    </Field>

                    <Field label={t('contact.form.inquiryType')} error={errors.inquiry_type} labelClass={labelClass}>
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
                                value={data.inquiry_type}
                                onChange={(e) => setData('inquiry_type', e.target.value)}
                                required
                                className={`${fieldClass} appearance-none ${data.inquiry_type ? '' : 'text-white/60'}`}
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
                                className="pointer-events-none absolute end-[clamp(0.9rem,1.53vw,1.375rem)] top-1/2 size-5 -translate-y-1/2 text-white/70"
                            />
                        </div>
                    </Field>

                    <Field label={t('contact.form.message')} error={errors.message} labelClass={labelClass}>
                        {/* Message field is 290/93 = 3.118× the standard field height
                            in the design — reproduced as a rows count rather than a
                            fixed height so it still grows with the same clamp()
                            padding/font-size as every other field. */}
                        <textarea
                            value={data.message}
                            onChange={(e) => setData('message', e.target.value)}
                            placeholder={t('contact.form.message')}
                            required
                            rows={5}
                            className={`${fieldClass} resize-none`}
                        />
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
                    {errors.message && !data.message && (
                        <p role="alert" className="text-center text-sm text-red-200">
                            {errors.message}
                        </p>
                    )}

                    {/* Button width matches a row1 half-field almost exactly (446 vs
                        445 in the design) — reused rather than a fresh number. White
                        fill + teal text: see the doc comment on why this replaces the
                        design's unstyled #D9D9D9 placeholder. */}
                    <div className="flex justify-center pt-[clamp(0.5rem,1.5vw,1.5rem)]">
                        <button
                            type="submit"
                            disabled={processing}
                            className="hover:bg-brand-cream text-brand-teal w-[40.9%] min-w-[220px] rounded-full bg-white px-6 py-[clamp(0.7rem,1.5vw,1.5rem)] text-[clamp(0.95rem,1.9vw,1.5rem)] font-bold transition-colors disabled:opacity-60"
                        >
                            {processing ? t('contact.form.sending') : t('contact.form.submit')}
                        </button>
                    </div>
                </form>
            </div>
        </section>
    );
}

/** Label above an input, sharing its horizontal inset so both align on the same edge. */
function Field({ label, error, labelClass, children }: { label: string; error?: string; labelClass: string; children: ReactNode }) {
    return (
        <label className="block">
            <span className={labelClass}>{label}</span>
            <div className="mt-[clamp(0.4rem,0.83vw,0.75rem)]">{children}</div>
            {error && (
                <span role="alert" className="mt-1 block px-[clamp(0.9rem,1.53vw,1.375rem)] text-start text-sm text-red-200">
                    {error}
                </span>
            )}
        </label>
    );
}
