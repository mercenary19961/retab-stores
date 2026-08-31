import Button from '@/components/admin/button';
import FilterChips from '@/components/admin/filter-chips';
import Modal from '@/components/admin/modal';
import StatusToggle from '@/components/admin/status-toggle';
import CopyText from '@/components/copy-text';
import StatusPill from '@/components/status-pill';
import { useCan } from '@/hooks/use-can';
import { useAdminT } from '@/i18n/use-admin-t';
import AdminLayout from '@/layouts/admin-layout';
import { CARD } from '@/lib/admin-ui';
import { Head, router, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    CircleSlash,
    Clock,
    ExternalLink,
    Globe,
    LifeBuoy,
    Mail,
    MapPin,
    Package,
    Pencil,
    Phone,
    PlugZap,
    RefreshCw,
    Truck,
    Zap,
} from 'lucide-react';
import { useMemo, useState } from 'react';

interface Service {
    id: number | null;
    carrier: string;
    service: string | null;
    price: number | null;
    currency: string;
    estimated_delivery: string | null;
    pickup_cut_off: string | null;
    logo: string | null;
    max_order_value: number | null;
    max_free_weight: number | null;
    extra_weight_per_kg: number | null;
    return_fee: number | null;
    pickup_dropoff: boolean | null;
}

interface Carrier {
    id: number;
    key: string;
    name: string;
    name_ar: string | null;
    is_enabled: boolean;
    website_url: string | null;
    support_phone: string | null;
    support_email: string | null;
    support_url: string | null;
    tracking_url: string | null;
    oto_url: string | null;
    notes: string | null;
    last_seen_at: string | null;
    available: boolean;
    services: Service[];
    cheapest: number | null;
    currency: string;
}

const INPUT =
    'w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-900 shadow-sm transition-colors placeholder:text-neutral-400 focus:border-brand-gold focus:outline-none focus:ring-1 focus:ring-brand-gold dark:border-neutral-700 dark:bg-neutral-950 dark:text-neutral-100';

/**
 * Shipping carriers portal.
 *
 * Reads as three questions per carrier, in the order someone actually asks them:
 * can we ship with it today and for how much (live from OTO), will we ship with
 * it at all (the switch, ours), and who do I call when a parcel goes missing
 * (the contact card, ours).
 *
 * 🔑 Availability and enablement are shown as SEPARATE things and never merged
 * into one "status". They fail for unrelated reasons and need opposite responses:
 * "OTO is not offering this today" is someone else's problem to wait out, while
 * "we switched this off" is a decision on this page. Collapsing them into one
 * green/grey dot would make an outage look like a setting.
 */
export default function ShippingIndex({
    carriers,
    city,
    error,
    fetched_at: fetchedAt,
    detailed,
    originCity,
    otoUrl,
}: {
    carriers: Carrier[];
    city: string | null;
    error: string | null;
    fetched_at: string | null;
    detailed: boolean;
    originCity: string;
    otoUrl: string;
}) {
    const { t, i18n } = useAdminT();
    const can = useCan();
    const manage = can('shipping.manage');

    const [filter, setFilter] = useState<string | null>(null);
    const [cityInput, setCityInput] = useState(city ?? '');
    const [editing, setEditing] = useState<Carrier | null>(null);

    // SAR is the only currency the store transacts in, so it gets the panel's
    // localised symbol; anything else OTO reports is printed as its raw code
    // rather than guessed at.
    const money = (amount: number | null, currency: string) =>
        amount === null ? null : `${amount.toFixed(2)} ${currency === 'SAR' ? t('admin.common.sar') : currency}`;

    const counts = useMemo(
        () => ({
            available: carriers.filter((c) => c.available).length,
            // What can actually carry a parcel right now: OTO is offering it AND we
            // have not switched it off. This is the number that matters, and it is
            // not either column on its own.
            usable: carriers.filter((c) => c.available && c.is_enabled).length,
            off: carriers.filter((c) => !c.is_enabled).length,
        }),
        [carriers],
    );

    const shown = carriers.filter((c) => {
        if (filter === 'available') return c.available;
        if (filter === 'enabled') return c.is_enabled;
        if (filter === 'off') return !c.is_enabled;
        return true;
    });

    const repriceTo = (next: string) =>
        router.get('/admin/shipping', next.trim() ? { city: next.trim() } : {}, { preserveState: true, preserveScroll: true });

    return (
        <AdminLayout title={t('admin.shipping.title')}>
            <Head title={t('admin.shipping.title')} />

            <p className="mb-4 max-w-3xl text-sm text-neutral-400">{t('admin.shipping.intro')}</p>

            {/* Live-data controls. The city drives the prices below, because OTO
                quotes per destination — a single "the price" would be a fiction. */}
            <div className={`${CARD} mb-5 p-4`}>
                <div className="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div className="flex flex-1 flex-col gap-3 sm:flex-row sm:items-end">
                        <label className="block sm:w-64">
                            <span className="mb-1 flex items-center gap-1.5 text-xs font-medium text-neutral-400">
                                <MapPin className="h-3.5 w-3.5" />
                                {t('admin.shipping.destination')}
                            </span>
                            <input
                                className={INPUT}
                                value={cityInput}
                                onChange={(e) => setCityInput(e.target.value)}
                                onKeyDown={(e) => e.key === 'Enter' && repriceTo(cityInput)}
                                placeholder={originCity}
                                dir="ltr"
                            />
                        </label>
                        <Button variant="secondary" icon={Zap} onClick={() => repriceTo(cityInput)}>
                            {t('admin.shipping.reprice')}
                        </Button>
                    </div>

                    <div className="flex flex-wrap items-center gap-3">
                        {manage && (
                            <Button
                                variant="secondary"
                                icon={RefreshCw}
                                onClick={() =>
                                    router.post(
                                        `/admin/shipping/refresh${cityInput.trim() ? `?city=${encodeURIComponent(cityInput.trim())}` : ''}`,
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                {t('admin.shipping.refresh')}
                            </Button>
                        )}
                        <a
                            href={otoUrl}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="text-brand-gold inline-flex items-center gap-1.5 text-sm hover:underline"
                        >
                            <ExternalLink className="h-4 w-4" />
                            {t('admin.shipping.openOto')}
                        </a>
                    </div>
                </div>

                <div className="mt-4 flex flex-wrap items-center gap-x-6 gap-y-2 border-t border-neutral-100 pt-3 text-xs text-neutral-400 dark:border-neutral-800">
                    <span className="inline-flex items-center gap-1.5">
                        <Truck className="h-3.5 w-3.5" />
                        {t('admin.shipping.usableCount', { n: counts.usable, total: counts.available })}
                    </span>
                    {fetchedAt && (
                        <span className="inline-flex items-center gap-1.5">
                            <Clock className="h-3.5 w-3.5" />
                            {t('admin.shipping.fetchedAt', {
                                when: new Date(fetchedAt).toLocaleTimeString(i18n.language === 'ar' ? 'ar-SA' : 'en-GB', {
                                    hour: '2-digit',
                                    minute: '2-digit',
                                }),
                            })}
                        </span>
                    )}
                </div>
            </div>

            {/* OTO could not be reached. Deliberately not an empty page: the switches
                and the phone numbers below are still exactly what someone needs. */}
            {error && (
                <div className="mb-5 flex items-start gap-3 rounded-xl border border-amber-500/30 bg-amber-500/10 p-4 text-sm text-amber-200">
                    <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-amber-400" />
                    <div>
                        <p className="font-medium">{t('admin.shipping.liveUnavailable')}</p>
                        <p className="mt-1 text-amber-200/80">{t('admin.shipping.liveUnavailableHint')}</p>
                        <p className="mt-2 font-mono text-xs text-amber-200/60" dir="ltr">
                            {error}
                        </p>
                    </div>
                </div>
            )}

            {/* The rate-check fallback answered instead of the full catalogue, so the
                per-service detail simply is not in the payload. Said plainly, because
                otherwise it reads as missing data rather than a plan limit. */}
            {!error && !detailed && carriers.some((c) => c.available) && (
                <div className="mb-5 flex items-start gap-3 rounded-xl border border-neutral-700 bg-neutral-800/40 p-4 text-sm text-neutral-300">
                    <PlugZap className="mt-0.5 h-4 w-4 shrink-0 text-neutral-400" />
                    <p>{t('admin.shipping.basicData')}</p>
                </div>
            )}

            <FilterChips
                className="mb-4"
                value={filter}
                onChange={setFilter}
                options={[
                    { value: null, label: t('admin.shipping.filters.all'), count: carriers.length },
                    { value: 'available', label: t('admin.shipping.filters.available'), count: counts.available },
                    { value: 'off', label: t('admin.shipping.filters.off'), count: counts.off },
                ]}
            />

            {shown.length === 0 && (
                <div className={`${CARD} p-10 text-center`}>
                    <Truck className="mx-auto mb-3 h-8 w-8 text-neutral-600" />
                    <p className="text-sm text-neutral-400">{t('admin.shipping.empty')}</p>
                </div>
            )}

            <div className="grid gap-4 xl:grid-cols-2">
                {shown.map((carrier) => (
                    <article key={carrier.id} className={`${CARD} flex flex-col p-5`}>
                        <header className="flex items-start justify-between gap-3">
                            <div className="flex min-w-0 items-start gap-3">
                                {carrier.services[0]?.logo ? (
                                    <img
                                        src={carrier.services[0].logo}
                                        alt=""
                                        className="h-10 w-10 shrink-0 rounded-lg bg-white object-contain p-1"
                                        loading="lazy"
                                    />
                                ) : (
                                    <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-neutral-800 text-neutral-500">
                                        <Package className="h-5 w-5" />
                                    </span>
                                )}
                                <div className="min-w-0">
                                    <h2 className="truncate font-semibold text-neutral-100">{carrier.name}</h2>
                                    {carrier.name_ar && (
                                        <p className="truncate text-xs text-neutral-500" dir="rtl">
                                            {carrier.name_ar}
                                        </p>
                                    )}
                                </div>
                            </div>

                            <div className="flex shrink-0 flex-col items-end gap-1.5">
                                {/* Ours: what the store has decided. */}
                                {manage ? (
                                    <StatusToggle
                                        tone={carrier.is_enabled ? 'active' : 'idle'}
                                        icon={carrier.is_enabled ? Zap : CircleSlash}
                                        label={carrier.is_enabled ? t('admin.shipping.on') : t('admin.shipping.off')}
                                        url={`/admin/shipping/${carrier.id}/toggle`}
                                        hint={carrier.is_enabled ? t('admin.shipping.turnOffHint') : t('admin.shipping.turnOnHint')}
                                    />
                                ) : (
                                    <StatusPill tone={carrier.is_enabled ? 'active' : 'idle'} icon={carrier.is_enabled ? Zap : CircleSlash}>
                                        {carrier.is_enabled ? t('admin.shipping.on') : t('admin.shipping.off')}
                                    </StatusPill>
                                )}
                                {/* Theirs: what OTO is offering. Never merged with the above. */}
                                <span className="text-[11px] text-neutral-500">
                                    {carrier.available ? t('admin.shipping.availableNow') : t('admin.shipping.notOffered')}
                                </span>
                            </div>
                        </header>

                        {/* Prices. */}
                        <div className="mt-4 rounded-lg border border-neutral-800 bg-neutral-950/40 p-3">
                            {carrier.available ? (
                                <>
                                    <p className="mb-2 text-xs text-neutral-400">
                                        {t('admin.shipping.fromPrice', { price: money(carrier.cheapest, carrier.currency) ?? '—' })}
                                    </p>
                                    <ul className="space-y-1.5">
                                        {carrier.services.map((service, i) => (
                                            <li
                                                key={`${service.id ?? 'x'}-${i}`}
                                                className="flex flex-wrap items-baseline justify-between gap-x-3 text-sm"
                                            >
                                                <span className="text-neutral-300">{service.service ?? carrier.name}</span>
                                                <span className="flex items-baseline gap-3 text-xs text-neutral-500">
                                                    {/* dir="auto" because this string is the PROVIDER's, not ours.
                                                        In Arabic, OTO's "1 to 2 Working Days" was being bidi-reordered
                                                        into "to 2 Working Days 1" — the leading digit is neutral, so the
                                                        run gets laid out right-to-left around it. `auto` resolves from
                                                        the first strong character, so a Latin string reads LTR and an
                                                        Arabic one still reads RTL. No `ltr:` utilities on this element,
                                                        which is what makes setting dir here safe. */}
                                                    {service.estimated_delivery && <span dir="auto">{service.estimated_delivery}</span>}
                                                    {service.pickup_cut_off && (
                                                        <span className="inline-flex items-center gap-1">
                                                            <Clock className="h-3 w-3" />
                                                            {service.pickup_cut_off}
                                                        </span>
                                                    )}
                                                    <span className="font-medium text-neutral-300" dir="ltr">
                                                        {money(service.price, service.currency) ?? '—'}
                                                    </span>
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                    {/* Terms worth knowing before choosing a courier, shown only
                                        when OTO actually returned them. */}
                                    {carrier.services.some((s) => s.max_free_weight || s.extra_weight_per_kg || s.return_fee) && (
                                        <dl className="mt-3 flex flex-wrap gap-x-5 gap-y-1 border-t border-neutral-800 pt-2 text-[11px] text-neutral-500">
                                            {carrier.services[0].max_free_weight != null && (
                                                <div className="flex gap-1">
                                                    <dt>{t('admin.shipping.terms.freeWeight')}</dt>
                                                    <dd dir="ltr">{carrier.services[0].max_free_weight} kg</dd>
                                                </div>
                                            )}
                                            {carrier.services[0].extra_weight_per_kg != null && (
                                                <div className="flex gap-1">
                                                    <dt>{t('admin.shipping.terms.extraKg')}</dt>
                                                    <dd dir="ltr">{money(carrier.services[0].extra_weight_per_kg, carrier.currency)}</dd>
                                                </div>
                                            )}
                                            {carrier.services[0].return_fee != null && (
                                                <div className="flex gap-1">
                                                    <dt>{t('admin.shipping.terms.returnFee')}</dt>
                                                    <dd dir="ltr">{money(carrier.services[0].return_fee, carrier.currency)}</dd>
                                                </div>
                                            )}
                                        </dl>
                                    )}
                                </>
                            ) : (
                                <p className="text-xs text-neutral-500">
                                    {carrier.last_seen_at
                                        ? t('admin.shipping.lastSeen', {
                                              when: new Date(carrier.last_seen_at).toLocaleDateString(i18n.language === 'ar' ? 'ar-SA' : 'en-GB'),
                                          })
                                        : t('admin.shipping.neverSeen')}
                                </p>
                            )}
                        </div>

                        {/* Who to contact. The reason this page exists at 2am. */}
                        <div className="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-xs">
                            {carrier.website_url && (
                                <a
                                    href={carrier.website_url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="inline-flex items-center gap-1.5 text-neutral-400 hover:text-neutral-200"
                                >
                                    <Globe className="h-3.5 w-3.5" />
                                    {t('admin.shipping.website')}
                                </a>
                            )}
                            {carrier.support_url && (
                                <a
                                    href={carrier.support_url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="inline-flex items-center gap-1.5 text-neutral-400 hover:text-neutral-200"
                                >
                                    <LifeBuoy className="h-3.5 w-3.5" />
                                    {t('admin.shipping.supportPortal')}
                                </a>
                            )}
                            {carrier.support_phone && (
                                <span className="inline-flex items-center gap-1.5 text-neutral-400">
                                    <Phone className="h-3.5 w-3.5" />
                                    {/* Copy as well as dial: the panel is used on a laptop,
                                        where a tel: link usually opens nothing. */}
                                    <a href={`tel:${carrier.support_phone.replace(/\s/g, '')}`} className="hover:text-neutral-200" dir="ltr">
                                        {carrier.support_phone}
                                    </a>
                                    <CopyText
                                        value={carrier.support_phone}
                                        copyLabel={t('admin.common.copy')}
                                        copiedLabel={t('admin.common.copied')}
                                    />
                                </span>
                            )}
                            {carrier.support_email && (
                                <span className="inline-flex items-center gap-1.5 text-neutral-400">
                                    <Mail className="h-3.5 w-3.5" />
                                    <span dir="ltr">{carrier.support_email}</span>
                                    <CopyText
                                        value={carrier.support_email}
                                        copyLabel={t('admin.common.copy')}
                                        copiedLabel={t('admin.common.copied')}
                                    />
                                </span>
                            )}
                            {carrier.oto_url && (
                                <a
                                    href={carrier.oto_url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="text-brand-gold inline-flex items-center gap-1.5 hover:underline"
                                >
                                    <ExternalLink className="h-3.5 w-3.5" />
                                    {t('admin.shipping.viewOnOto')}
                                </a>
                            )}
                        </div>

                        {carrier.notes && <p className="mt-3 text-xs text-neutral-500 italic">{carrier.notes}</p>}

                        {manage && (
                            <div className="mt-4 flex justify-end border-t border-neutral-800 pt-3">
                                <Button size="sm" variant="ghost" icon={Pencil} onClick={() => setEditing(carrier)}>
                                    {t('admin.shipping.editDetails')}
                                </Button>
                            </div>
                        )}
                    </article>
                ))}
            </div>

            <EditCarrierModal carrier={editing} onClose={() => setEditing(null)} />
        </AdminLayout>
    );
}

/**
 * The contact card editor.
 *
 * Keyed on the carrier id so switching carriers remounts it with fresh values —
 * without that, useForm keeps the first carrier's data and the admin would
 * silently save one courier's phone number onto another.
 */
function EditCarrierModal({ carrier, onClose }: { carrier: Carrier | null; onClose: () => void }) {
    const { t } = useAdminT();

    if (!carrier) return null;

    return <EditCarrierForm key={carrier.id} carrier={carrier} onClose={onClose} title={t('admin.shipping.editTitle', { name: carrier.name })} />;
}

function EditCarrierForm({ carrier, onClose, title }: { carrier: Carrier; onClose: () => void; title: string }) {
    const { t } = useAdminT();
    const { data, setData, put, processing, errors } = useForm({
        name: carrier.name,
        name_ar: carrier.name_ar ?? '',
        website_url: carrier.website_url ?? '',
        support_phone: carrier.support_phone ?? '',
        support_email: carrier.support_email ?? '',
        support_url: carrier.support_url ?? '',
        tracking_url: carrier.tracking_url ?? '',
        oto_url: carrier.oto_url ?? '',
        notes: carrier.notes ?? '',
    });

    const fields: { key: keyof typeof data; type?: string; hint?: string; dir?: 'ltr' }[] = [
        { key: 'name' },
        { key: 'name_ar' },
        { key: 'website_url', dir: 'ltr' },
        { key: 'support_phone', dir: 'ltr' },
        { key: 'support_email', type: 'email', dir: 'ltr' },
        { key: 'support_url', dir: 'ltr' },
        { key: 'tracking_url', dir: 'ltr', hint: t('admin.shipping.fields.trackingHint') },
        { key: 'oto_url', dir: 'ltr', hint: t('admin.shipping.fields.otoHint') },
    ];

    return (
        <Modal open title={title} onClose={onClose}>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    put(`/admin/shipping/${carrier.id}`, { preserveScroll: true, onSuccess: onClose });
                }}
                // Server-validated only: a native `required` intercepts the submit
                // before Inertia runs, so the localized field errors below would be
                // unreachable.
                noValidate
                className="space-y-4"
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    {fields.map(({ key, type, hint, dir }) => (
                        <label key={key} className={key === 'tracking_url' || key === 'oto_url' ? 'sm:col-span-2' : undefined}>
                            <span className="mb-1 block text-xs font-medium text-neutral-400">{t(`admin.shipping.fields.${key}`)}</span>
                            <input
                                type={type ?? 'text'}
                                dir={dir}
                                className={INPUT}
                                value={data[key]}
                                onChange={(e) => setData(key, e.target.value)}
                                aria-invalid={errors[key] ? true : undefined}
                            />
                            {hint && <span className="mt-1 block text-[11px] text-neutral-500">{hint}</span>}
                            {errors[key] && <span className="mt-1 block text-[11px] text-red-400">{errors[key]}</span>}
                        </label>
                    ))}
                </div>

                <label className="block">
                    <span className="mb-1 block text-xs font-medium text-neutral-400">{t('admin.shipping.fields.notes')}</span>
                    <textarea rows={3} className={INPUT} value={data.notes} onChange={(e) => setData('notes', e.target.value)} />
                </label>

                <div className="flex justify-end gap-3 border-t border-neutral-800 pt-4">
                    <Button variant="ghost" onClick={onClose}>
                        {t('admin.common.cancel')}
                    </Button>
                    <Button type="submit" variant="primary" disabled={processing}>
                        {t('admin.common.save')}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}
