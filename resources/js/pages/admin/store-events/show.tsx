import { Head, router, useForm } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    ExternalLink,
    ImageOff,
    ImageUp,
    Images,
    PackagePlus,
    Pencil,
    Plus,
    Power,
    Search,
    Smartphone,
    Trash2,
    X,
} from 'lucide-react';
import { type FormEvent, type KeyboardEvent, type ReactNode, useEffect, useMemo, useRef, useState } from 'react';

import Button from '@/components/admin/button';
import ConfirmDeleteButton from '@/components/admin/confirm-delete-button';
import StatusBadge from '@/components/admin/status-badge';
import StatusToggle from '@/components/admin/status-toggle';
import StatusPill from '@/components/status-pill';
import { useCan } from '@/hooks/use-can';
import { useAdminT } from '@/i18n/use-admin-t';
import AdminLayout from '@/layouts/admin-layout';
import { CARD } from '@/lib/admin-ui';
import { normalize } from '@/lib/search';

import EventFields, { type EventForm, toInput } from './event-fields';
import { type EventRow } from './index';
import { riyadhDay, riyadhLabel, timing } from './time';

/**
 * One store event, laid out as a campaign rather than a form.
 *
 * 🔑 The header answers the question someone opens this page with — is it live,
 * how long is left, what is showing right now — before anything asks them to edit.
 * The work then splits into three tabs (hero banners, offers, details) because
 * they are three different jobs done at different times: arranging artwork,
 * managing what is on sale, and setting up the campaign once. Controls used
 * rarely (a banner's own dates, an offer's badge and artwork, the add and create
 * forms) stay closed until asked for, so every list reads as a list.
 *
 * An ATTACHED offer's campaign data lives on the pivot, so attaching never edits a
 * product. A CREATED offer is a campaign-only product, born with `available_until`
 * equal to the event's end so it leaves the store on its own.
 */

interface Offer {
    product_id: number;
    name_ar: string;
    name_en: string | null;
    sku: string;
    slug: string;
    price: number;
    sale_price: number | null;
    on_sale: boolean;
    sale_state: string | null;
    sale_ends_at: string | null;
    available_until: string | null;
    stock: number;
    is_active: boolean;
    badge_ar: string | null;
    badge_en: string | null;
    banner_image: string | null;
    has_banner: boolean;
    preview: string | null;
    sort_order: number;
}

interface Banner {
    id: number;
    image: string | null;
    image_mobile: string | null;
    product_id: number | null;
    alt_ar: string | null;
    alt_en: string | null;
    starts_at: string | null;
    ends_at: string | null;
    is_active: boolean;
    state: string;
}

interface PoolProduct {
    id: number;
    name_ar: string;
    name_en: string | null;
    sku: string;
    price: number;
    on_sale: boolean;
    image: string | null;
}

type EventDetail = EventRow & { offers: Offer[]; banners: Banner[] };

const TABS = ['banners', 'offers', 'details'] as const;
type Tab = (typeof TABS)[number];

/** Remembered per browser, so saving a badge does not bounce the admin back to the first tab. */
const TAB_KEY = 'admin.storeEvents.tab';

/** Below this, an offer's stock is worth a second look while a campaign runs. */
const LOW_STOCK = 10;

const INPUT =
    'w-full rounded-lg border border-neutral-700 bg-neutral-950 px-3 py-2 text-sm text-neutral-100 outline-none placeholder:text-neutral-600 focus:border-brand-gold focus:ring-1 focus:ring-brand-gold';

const INPUT_SM =
    'rounded-md border border-neutral-700 bg-neutral-950 px-2.5 py-1.5 text-xs text-neutral-100 outline-none focus:border-brand-gold focus:ring-1 focus:ring-brand-gold';

const FILE =
    'block w-full text-sm text-neutral-400 file:me-3 file:rounded-md file:border-0 file:bg-neutral-800 file:px-3 file:py-1.5 file:text-sm file:text-neutral-100 hover:file:bg-neutral-700';

/** The only loud tone is the one someone must act on (a banner pointing at nothing live). */
const BANNER_TONE: Record<string, 'active' | 'idle' | 'done' | 'stopped' | 'attention'> = {
    live: 'active',
    scheduled: 'idle',
    ended: 'done',
    off: 'stopped',
    event_paused: 'stopped',
    offer_hidden: 'attention',
    no_offers: 'attention',
};

function Field({ label, hint, error, children }: { label: string; hint?: string; error?: string; children: ReactNode }) {
    return (
        <label className="block text-sm">
            <span className="mb-1 block text-xs font-medium text-neutral-300">{label}</span>
            {children}
            {hint && <span className="mt-1 block text-[11px] text-neutral-500">{hint}</span>}
            {error && <span className="mt-1 block text-xs text-red-400">{error}</span>}
        </label>
    );
}

/** A bordered sub-panel for the add/create forms, opened in place above a list. */
function Panel({ title, onClose, children }: { title: string; onClose: () => void; children: ReactNode }) {
    const { t } = useAdminT();

    return (
        <div className="mb-5 rounded-lg border border-neutral-700 bg-neutral-950/60 p-4">
            <div className="mb-4 flex items-center justify-between gap-3">
                <h3 className="text-sm font-semibold text-neutral-100">{title}</h3>
                <button
                    type="button"
                    onClick={onClose}
                    aria-label={t('admin.storeEvents.createOffer.cancel')}
                    className="rounded-md p-1 text-neutral-500 hover:bg-neutral-800 hover:text-neutral-200"
                >
                    <X className="h-4 w-4" />
                </button>
            </div>
            {children}
        </div>
    );
}

/** Up/down rather than drag: keyboard-reachable, and the lists are a handful long. */
function MoveButtons({
    index,
    total,
    onMove,
    upLabel,
    downLabel,
}: {
    index: number;
    total: number;
    onMove: (from: number, to: number) => void;
    upLabel: string;
    downLabel: string;
}) {
    return (
        <div className="flex shrink-0 flex-col">
            <button
                type="button"
                disabled={index === 0}
                onClick={() => onMove(index, index - 1)}
                aria-label={upLabel}
                className="rounded p-0.5 text-neutral-500 hover:bg-neutral-800 hover:text-neutral-200 disabled:pointer-events-none disabled:opacity-25"
            >
                <ArrowUp className="h-3.5 w-3.5" />
            </button>
            <button
                type="button"
                disabled={index === total - 1}
                onClick={() => onMove(index, index + 1)}
                aria-label={downLabel}
                className="rounded p-0.5 text-neutral-500 hover:bg-neutral-800 hover:text-neutral-200 disabled:pointer-events-none disabled:opacity-25"
            >
                <ArrowDown className="h-3.5 w-3.5" />
            </button>
        </div>
    );
}

// ------------------------------------------------------------------ summary

function Summary({ event, onEdit }: { event: EventDetail; onEdit: () => void }) {
    const { t, i18n } = useAdminT();
    const lang = i18n.language;
    const time = timing(event.starts_at, event.ends_at);
    const liveBanners = event.banners.filter((b) => b.state === 'live').length;
    const liveOffers = event.offers.filter((o) => o.is_active).length;
    const units = event.offers.reduce((sum, o) => sum + o.stock, 0);

    const remaining =
        time.phase === 'upcoming'
            ? t('admin.storeEvents.summary.startsIn', { n: time.days })
            : time.phase === 'over'
              ? t('admin.storeEvents.summary.ended')
              : time.days <= 1
                ? t('admin.storeEvents.summary.lastDay')
                : t('admin.storeEvents.summary.daysLeft', { n: time.days });

    return (
        <section className={`${CARD} mb-6 overflow-hidden`}>
            {/* The event's own accent, as its homepage section wears it. */}
            <div className="h-1" style={{ background: event.accent }} aria-hidden />

            <div className="flex flex-col gap-5 p-5 lg:flex-row lg:items-start lg:justify-between">
                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                        <h1 className="text-xl font-semibold text-neutral-100">{event.name_ar}</h1>
                        <StatusBadge domain="storeEvent" value={event.state} />
                    </div>
                    {event.name_en && <p className="mt-0.5 text-sm text-neutral-400">{event.name_en}</p>}

                    <div className="mt-5 max-w-2xl">
                        <div className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 text-xs">
                            <span className="text-neutral-300 tabular-nums">
                                {riyadhLabel(event.starts_at, lang)} → {riyadhLabel(event.ends_at, lang)}
                                <span className="text-neutral-500"> · {t('admin.storeEvents.summary.riyadhTime')}</span>
                            </span>
                            <span className="font-semibold text-neutral-100">{remaining}</span>
                        </div>
                        <div
                            className="mt-2 h-1.5 overflow-hidden rounded-full bg-neutral-800"
                            role="progressbar"
                            aria-label={remaining}
                            aria-valuemin={0}
                            aria-valuemax={100}
                            aria-valuenow={Math.round(time.fraction * 100)}
                        >
                            <div
                                className="h-full rounded-full transition-[width] duration-300"
                                style={{ width: `${time.fraction * 100}%`, background: event.accent }}
                            />
                        </div>
                        <p className="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-neutral-400 tabular-nums">
                            <span>{t('admin.storeEvents.summary.bannersLive', { live: liveBanners, total: event.banners.length })}</span>
                            <span>{t('admin.storeEvents.summary.offersOnSale', { live: liveOffers, total: event.offers.length })}</span>
                            <span>{t('admin.storeEvents.summary.unitsInStock', { n: units })}</span>
                        </p>
                    </div>
                </div>

                <div className="flex shrink-0 flex-wrap items-center gap-2">
                    {/* Pause/resume is reversible in one click, so it needs no confirm. */}
                    <StatusToggle
                        tone={event.is_active ? 'active' : 'stopped'}
                        label={t(event.is_active ? 'admin.storeEvents.live' : 'admin.storeEvents.paused')}
                        url={`/admin/store-events/${event.id}/toggle`}
                    />
                    <Button size="sm" variant="secondary" icon={ExternalLink} href={`/shop?event=${event.id}`}>
                        {t('admin.storeEvents.summary.viewOnStore')}
                    </Button>
                    <Button size="sm" variant="secondary" icon={Pencil} onClick={onEdit}>
                        {t('admin.storeEvents.summary.editDetails')}
                    </Button>
                </div>
            </div>
        </section>
    );
}

// --------------------------------------------------------------------- tabs

function Tabs({ value, onChange, counts }: { value: Tab; onChange: (tab: Tab) => void; counts: Partial<Record<Tab, number>> }) {
    const { t } = useAdminT();
    const refs = useRef<Record<string, HTMLButtonElement | null>>({});

    // Arrow keys move between tabs, per the WAI-ARIA tabs pattern. Left/right are
    // swapped under RTL so "next" is always in the reading direction.
    //
    // Steps from the tab that HAS FOCUS rather than the selected one: normally the
    // same tab (roving tabindex), but a click or script can focus another, and
    // stepping from `value` then jumps somewhere the user did not expect.
    const onKeyDown = (e: KeyboardEvent<HTMLDivElement>) => {
        const rtl = e.currentTarget.closest('[dir]')?.getAttribute('dir') === 'rtl';
        const step = e.key === 'ArrowRight' ? (rtl ? -1 : 1) : e.key === 'ArrowLeft' ? (rtl ? 1 : -1) : 0;
        if (!step) return;
        e.preventDefault();
        const focused = TABS.findIndex((tab) => refs.current[tab] === document.activeElement);
        const from = focused >= 0 ? focused : TABS.indexOf(value);
        const next = TABS[(from + step + TABS.length) % TABS.length];
        onChange(next);
        refs.current[next]?.focus();
    };

    return (
        <div role="tablist" aria-label={t('admin.storeEvents.title')} onKeyDown={onKeyDown} className="mb-4 flex gap-1 border-b border-neutral-800">
            {TABS.map((tab) => {
                const selected = tab === value;
                return (
                    <button
                        key={tab}
                        ref={(el) => {
                            refs.current[tab] = el;
                        }}
                        id={`tab-${tab}`}
                        type="button"
                        role="tab"
                        aria-selected={selected}
                        aria-controls={`panel-${tab}`}
                        tabIndex={selected ? 0 : -1}
                        onClick={() => onChange(tab)}
                        className={`-mb-px flex items-center gap-2 border-b-2 px-3 py-2.5 text-sm font-medium transition-colors ${
                            selected ? 'border-brand-gold text-neutral-100' : 'border-transparent text-neutral-400 hover:text-neutral-200'
                        }`}
                    >
                        {t(`admin.storeEvents.tabs.${tab}`)}
                        {counts[tab] !== undefined && (
                            <span
                                className={`rounded-full px-1.5 text-[11px] tabular-nums ${selected ? 'bg-brand-gold/15 text-brand-gold' : 'bg-neutral-800 text-neutral-400'}`}
                            >
                                {counts[tab]}
                            </span>
                        )}
                    </button>
                );
            })}
        </div>
    );
}

// ------------------------------------------------------------------ banners

/** Link target: one of the event's own offers, or the event's catalogue page. */
function LinkSelect({
    value,
    offers,
    onChange,
    className = INPUT,
}: {
    value: number | '' | null;
    offers: Offer[];
    onChange: (id: number | null) => void;
    className?: string;
}) {
    const { t } = useAdminT();

    return (
        <select className={className} value={value ?? ''} onChange={(e) => onChange(e.target.value ? Number(e.target.value) : null)}>
            <option value="">{t('admin.storeEvents.banners.linkEvent')}</option>
            {offers.map((o) => (
                <option key={o.product_id} value={o.product_id}>
                    {o.name_ar}
                </option>
            ))}
        </select>
    );
}

function BannerRow({
    eventId,
    banner,
    offers,
    index,
    total,
    onMove,
}: {
    eventId: number;
    banner: Banner;
    offers: Offer[];
    index: number;
    total: number;
    onMove: (from: number, to: number) => void;
}) {
    const { t, i18n } = useAdminT();
    const base = `/admin/store-events/${eventId}/banners/${banner.id}`;
    const hasOwnDates = Boolean(banner.starts_at || banner.ends_at);
    const [editingDates, setEditingDates] = useState(false);
    const [starts, setStarts] = useState(toInput(banner.starts_at));
    const [ends, setEnds] = useState(toInput(banner.ends_at));
    const patch = (data: Record<string, string | number | boolean | null>, onSuccess?: () => void) =>
        router.patch(base, data, { preserveScroll: true, onSuccess });

    const window = hasOwnDates
        ? `${riyadhDay(banner.starts_at, i18n.language) || '…'} → ${riyadhDay(banner.ends_at, i18n.language) || '…'}`
        : t('admin.storeEvents.banners.followsEvent');

    return (
        <li className="flex flex-col gap-3 p-3 sm:flex-row sm:items-center">
            <div className="flex items-center gap-2.5">
                <MoveButtons
                    index={index}
                    total={total}
                    onMove={onMove}
                    upLabel={t('admin.storeEvents.banners.moveUp')}
                    downLabel={t('admin.storeEvents.banners.moveDown')}
                />
                <div className="aspect-[2/1] w-36 shrink-0 overflow-hidden rounded-md bg-neutral-800">
                    {banner.image && <img src={banner.image} alt="" className="h-full w-full object-cover" />}
                </div>
                {banner.image_mobile ? (
                    <div className="aspect-[4/5] w-9 shrink-0 overflow-hidden rounded bg-neutral-800">
                        <img src={banner.image_mobile} alt="" className="h-full w-full object-cover" />
                    </div>
                ) : (
                    <span
                        title={t('admin.storeEvents.banners.noMobile')}
                        className="flex aspect-[4/5] w-9 shrink-0 items-center justify-center rounded border border-dashed border-neutral-700"
                    >
                        <Smartphone className="h-3.5 w-3.5 text-neutral-600" aria-label={t('admin.storeEvents.banners.noMobile')} />
                    </span>
                )}
            </div>

            <div className="min-w-0 flex-1 space-y-2">
                <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                    <StatusPill tone={BANNER_TONE[banner.state] ?? 'idle'}>{t(`admin.storeEvents.banners.state.${banner.state}`)}</StatusPill>
                    <span className="text-xs text-neutral-400 tabular-nums">{window}</span>
                    <button
                        type="button"
                        onClick={() => setEditingDates((v) => !v)}
                        aria-expanded={editingDates}
                        className="text-xs text-neutral-500 underline decoration-neutral-700 underline-offset-4 hover:text-neutral-200"
                    >
                        {t('admin.storeEvents.banners.dates')}
                    </button>
                </div>
                <label className="flex items-center gap-2">
                    <span className="shrink-0 text-xs text-neutral-500">{t('admin.storeEvents.banners.link')}</span>
                    <LinkSelect
                        value={banner.product_id}
                        offers={offers}
                        onChange={(id) => patch({ product_id: id })}
                        className={`${INPUT_SM} w-full max-w-xs`}
                    />
                </label>

                {editingDates && (
                    <div className="grid gap-2 rounded-lg bg-neutral-950/60 p-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                        <Field label={t('admin.storeEvents.banners.startsAt')} hint={riyadhLabel(starts, i18n.language)}>
                            <input
                                className={INPUT_SM + ' w-full'}
                                type="datetime-local"
                                value={starts}
                                onChange={(e) => setStarts(e.target.value)}
                            />
                        </Field>
                        <Field label={t('admin.storeEvents.banners.endsAt')} hint={riyadhLabel(ends, i18n.language)}>
                            <input className={INPUT_SM + ' w-full'} type="datetime-local" value={ends} onChange={(e) => setEnds(e.target.value)} />
                        </Field>
                        <div className="flex gap-1.5 pb-5">
                            <Button
                                size="sm"
                                variant="primary"
                                onClick={() => patch({ starts_at: starts || null, ends_at: ends || null }, () => setEditingDates(false))}
                            >
                                {t('admin.storeEvents.banners.saveDates')}
                            </Button>
                            {hasOwnDates && (
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    onClick={() => patch({ starts_at: null, ends_at: null }, () => setEditingDates(false))}
                                >
                                    {t('admin.storeEvents.banners.clearDates')}
                                </Button>
                            )}
                        </div>
                        <p className="text-[11px] text-neutral-500 sm:col-span-3">{t('admin.storeEvents.banners.windowHint')}</p>
                    </div>
                )}
            </div>

            <div className="flex shrink-0 items-center gap-1.5 sm:justify-end">
                <Button size="sm" variant="secondary" icon={Power} onClick={() => patch({ is_active: !banner.is_active })}>
                    {banner.is_active ? t('admin.storeEvents.banners.switchOff') : t('admin.storeEvents.banners.switchOn')}
                </Button>
                <ConfirmDeleteButton
                    itemName={t('admin.storeEvents.banners.item', { n: index + 1 })}
                    onConfirm={() => router.delete(base, { preserveScroll: true })}
                    size="sm"
                />
            </div>
        </li>
    );
}

function AddBannerForm({ eventId, offers, onClose }: { eventId: number; offers: Offer[]; onClose: () => void }) {
    const { t, i18n } = useAdminT();
    const { data, setData, post, processing, errors } = useForm({
        image: null as File | null,
        image_mobile: null as File | null,
        product_id: '' as number | '',
        alt_ar: '',
        alt_en: '',
        starts_at: '',
        ends_at: '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        // Two files, so multipart POST.
        post(`/admin/store-events/${eventId}/banners`, { forceFormData: true, preserveScroll: true, onSuccess: onClose });
    };

    return (
        <Panel title={t('admin.storeEvents.banners.addTitle')} onClose={onClose}>
            <form onSubmit={submit}>
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label={t('admin.storeEvents.banners.desktop')} hint={t('admin.storeEvents.banners.desktopHint')} error={errors.image}>
                        <input className={FILE} type="file" accept="image/*" onChange={(e) => setData('image', e.target.files?.[0] ?? null)} />
                    </Field>
                    <Field label={t('admin.storeEvents.banners.mobile')} hint={t('admin.storeEvents.banners.mobileHint')} error={errors.image_mobile}>
                        <input className={FILE} type="file" accept="image/*" onChange={(e) => setData('image_mobile', e.target.files?.[0] ?? null)} />
                    </Field>
                    <Field label={t('admin.storeEvents.banners.link')} error={errors.product_id}>
                        <LinkSelect value={data.product_id} offers={offers} onChange={(id) => setData('product_id', id ?? '')} />
                    </Field>
                    <div className="hidden sm:block" />
                    <Field label={t('admin.storeEvents.banners.altAr')} hint={t('admin.storeEvents.banners.altHint')} error={errors.alt_ar}>
                        <input className={INPUT} dir="rtl" value={data.alt_ar} onChange={(e) => setData('alt_ar', e.target.value)} />
                    </Field>
                    <Field label={t('admin.storeEvents.banners.altEn')} error={errors.alt_en}>
                        <input className={INPUT} dir="ltr" value={data.alt_en} onChange={(e) => setData('alt_en', e.target.value)} />
                    </Field>
                    <Field label={t('admin.storeEvents.banners.startsAt')} hint={riyadhLabel(data.starts_at, i18n.language)} error={errors.starts_at}>
                        <input
                            className={INPUT}
                            type="datetime-local"
                            value={data.starts_at}
                            onChange={(e) => setData('starts_at', e.target.value)}
                        />
                    </Field>
                    <Field
                        label={t('admin.storeEvents.banners.endsAt')}
                        hint={riyadhLabel(data.ends_at, i18n.language) || t('admin.storeEvents.banners.windowHint')}
                        error={errors.ends_at}
                    >
                        <input className={INPUT} type="datetime-local" value={data.ends_at} onChange={(e) => setData('ends_at', e.target.value)} />
                    </Field>
                </div>

                <div className="mt-4 flex justify-end gap-2">
                    <Button variant="ghost" onClick={onClose}>
                        {t('admin.storeEvents.createOffer.cancel')}
                    </Button>
                    <Button type="submit" variant="primary" disabled={processing || !data.image}>
                        {t('admin.storeEvents.banners.addSubmit')}
                    </Button>
                </div>
            </form>
        </Panel>
    );
}

function BannersTab({ event }: { event: EventDetail }) {
    const { t } = useAdminT();
    const [adding, setAdding] = useState(event.banners.length === 0);
    const withoutPhone = event.banners.filter((b) => !b.image_mobile).length;

    const move = (from: number, to: number) => {
        const ids = event.banners.map((b) => b.id);
        const [moved] = ids.splice(from, 1);
        ids.splice(to, 0, moved);
        router.post(`/admin/store-events/${event.id}/banners/reorder`, { banner_ids: ids }, { preserveScroll: true });
    };

    return (
        <>
            <div className="mb-4 flex flex-wrap items-start justify-between gap-3">
                <p className="max-w-2xl text-xs leading-relaxed text-neutral-400">{t('admin.storeEvents.banners.hint')}</p>
                {!adding && (
                    <Button size="sm" variant="primary" icon={Plus} onClick={() => setAdding(true)}>
                        {t('admin.storeEvents.banners.add')}
                    </Button>
                )}
            </div>

            {adding && <AddBannerForm eventId={event.id} offers={event.offers} onClose={() => setAdding(false)} />}

            {/* Phone art only takes effect when EVERY banner has it (one shape per set,
                or the hero changes height as it rotates) — say so, rather than let a
                missing image silently switch the whole set to desktop art. */}
            {withoutPhone > 0 && event.banners.length > 0 && (
                <p className="mb-3 flex items-start gap-2 rounded-lg bg-amber-500/10 px-3 py-2 text-xs text-amber-200 ring-1 ring-amber-500/20">
                    <Smartphone className="mt-px h-3.5 w-3.5 shrink-0" />
                    {t('admin.storeEvents.banners.phoneFallback', { n: withoutPhone })}
                </p>
            )}

            {event.banners.length > 0 ? (
                <ol className="divide-y divide-neutral-800 rounded-lg border border-neutral-800">
                    {event.banners.map((banner, i) => (
                        <BannerRow
                            // Position in the key re-seeds the date inputs after a reorder.
                            key={`${banner.id}-${i}`}
                            eventId={event.id}
                            banner={banner}
                            offers={event.offers}
                            index={i}
                            total={event.banners.length}
                            onMove={move}
                        />
                    ))}
                </ol>
            ) : (
                !adding && (
                    <div className="rounded-lg border border-dashed border-neutral-700 px-6 py-10 text-center">
                        <Images className="mx-auto h-6 w-6 text-neutral-600" />
                        <p className="mt-3 text-sm font-medium text-neutral-200">{t('admin.storeEvents.banners.emptyTitle')}</p>
                        <p className="mx-auto mt-1 max-w-md text-xs text-neutral-500">{t('admin.storeEvents.banners.emptyHint')}</p>
                    </div>
                )
            )}
        </>
    );
}

// ------------------------------------------------------------------- offers

function OfferRow({
    eventId,
    offer,
    index,
    total,
    onMove,
}: {
    eventId: number;
    offer: Offer;
    index: number;
    total: number;
    onMove: (from: number, to: number) => void;
}) {
    const { t, i18n } = useAdminT();
    const [open, setOpen] = useState(false);
    const [badgeAr, setBadgeAr] = useState(offer.badge_ar ?? '');
    const [badgeEn, setBadgeEn] = useState(offer.badge_en ?? '');
    const fileRef = useRef<HTMLInputElement>(null);
    const base = `/admin/store-events/${eventId}/offers/${offer.product_id}`;
    const dirty = badgeAr !== (offer.badge_ar ?? '') || badgeEn !== (offer.badge_en ?? '');
    const badge = i18n.language === 'en' ? offer.badge_en || offer.badge_ar : offer.badge_ar;

    return (
        <li className="p-3">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div className="flex items-center gap-2.5">
                    <MoveButtons
                        index={index}
                        total={total}
                        onMove={onMove}
                        upLabel={t('admin.storeEvents.offer.moveUp')}
                        downLabel={t('admin.storeEvents.offer.moveDown')}
                    />
                    {/* 2:1, as the homepage card crops it. */}
                    <div className="relative aspect-[2/1] w-28 shrink-0 overflow-hidden rounded-md bg-neutral-800">
                        {offer.preview ? (
                            <img src={offer.preview} alt="" className="h-full w-full object-cover" />
                        ) : (
                            <ImageOff className="absolute inset-0 m-auto h-4 w-4 text-neutral-600" />
                        )}
                    </div>
                </div>

                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                        <span className="truncate font-medium text-neutral-100">{offer.name_ar}</span>
                        <span className="font-mono text-[11px] text-neutral-500">{offer.sku}</span>
                        {!offer.is_active && <StatusPill tone="stopped">{t('admin.storeEvents.offer.hidden')}</StatusPill>}
                        {badge && (
                            <span dir="auto" className="rounded-full bg-neutral-800 px-2 py-0.5 text-[11px] text-neutral-300">
                                {badge}
                            </span>
                        )}
                    </div>
                    <div className="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-neutral-400 tabular-nums">
                        <span>
                            <span className="text-sm font-semibold text-neutral-100">
                                {(offer.on_sale ? (offer.sale_price ?? offer.price) : offer.price).toFixed(2)}
                            </span>
                            {offer.on_sale && <span className="ms-1.5 text-neutral-500 line-through">{offer.price.toFixed(2)}</span>}
                        </span>
                        {/* The commonest way an event looks wrong on the storefront is a
                            discount that is only scheduled or already lapsed. */}
                        {offer.sale_state && <StatusBadge domain="discount" value={offer.sale_state} />}
                        <span className={offer.stock <= LOW_STOCK ? 'font-medium text-amber-300' : ''}>
                            {t('admin.storeEvents.offer.stock', { n: offer.stock })}
                        </span>
                        {offer.available_until && (
                            <span>{t('admin.storeEvents.offer.leavesAt', { date: riyadhLabel(offer.available_until, i18n.language) })}</span>
                        )}
                    </div>
                </div>

                <div className="flex shrink-0 items-center gap-1">
                    <Button size="sm" variant={open ? 'secondary' : 'ghost'} icon={Pencil} onClick={() => setOpen((v) => !v)}>
                        {t('admin.storeEvents.offersTab.edit')}
                    </Button>
                    <Button
                        size="sm"
                        variant="ghost"
                        icon={ExternalLink}
                        href={`/products/${offer.slug}`}
                        aria-label={t('admin.storeEvents.offer.viewOnStore')}
                    >
                        {''}
                    </Button>
                    <Button
                        size="sm"
                        variant="ghost"
                        icon={Trash2}
                        onClick={() => router.delete(base, { preserveScroll: true })}
                        aria-label={t('admin.storeEvents.offer.remove')}
                    >
                        {''}
                    </Button>
                </div>
            </div>

            {open && (
                <div className="mt-3 grid gap-4 rounded-lg bg-neutral-950/60 p-3 lg:grid-cols-[1fr_auto]">
                    <div>
                        <div className="grid gap-2 sm:grid-cols-2">
                            <input
                                className={INPUT}
                                dir="rtl"
                                value={badgeAr}
                                onChange={(e) => setBadgeAr(e.target.value)}
                                placeholder={t('admin.storeEvents.offer.badgeArPlaceholder')}
                                aria-label={t('admin.storeEvents.offer.badgeAr')}
                            />
                            <input
                                className={INPUT}
                                dir="ltr"
                                value={badgeEn}
                                onChange={(e) => setBadgeEn(e.target.value)}
                                placeholder={t('admin.storeEvents.offer.badgeEnPlaceholder')}
                                aria-label={t('admin.storeEvents.offer.badgeEn')}
                            />
                        </div>
                        <div className="mt-2 flex flex-wrap items-center justify-between gap-2">
                            <p className="text-[11px] text-neutral-500">{t('admin.storeEvents.offer.badgeHint')}</p>
                            <Button
                                size="sm"
                                variant="primary"
                                disabled={!dirty}
                                onClick={() => router.patch(base, { badge_ar: badgeAr || null, badge_en: badgeEn || null }, { preserveScroll: true })}
                            >
                                {t('admin.storeEvents.offer.save')}
                            </Button>
                        </div>
                    </div>

                    <div className="flex flex-col gap-2 lg:w-56">
                        <p className="text-[11px] text-neutral-500">{t('admin.storeEvents.offersTab.artworkHint')}</p>
                        <input
                            ref={fileRef}
                            type="file"
                            accept="image/*"
                            className="hidden"
                            aria-label={t('admin.storeEvents.offer.uploadArtwork')}
                            onChange={(e) => {
                                const file = e.target.files?.[0];
                                // Multipart, so POST — a PATCH body is not parsed by PHP.
                                if (file) router.post(`${base}/banner`, { banner: file }, { forceFormData: true, preserveScroll: true });
                                // Cleared so re-picking the SAME file fires onChange again.
                                e.target.value = '';
                            }}
                        />
                        <div className="flex flex-wrap gap-1.5">
                            <Button size="sm" variant="secondary" icon={ImageUp} onClick={() => fileRef.current?.click()}>
                                {t('admin.storeEvents.offer.uploadArtwork')}
                            </Button>
                            {offer.has_banner && (
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    icon={ImageOff}
                                    onClick={() => router.delete(`${base}/banner`, { preserveScroll: true })}
                                >
                                    {t('admin.storeEvents.offer.removeArtwork')}
                                </Button>
                            )}
                        </div>
                    </div>
                </div>
            )}
        </li>
    );
}

function AddExisting({ eventId, pool, onClose }: { eventId: number; pool: PoolProduct[]; onClose: () => void }) {
    const { t } = useAdminT();
    const [query, setQuery] = useState('');

    // Arabic-folded matching, reusing the storefront search's normalizer so «جده»
    // finds «جدة» here too. One normalizer, not a second copy that drifts.
    const matches = useMemo(() => {
        const q = normalize(query);
        if (!q) return pool.slice(0, 8);
        return pool.filter((p) => normalize(`${p.name_ar} ${p.name_en ?? ''} ${p.sku}`).includes(q)).slice(0, 8);
    }, [pool, query]);

    return (
        <Panel title={t('admin.storeEvents.offersTab.addExisting')} onClose={onClose}>
            <p className="mb-3 text-xs text-neutral-500">{t('admin.storeEvents.addOfferHint')}</p>
            <div className="relative">
                <Search className="pointer-events-none absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-500" />
                <input
                    className={`${INPUT} ps-9`}
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                    placeholder={t('admin.storeEvents.searchProducts')}
                    aria-label={t('admin.storeEvents.searchProducts')}
                    autoFocus
                />
            </div>
            <div className="mt-3 grid gap-2 sm:grid-cols-2">
                {matches.map((p) => (
                    <button
                        key={p.id}
                        type="button"
                        onClick={() => router.post(`/admin/store-events/${eventId}/offers`, { product_id: p.id }, { preserveScroll: true })}
                        className="flex items-center gap-3 rounded-lg border border-neutral-800 p-2 text-start transition-colors hover:border-neutral-600 hover:bg-neutral-800/50"
                    >
                        {p.image ? (
                            <img src={p.image} alt="" className="h-10 w-10 shrink-0 rounded object-cover" />
                        ) : (
                            <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded bg-neutral-800">
                                <ImageOff className="h-4 w-4 text-neutral-600" />
                            </span>
                        )}
                        <span className="min-w-0 flex-1">
                            <span className="block truncate text-sm text-neutral-100">{p.name_ar}</span>
                            <span className="block font-mono text-[11px] text-neutral-500">
                                {p.sku} · {p.price.toFixed(2)}
                            </span>
                        </span>
                        <Plus className="h-4 w-4 shrink-0 text-neutral-500" />
                    </button>
                ))}
                {matches.length === 0 && (
                    <p className="col-span-full py-4 text-center text-sm text-neutral-500">{t('admin.storeEvents.noProducts')}</p>
                )}
            </div>
        </Panel>
    );
}

/**
 * Create a campaign-only product (a bundle) and attach it, in one step. Short on
 * purpose — name, contents, price, stock, one photo; the rest is the full editor's.
 */
function CreateOfferForm({ eventId, endsAt, onClose }: { eventId: number; endsAt: string; onClose: () => void }) {
    const { t, i18n } = useAdminT();
    const { data, setData, post, processing, errors } = useForm({
        name_ar: '',
        name_en: '',
        description_ar: '',
        description_en: '',
        price: '',
        stock: '',
        image: null as File | null,
    });

    const preview = useMemo(() => (data.image ? URL.createObjectURL(data.image) : null), [data.image]);
    useEffect(
        () => () => {
            if (preview) URL.revokeObjectURL(preview);
        },
        [preview],
    );

    const submit = (e: FormEvent) => {
        e.preventDefault();
        // Multipart (a photo), so POST with FormData.
        post(`/admin/store-events/${eventId}/offers/new`, { forceFormData: true, preserveScroll: true, onSuccess: onClose });
    };

    return (
        <Panel title={t('admin.storeEvents.createOffer.title')} onClose={onClose}>
            <p className="mb-4 text-xs text-neutral-500">{t('admin.storeEvents.createOffer.hint', { date: riyadhLabel(endsAt, i18n.language) })}</p>
            <form onSubmit={submit}>
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label={t('admin.storeEvents.createOffer.nameAr')} error={errors.name_ar}>
                        <input className={INPUT} dir="rtl" value={data.name_ar} onChange={(e) => setData('name_ar', e.target.value)} />
                    </Field>
                    <Field label={t('admin.storeEvents.createOffer.nameEn')} error={errors.name_en}>
                        <input className={INPUT} dir="ltr" value={data.name_en} onChange={(e) => setData('name_en', e.target.value)} />
                    </Field>
                    <Field label={t('admin.storeEvents.createOffer.descriptionAr')} error={errors.description_ar}>
                        <textarea
                            className={INPUT}
                            dir="rtl"
                            rows={3}
                            value={data.description_ar}
                            onChange={(e) => setData('description_ar', e.target.value)}
                        />
                    </Field>
                    <Field label={t('admin.storeEvents.createOffer.descriptionEn')} error={errors.description_en}>
                        <textarea
                            className={INPUT}
                            dir="ltr"
                            rows={3}
                            value={data.description_en}
                            onChange={(e) => setData('description_en', e.target.value)}
                        />
                    </Field>
                    <div className="grid grid-cols-2 gap-4">
                        <Field label={t('admin.storeEvents.createOffer.price')} error={errors.price}>
                            <input
                                className={INPUT}
                                type="number"
                                min="0"
                                step="0.01"
                                value={data.price}
                                onChange={(e) => setData('price', e.target.value)}
                            />
                        </Field>
                        <Field label={t('admin.storeEvents.createOffer.stock')} error={errors.stock}>
                            <input className={INPUT} type="number" min="0" value={data.stock} onChange={(e) => setData('stock', e.target.value)} />
                        </Field>
                    </div>
                    <div className="flex items-start gap-3">
                        <div className="min-w-0 flex-1">
                            <Field
                                label={t('admin.storeEvents.createOffer.image')}
                                hint={t('admin.storeEvents.createOffer.imageHint')}
                                error={errors.image}
                            >
                                <input
                                    className={FILE}
                                    type="file"
                                    accept="image/*"
                                    onChange={(e) => setData('image', e.target.files?.[0] ?? null)}
                                />
                            </Field>
                        </div>
                        {preview && <img src={preview} alt="" className="aspect-square w-16 shrink-0 rounded-md object-cover" />}
                    </div>
                </div>

                <div className="mt-4 flex justify-end gap-2">
                    <Button variant="ghost" onClick={onClose}>
                        {t('admin.storeEvents.createOffer.cancel')}
                    </Button>
                    <Button type="submit" variant="primary" disabled={processing}>
                        {t('admin.storeEvents.createOffer.submit')}
                    </Button>
                </div>
            </form>
        </Panel>
    );
}

function OffersTab({ event, pool, canCreate }: { event: EventDetail; pool: PoolProduct[]; canCreate: boolean }) {
    const { t } = useAdminT();
    const [panel, setPanel] = useState<null | 'existing' | 'create'>(null);

    const move = (from: number, to: number) => {
        const ids = event.offers.map((o) => o.product_id);
        const [moved] = ids.splice(from, 1);
        ids.splice(to, 0, moved);
        router.post(`/admin/store-events/${event.id}/offers/reorder`, { product_ids: ids }, { preserveScroll: true });
    };

    return (
        <>
            <div className="mb-4 flex flex-wrap items-start justify-between gap-3">
                <p className="max-w-2xl text-xs leading-relaxed text-neutral-400">{t('admin.storeEvents.offersTab.hint')}</p>
                <div className="flex flex-wrap gap-2">
                    <Button size="sm" variant="secondary" icon={Plus} onClick={() => setPanel('existing')}>
                        {t('admin.storeEvents.offersTab.addExisting')}
                    </Button>
                    {/* The endpoint also needs products.create, so only offer what will work. */}
                    {canCreate && (
                        <Button size="sm" variant="primary" icon={PackagePlus} onClick={() => setPanel('create')}>
                            {t('admin.storeEvents.createOffer.open')}
                        </Button>
                    )}
                </div>
            </div>

            {panel === 'existing' && <AddExisting eventId={event.id} pool={pool} onClose={() => setPanel(null)} />}
            {panel === 'create' && <CreateOfferForm eventId={event.id} endsAt={event.ends_at} onClose={() => setPanel(null)} />}

            {event.offers.length > 0 ? (
                <ol className="divide-y divide-neutral-800 rounded-lg border border-neutral-800">
                    {event.offers.map((offer, i) => (
                        <OfferRow
                            // Position in the key re-seeds the badge inputs after a reorder.
                            key={`${offer.product_id}-${i}`}
                            eventId={event.id}
                            offer={offer}
                            index={i}
                            total={event.offers.length}
                            onMove={move}
                        />
                    ))}
                </ol>
            ) : (
                panel === null && (
                    <div className="rounded-lg border border-dashed border-neutral-700 px-6 py-10 text-center">
                        <PackagePlus className="mx-auto h-6 w-6 text-neutral-600" />
                        <p className="mt-3 text-sm font-medium text-neutral-200">{t('admin.storeEvents.offersTab.emptyTitle')}</p>
                        <p className="mx-auto mt-1 max-w-md text-xs text-neutral-500">{t('admin.storeEvents.offersTab.emptyHint')}</p>
                    </div>
                )
            )}
        </>
    );
}

// --------------------------------------------------------------------- page

export default function StoreEventShow({
    event,
    pool,
    accentPresets,
}: {
    event: EventDetail;
    pool: PoolProduct[];
    accentPresets: Record<string, string>;
}) {
    const { t } = useAdminT();
    const canCreateOffers = useCan()('products.create');
    const [tab, setTab] = useState<Tab>(() => {
        try {
            const saved = localStorage.getItem(TAB_KEY);
            return (TABS as readonly string[]).includes(saved ?? '') ? (saved as Tab) : 'banners';
        } catch {
            return 'banners';
        }
    });
    useEffect(() => {
        try {
            localStorage.setItem(TAB_KEY, tab);
        } catch {
            // Storage blocked (private mode): the tab just is not remembered.
        }
    }, [tab]);

    const [form, setForm] = useState<EventForm>({
        name_ar: event.name_ar,
        name_en: event.name_en ?? '',
        subtitle_ar: event.subtitle_ar ?? '',
        subtitle_en: event.subtitle_en ?? '',
        starts_at: toInput(event.starts_at),
        ends_at: toInput(event.ends_at),
        accent_color: event.accent_color ?? '',
        is_active: event.is_active,
        sort_order: event.sort_order,
    });

    return (
        <AdminLayout>
            <Head title={event.name_ar} />

            <div className="mb-3">
                <Button size="sm" variant="ghost" href="/admin/store-events">
                    {t('admin.storeEvents.backToList')}
                </Button>
            </div>

            <Summary event={event} onEdit={() => setTab('details')} />

            <Tabs value={tab} onChange={setTab} counts={{ banners: event.banners.length, offers: event.offers.length }} />

            <div role="tabpanel" id={`panel-${tab}`} aria-labelledby={`tab-${tab}`} className={`${CARD} p-5`}>
                {tab === 'banners' && <BannersTab event={event} />}
                {tab === 'offers' && <OffersTab event={event} pool={pool} canCreate={canCreateOffers} />}
                {tab === 'details' && (
                    <>
                        <EventFields form={form} onChange={setForm} accentPresets={accentPresets} />
                        <div className="mt-6 flex justify-end border-t border-neutral-800 pt-4">
                            <Button
                                variant="primary"
                                onClick={() => router.put(`/admin/store-events/${event.id}`, { ...form }, { preserveScroll: true })}
                            >
                                {t('admin.storeEvents.saveEvent')}
                            </Button>
                        </div>
                    </>
                )}
            </div>
        </AdminLayout>
    );
}
