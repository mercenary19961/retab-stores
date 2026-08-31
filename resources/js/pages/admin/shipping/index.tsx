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
    Search,
    Truck,
    X,
    Zap,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

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

type Money = (amount: number | null, currency?: string) => string | null;
type Delivery = (raw: string | null) => string | null;

const INPUT =
    'w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-900 shadow-sm transition-colors placeholder:text-neutral-400 focus:border-brand-gold focus:outline-none focus:ring-1 focus:ring-brand-gold dark:border-neutral-700 dark:bg-neutral-950 dark:text-neutral-100';

/**
 * Tidy up a carrier name OTO reported in camelCase, for display only.
 *
 * OTO returns names like `adwarLogestechs` and `delexLogestechs` alongside
 * properly written ones. Splitting every lowercase-to-uppercase boundary would be
 * wrong, though: it turns `AyMakan` into "Ay Makan" and `iMile` into "I Mile",
 * which is not how either company writes itself.
 *
 * 🔑 So the split fires only when BOTH sides are long enough to be words (three
 * characters or more), which separates `adwar|Logestechs` while leaving `Ay|Makan`
 * and `i|Mile` alone. The leading capital is added only when a split happened, for
 * the same reason: `iMile` has to keep its lowercase i.
 *
 * The admin can override any of this permanently in Edit details, which is the
 * real fix for a name the heuristic gets wrong.
 */
function prettyName(name: string): string {
    const split = name.replace(/([a-z]{3,})([A-Z][a-z]{2,})/g, '$1 $2');

    return split === name ? name : split.charAt(0).toUpperCase() + split.slice(1);
}

/**
 * The upper bound of a delivery-time string, for picking the fastest carrier.
 * `1to4WorkingDays` is 4. Null when nothing parses.
 */
function deliveryDays(raw: string | null): number | null {
    if (!raw) return null;
    const numbers = raw.match(/\d+/g);

    return numbers ? Math.max(...numbers.map(Number)) : null;
}

/** Cheapest price across a carrier's services, or Infinity so it sorts last. */
function priceOf(carrier: Carrier): number {
    return carrier.cheapest ?? Infinity;
}

/**
 * Shipping carriers portal.
 *
 * Two modes, one set of data. **Grid** is the default: every carrier as a compact
 * card, cheapest first, because the page's primary job is comparison. **Detail**
 * opens in place when a card is clicked, with a rail that keeps every carrier one
 * click away so two couriers can be compared closely without going back out.
 *
 * 🔑 Availability and enablement are shown as SEPARATE things and never merged
 * into one "status". They fail for unrelated reasons and need opposite responses:
 * "OTO is not offering this today" is somebody else's outage to wait out, while
 * "we switched this off" is a decision made on this page. One green dot covering
 * both would make an outage look like a setting.
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
    const [query, setQuery] = useState('');
    const [cityInput, setCityInput] = useState(city ?? '');
    const [editing, setEditing] = useState<Carrier | null>(null);
    /** null = the card grid; a carrier key = the rail-and-detail view. */
    const [selectedKey, setSelectedKey] = useState<string | null>(null);

    // SAR is the only currency the store transacts in, so it gets the panel's
    // localised symbol; anything else OTO reports is printed as its raw code
    // rather than guessed at.
    const money: Money = (amount, currency = 'SAR') =>
        amount === null ? null : `${amount.toFixed(2)} ${currency === 'SAR' ? t('admin.common.sar') : currency}`;

    /**
     * OTO sends delivery times unspaced (`1to4WorkingDays`). Pull the numbers out
     * and rebuild the phrase through i18n rather than tidying the English in place,
     * so the Arabic panel reads «من 1 إلى 4 أيام» instead of a spaced-out English
     * string.
     */
    const delivery: Delivery = (raw) => {
        if (!raw) return null;
        const numbers = raw.match(/\d+/g);

        if (numbers && numbers.length >= 2) return t('admin.shipping.daysRange', { from: numbers[0], to: numbers[1] });
        if (numbers && numbers.length === 1) return t('admin.shipping.daysSingle', { n: numbers[0] });

        // Nothing numeric to work with: show OTO's own string, spaced out.
        return raw.replace(/([a-z])([A-Z])/g, '$1 $2');
    };

    const counts = useMemo(
        () => ({
            all: carriers.length,
            available: carriers.filter((c) => c.available).length,
            off: carriers.filter((c) => !c.is_enabled).length,
            // What can actually carry a parcel right now: OTO is offering it AND we
            // have not switched it off. Neither column tells you this on its own.
            usable: carriers.filter((c) => c.available && c.is_enabled).length,
        }),
        [carriers],
    );

    /** Current filter + search, cheapest first. Drives the grid AND the rail. */
    const visible = useMemo(() => {
        const q = query.trim().toLowerCase();

        return carriers
            .filter((c) => (filter === 'available' ? c.available : filter === 'off' ? !c.is_enabled : true))
            .filter((c) => !q || prettyName(c.name).toLowerCase().includes(q) || (c.name_ar ?? '').includes(q))
            .sort((a, b) => priceOf(a) - priceOf(b));
    }, [carriers, filter, query]);

    // 🔑 Derived, not stored. If the filter changes and the open carrier drops out
    // of the list this falls back to null and the grid returns on its own, so there
    // is no effect to keep in sync and no way to be left reading a hidden carrier.
    const current = visible.find((c) => c.key === selectedKey) ?? null;

    const cheapest = visible.find((c) => c.cheapest !== null) ?? null;
    const fastest = useMemo(() => {
        const rated = visible
            .map((c) => ({
                carrier: c,
                days: Math.min(...c.services.map((s) => deliveryDays(s.estimated_delivery) ?? Infinity)),
            }))
            .filter((r) => Number.isFinite(r.days));

        return rated.length ? rated.reduce((a, b) => (b.days < a.days ? b : a)) : null;
    }, [visible]);

    // On the document rather than the pane: focus may still be on the card that
    // opened it, so a pane-scoped handler would miss a fast Escape.
    useEffect(() => {
        if (!current) return;
        const onKey = (e: KeyboardEvent) => {
            if (e.key === 'Escape') setSelectedKey(null);
        };
        window.addEventListener('keydown', onKey);

        return () => window.removeEventListener('keydown', onKey);
    }, [current]);

    const repriceTo = (next: string) =>
        router.get('/admin/shipping', next.trim() ? { city: next.trim() } : {}, { preserveState: true, preserveScroll: true });

    const logoFor = (carrier: Carrier) => carrier.services.find((s) => s.logo)?.logo ?? null;

    return (
        <AdminLayout title={t('admin.shipping.title')}>
            <Head title={t('admin.shipping.title')} />

            <p className="mb-4 max-w-3xl text-sm text-neutral-400">{t('admin.shipping.intro')}</p>

            {/* Live-data controls. The city drives every price below, because OTO
                quotes per destination and a single "the price" would be a fiction. */}
            <div className={`${CARD} mb-4 p-4`}>
                <div className="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div className="flex flex-1 flex-col gap-3 sm:flex-row sm:items-end">
                        <label className="block sm:w-56">
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
            </div>

            {/* OTO could not be reached. Deliberately not an empty page: the switches
                and the phone numbers below are still exactly what someone needs. */}
            {error && (
                <div className="mb-4 flex items-start gap-3 rounded-xl border border-amber-500/30 bg-amber-500/10 p-4 text-sm text-amber-200">
                    <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-amber-400" />
                    <div>
                        <p className="font-medium">{t('admin.shipping.liveUnavailable')}</p>
                        <p className="mt-1 text-amber-200/80">{t('admin.shipping.liveUnavailableHint')}</p>
                        <p className="mt-2 font-mono text-xs break-all text-amber-200/60" dir="ltr">
                            {error}
                        </p>
                    </div>
                </div>
            )}

            {/* The rate-check fallback carries no per-service detail, so say so
                plainly. Otherwise it reads as missing data rather than a plan limit. */}
            {!error && !detailed && counts.available > 0 && (
                <div className="mb-4 flex items-start gap-3 rounded-xl border border-neutral-700 bg-neutral-800/40 p-4 text-sm text-neutral-300">
                    <PlugZap className="mt-0.5 h-4 w-4 shrink-0 text-neutral-400" />
                    <p>{t('admin.shipping.basicData')}</p>
                </div>
            )}

            {/* Filters + search. Shared by both modes, so the rail always lists the
                same set the grid would have. */}
            <div className="mb-3 flex flex-wrap items-center gap-3">
                <FilterChips
                    value={filter}
                    onChange={setFilter}
                    options={[
                        { value: null, label: t('admin.shipping.filters.all'), count: counts.all },
                        { value: 'available', label: t('admin.shipping.filters.available'), count: counts.available },
                        { value: 'off', label: t('admin.shipping.filters.off'), count: counts.off },
                    ]}
                />
                <label className="relative w-full sm:ms-auto sm:w-56">
                    <Search className="pointer-events-none absolute inset-y-0 start-3 my-auto h-4 w-4 text-neutral-500" />
                    <input
                        className={`${INPUT} ps-9`}
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder={t('admin.shipping.filterPlaceholder')}
                        aria-label={t('admin.shipping.filterPlaceholder')}
                    />
                </label>
            </div>

            {/* The answer above the data, the way the dashboard already works. */}
            <div className="mb-4 flex flex-wrap items-center gap-x-6 gap-y-2 rounded-lg border border-neutral-200 bg-white px-4 py-2.5 text-xs text-neutral-400 dark:border-neutral-800 dark:bg-neutral-900">
                {cheapest && (
                    <span>
                        <span className="text-neutral-500">{t('admin.shipping.summary.cheapest')}</span>{' '}
                        <span className="font-medium text-neutral-200">{prettyName(cheapest.name)}</span>{' '}
                        <span className="font-mono text-neutral-200 tabular-nums" dir="ltr">
                            {money(cheapest.cheapest, cheapest.currency)}
                        </span>
                    </span>
                )}
                {fastest && (
                    <span>
                        <span className="text-neutral-500">{t('admin.shipping.summary.fastest')}</span>{' '}
                        <span className="font-medium text-neutral-200">{prettyName(fastest.carrier.name)}</span>{' '}
                        <span className="text-neutral-200">{t('admin.shipping.daysSingle', { n: fastest.days })}</span>
                    </span>
                )}
                <span>
                    <span className="text-neutral-500">{t('admin.shipping.summary.switchedOn')}</span>{' '}
                    <span className="font-medium text-neutral-200">
                        {t('admin.shipping.usableCount', { n: counts.usable, total: counts.available })}
                    </span>
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

            {visible.length === 0 && (
                <div className={`${CARD} p-10 text-center`}>
                    <Truck className="mx-auto mb-3 h-8 w-8 text-neutral-600" />
                    <p className="text-sm text-neutral-400">{query ? t('admin.shipping.noMatch') : t('admin.shipping.empty')}</p>
                </div>
            )}

            {/* ---------------- MODE 1: the card grid ---------------- */}
            {!current && visible.length > 0 && (
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    {visible.map((carrier) => (
                        <CarrierCard
                            key={carrier.key}
                            carrier={carrier}
                            logo={logoFor(carrier)}
                            manage={manage}
                            delivery={delivery}
                            onOpen={() => setSelectedKey(carrier.key)}
                        />
                    ))}
                </div>
            )}

            {/* ---------------- MODE 2: rail + detail ---------------- */}
            {current && (
                <div className="grid gap-3 lg:grid-cols-[280px_1fr]">
                    {/* Hidden below lg, where the sidebar is still a drawer and a
                        280px rail beside a detail pane would not fit. There the
                        detail takes the full width and the close button is the only
                        way back, which is why that button is never optional. */}
                    <div className="hidden max-h-[560px] overflow-y-auto rounded-xl border border-neutral-200 lg:block dark:border-neutral-800">
                        <p className="sticky top-0 border-b border-neutral-200 bg-neutral-100 px-3 py-2 text-[11px] font-semibold tracking-wider text-neutral-500 uppercase dark:border-neutral-800 dark:bg-neutral-800/60">
                            {t('admin.shipping.railCount', { n: visible.length })}
                        </p>
                        {visible.map((carrier) => (
                            <button
                                key={carrier.key}
                                type="button"
                                onClick={() => setSelectedKey(carrier.key)}
                                aria-current={carrier.key === current.key}
                                // border-s-2, so the selection marker flips with reading direction.
                                className={`flex w-full items-center gap-2.5 border-s-2 border-b border-neutral-100 px-3 py-2 text-start text-sm transition-colors last:border-b-0 dark:border-neutral-800 ${
                                    carrier.key === current.key
                                        ? 'border-s-brand-gold bg-[#133c40] text-neutral-100'
                                        : 'border-s-transparent text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800/60'
                                } ${carrier.is_enabled ? '' : 'opacity-60'}`}
                            >
                                <CarrierLogo carrier={carrier} logo={logoFor(carrier)} className="h-6 w-6 text-[9px]" />
                                <span className="min-w-0 flex-1 truncate">{prettyName(carrier.name)}</span>
                                <span className="font-mono text-xs text-neutral-400 tabular-nums" dir="ltr">
                                    {carrier.cheapest === null ? '—' : carrier.cheapest.toFixed(2)}
                                </span>
                            </button>
                        ))}
                    </div>

                    <CarrierDetail
                        carrier={current}
                        logo={logoFor(current)}
                        manage={manage}
                        money={money}
                        delivery={delivery}
                        onClose={() => setSelectedKey(null)}
                        onEdit={() => setEditing(current)}
                    />
                </div>
            )}

            <EditCarrierModal carrier={editing} onClose={() => setEditing(null)} />
        </AdminLayout>
    );
}

/** The carrier's logo from OTO, or its initials when none came back. */
function CarrierLogo({ carrier, logo, className = 'h-10 w-10 text-xs' }: { carrier: Carrier; logo: string | null; className?: string }) {
    if (logo) {
        return <img src={logo} alt="" loading="lazy" className={`${className} shrink-0 rounded-lg bg-white object-contain p-0.5`} />;
    }

    const initials = carrier.name
        .replace(/[^A-Za-z ]/g, '')
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((w) => w[0].toUpperCase())
        .join('');

    return (
        <span className={`${className} grid shrink-0 place-items-center rounded-lg bg-neutral-800 font-mono font-semibold text-neutral-400`}>
            {initials || <Package className="h-4 w-4" />}
        </span>
    );
}

/** The on/off control, or a read-only pill for staff without `shipping.manage`. */
function EnableControl({ carrier, manage }: { carrier: Carrier; manage: boolean }) {
    const { t } = useAdminT();
    const label = carrier.is_enabled ? t('admin.shipping.on') : t('admin.shipping.off');
    const icon = carrier.is_enabled ? Zap : CircleSlash;
    const tone = carrier.is_enabled ? 'active' : 'idle';

    if (!manage) {
        return (
            <StatusPill tone={tone} icon={icon}>
                {label}
            </StatusPill>
        );
    }

    return (
        <StatusToggle
            tone={tone}
            icon={icon}
            label={label}
            url={`/admin/shipping/${carrier.id}/toggle`}
            hint={carrier.is_enabled ? t('admin.shipping.turnOffHint') : t('admin.shipping.turnOnHint')}
        />
    );
}

/**
 * One card in the grid.
 *
 * ⚠️ The card is a `div` with a click handler, NOT a button, because it contains
 * the enable toggle and a button inside a button is invalid HTML. The carrier name
 * is the real button, so the card stays reachable and operable by keyboard, and
 * the whole-card click is a mouse convenience on top. Same shape as the clickable
 * rows on the contact-messages page.
 */
function CarrierCard({
    carrier,
    logo,
    manage,
    delivery,
    onOpen,
}: {
    carrier: Carrier;
    logo: string | null;
    manage: boolean;
    delivery: Delivery;
    onOpen: () => void;
}) {
    const { t } = useAdminT();
    const eta = delivery(carrier.services[0]?.estimated_delivery ?? null);

    return (
        <div
            onClick={onOpen}
            className={`cursor-pointer rounded-xl border border-neutral-200 bg-white p-3.5 transition-colors hover:border-neutral-400 dark:border-neutral-800 dark:bg-neutral-900 dark:hover:border-neutral-600 ${
                carrier.is_enabled ? '' : 'opacity-60'
            }`}
        >
            <div className="flex items-center gap-2.5">
                <CarrierLogo carrier={carrier} logo={logo} className="h-9 w-9 text-[10px]" />
                <div className="min-w-0 flex-1">
                    <button
                        type="button"
                        onClick={(e) => {
                            e.stopPropagation();
                            onOpen();
                        }}
                        className="focus-visible:ring-brand-gold/60 block max-w-full truncate rounded text-start text-sm font-medium text-neutral-100 focus:outline-none focus-visible:ring-2"
                    >
                        {prettyName(carrier.name)}
                    </button>
                    {eta && <span className="block truncate text-xs text-neutral-500">{eta}</span>}
                </div>
            </div>

            <p className="mt-3 flex items-baseline gap-1.5 font-mono text-xl text-neutral-100 tabular-nums" dir="ltr">
                {carrier.cheapest === null ? '—' : carrier.cheapest.toFixed(2)}
                <span className="text-xs font-normal text-neutral-500">{carrier.currency === 'SAR' ? t('admin.common.sar') : carrier.currency}</span>
            </p>

            <div className="mt-3 flex items-center justify-between gap-2">
                <span className={`truncate text-xs ${carrier.available ? 'text-neutral-500' : 'text-amber-400'}`}>
                    {carrier.available ? t('admin.shipping.availableNow') : t('admin.shipping.notOffered')}
                </span>
                {/* Stops the toggle's click reaching the card, which would otherwise
                    switch the carrier AND open it in the same press. */}
                <span onClick={(e) => e.stopPropagation()}>
                    <EnableControl carrier={carrier} manage={manage} />
                </span>
            </div>
        </div>
    );
}

/** The opened carrier: its services, terms, and who to contact. */
function CarrierDetail({
    carrier,
    logo,
    manage,
    money,
    delivery,
    onClose,
    onEdit,
}: {
    carrier: Carrier;
    logo: string | null;
    manage: boolean;
    money: Money;
    delivery: Delivery;
    onClose: () => void;
    onEdit: () => void;
}) {
    const { t, i18n } = useAdminT();
    const first = carrier.services[0];

    // Only the terms OTO actually returned; a grid of dashes says nothing.
    const terms = [
        { key: 'freeWeight', value: first?.max_free_weight != null ? `${first.max_free_weight} kg` : null },
        { key: 'extraKg', value: first?.extra_weight_per_kg != null ? money(first.extra_weight_per_kg, carrier.currency) : null },
        { key: 'returnFee', value: first?.return_fee != null ? money(first.return_fee, carrier.currency) : null },
        { key: 'cutOff', value: first?.pickup_cut_off ?? null },
    ].filter((term) => term.value !== null);

    return (
        <div className={`${CARD} p-5`}>
            <div className="flex items-start gap-3">
                <CarrierLogo carrier={carrier} logo={logo} className="h-11 w-11 text-sm" />
                <div className="min-w-0 flex-1">
                    <h2 className="truncate text-lg font-semibold text-neutral-100">{prettyName(carrier.name)}</h2>
                    {carrier.name_ar && (
                        // dir on the SPAN, not the <p>: on a block element it would
                        // also right-align the text, throwing the Arabic name to the
                        // far edge of the header instead of under the Latin one.
                        <p className="truncate text-xs text-neutral-500">
                            <span dir="rtl">{carrier.name_ar}</span>
                        </p>
                    )}
                    <div className="mt-2 flex flex-wrap items-center gap-2.5">
                        <EnableControl carrier={carrier} manage={manage} />
                        <span className={`text-xs ${carrier.available ? 'text-neutral-500' : 'text-amber-400'}`}>
                            {carrier.available ? t('admin.shipping.availableNow') : t('admin.shipping.notOffered')}
                        </span>
                    </div>
                </div>
                {/* No ms-auto needed: the name column already flexes, so this sits at
                    the inline end in both directions. */}
                <button
                    type="button"
                    onClick={onClose}
                    aria-label={t('admin.shipping.backToAll')}
                    title={t('admin.shipping.backToAll')}
                    className="focus-visible:ring-brand-gold/60 grid h-8 w-8 shrink-0 place-items-center rounded-lg border border-neutral-300 text-neutral-400 transition-colors hover:text-neutral-100 focus:outline-none focus-visible:ring-2 dark:border-neutral-700"
                >
                    <X className="h-4 w-4" />
                </button>
            </div>

            <p className="mt-5 mb-2 text-[11px] font-semibold tracking-wider text-neutral-500 uppercase">{t('admin.shipping.services')}</p>
            <div className="overflow-hidden rounded-lg border border-neutral-200 dark:border-neutral-800">
                {carrier.services.length === 0 && (
                    <p className="px-3.5 py-3 text-sm text-neutral-500">
                        {carrier.last_seen_at
                            ? t('admin.shipping.lastSeen', {
                                  when: new Date(carrier.last_seen_at).toLocaleDateString(i18n.language === 'ar' ? 'ar-SA' : 'en-GB'),
                              })
                            : t('admin.shipping.neverSeen')}
                    </p>
                )}
                {carrier.services.map((service, i) => (
                    <div
                        key={`${service.id ?? 'x'}-${i}`}
                        className="flex flex-wrap items-baseline gap-x-3 gap-y-1 border-b border-neutral-100 px-3.5 py-2.5 text-sm last:border-b-0 dark:border-neutral-800"
                    >
                        <span className="flex-1 text-neutral-200">{service.service ?? prettyName(carrier.name)}</span>
                        {service.estimated_delivery && <span className="text-xs text-neutral-500">{delivery(service.estimated_delivery)}</span>}
                        {service.pickup_cut_off && (
                            <span className="inline-flex items-center gap-1 text-xs text-neutral-500">
                                <Clock className="h-3 w-3" />
                                <span dir="ltr">{service.pickup_cut_off}</span>
                            </span>
                        )}
                        <span className="font-mono text-sm font-medium text-neutral-200 tabular-nums" dir="ltr">
                            {money(service.price, service.currency) ?? '—'}
                        </span>
                    </div>
                ))}
            </div>

            {terms.length > 0 && (
                <>
                    <p className="mt-5 mb-2 text-[11px] font-semibold tracking-wider text-neutral-500 uppercase">{t('admin.shipping.termsTitle')}</p>
                    <div className="flex flex-wrap gap-2">
                        {terms.map((term) => (
                            <div
                                key={term.key}
                                className="min-w-28 rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2 dark:border-neutral-800 dark:bg-neutral-950/40"
                            >
                                <dt className="text-[10px] tracking-wide text-neutral-500 uppercase">{t(`admin.shipping.terms.${term.key}`)}</dt>
                                <dd className="mt-0.5 font-mono text-sm text-neutral-200 tabular-nums" dir="ltr">
                                    {term.value}
                                </dd>
                            </div>
                        ))}
                    </div>
                </>
            )}

            <p className="mt-5 mb-2 text-[11px] font-semibold tracking-wider text-neutral-500 uppercase">{t('admin.shipping.contact')}</p>
            <div className="flex flex-wrap items-center gap-x-4 gap-y-2 text-xs">
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
                        {/* Copy as well as dial: the panel is used on a laptop, where a
                            tel: link usually opens nothing. */}
                        <a href={`tel:${carrier.support_phone.replace(/\s/g, '')}`} className="hover:text-neutral-200" dir="ltr">
                            {carrier.support_phone}
                        </a>
                        <CopyText value={carrier.support_phone} copyLabel={t('admin.common.copy')} copiedLabel={t('admin.common.copied')} />
                    </span>
                )}
                {carrier.support_email && (
                    <span className="inline-flex items-center gap-1.5 text-neutral-400">
                        <Mail className="h-3.5 w-3.5" />
                        <span dir="ltr">{carrier.support_email}</span>
                        <CopyText value={carrier.support_email} copyLabel={t('admin.common.copy')} copiedLabel={t('admin.common.copied')} />
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

            {!carrier.support_phone && !carrier.support_email && (
                <p className="mt-2 text-xs text-neutral-500 italic">{t('admin.shipping.noContact')}</p>
            )}

            {carrier.notes && <p className="mt-4 text-xs text-neutral-500 italic">{carrier.notes}</p>}

            {manage && (
                <div className="mt-5 flex justify-end border-t border-neutral-200 pt-4 dark:border-neutral-800">
                    <Button size="sm" variant="secondary" icon={Pencil} onClick={onEdit}>
                        {t('admin.shipping.editDetails')}
                    </Button>
                </div>
            )}
        </div>
    );
}

/**
 * The contact card editor.
 *
 * Keyed on the carrier id so switching carriers remounts it with fresh values.
 * Without that, useForm keeps the first carrier's data and the admin would
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
