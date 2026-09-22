import Select from '@/components/admin/select';
import { useEffect, useState } from 'react';

export interface LinkTarget {
    value: string;
    ar: string;
    en: string | null;
}

export interface LinkTargets {
    categories: LinkTarget[];
    products: LinkTarget[];
    events: LinkTarget[];
}

type Kind = 'none' | 'shop' | 'category' | 'product' | 'event' | 'url';

/**
 * Where a banner goes when it is tapped, chosen rather than typed.
 *
 * 🔴 It was a free-text field asking for a PATH ("/shop?category=dates"), which
 * assumes the client thinks in URLs. They do not, and the failure is silent: a
 * typo produces a banner that looks fine in the admin and 404s for every shopper.
 * Picking from real categories, products and campaigns makes a wrong value
 * impossible to express.
 *
 * 🔑 The stored value is still a plain path string, so nothing downstream
 * changed — the storefront, the payload and every existing slide are untouched.
 * This is purely a better way to produce and read that one string, which is why
 * `parse` and `build` have to be exact inverses.
 */

/**
 * ⚠️ `decodeURIComponent` THROWS on a lone `%`, and slugs here are Arabic, so a
 * value that was stored raw and happens to contain one would take the whole
 * dialog down. Falls back to the string as written.
 */
function decode(value: string): string {
    try {
        return decodeURIComponent(value);
    } catch {
        return value;
    }
}

/** Turn a stored href back into the choice that produced it. */
export function parseHref(href: string | null): { kind: Kind; value: string } {
    const h = (href ?? '').trim();

    if (!h) return { kind: 'none', value: '' };
    if (/^(https?:)?\/\//i.test(h)) return { kind: 'url', value: h };

    const category = h.match(/^\/shop\?category=(.+)$/);
    if (category) return { kind: 'category', value: decode(category[1]) };

    const event = h.match(/^\/shop\?event=(\d+)$/);
    if (event) return { kind: 'event', value: event[1] };

    const product = h.match(/^\/products\/(.+)$/);
    if (product) return { kind: 'product', value: decode(product[1]) };

    if (h === '/shop') return { kind: 'shop', value: '' };

    // ⚠️ Anything hand-written that does not match a known shape is kept as a
    // custom address rather than silently discarded. A slide saved before this
    // picker existed must not lose its link just because we cannot categorise it.
    return { kind: 'url', value: h };
}

/** The inverse: turn a choice back into the path we store. */
export function buildHref(kind: Kind, value: string): string {
    switch (kind) {
        case 'shop':
            return '/shop';
        /*
         * 🔑 Slugs go in RAW, not percent-encoded. That is what the app already
         * stores for campaign banners (`/products/{slug}` in HeroBanners), so
         * encoding here would give two spellings of the same destination — and it
         * turned the admin's "shoppers will be taken to" line into an unreadable
         * run of %D8%A7. Safe because ArabicSlug only ever emits Arabic letters,
         * digits and hyphens.
         */
        case 'category':
            return value ? `/shop?category=${value}` : '';
        case 'product':
            return value ? `/products/${value}` : '';
        case 'event':
            return value ? `/shop?event=${value}` : '';
        case 'url':
            return value.trim();
        default:
            return '';
    }
}

export default function HeroLinkPicker({
    href,
    onChange,
    targets,
    lang,
    t,
}: {
    href: string | null;
    onChange: (href: string) => void;
    targets: LinkTargets;
    lang: string;
    t: (key: string, opts?: Record<string, unknown>) => string;
}) {
    const parsed = parseHref(href);

    /*
     * 🔴 The kind has to be LOCAL STATE, not derived from the href alone.
     * Choosing "A category" produces an empty href until a category is actually
     * picked, and an empty href parses back as "Nothing" — so a purely derived
     * kind snapped straight back and the second dropdown never appeared. The
     * href simply cannot express "kind chosen, value pending".
     *
     * Remounted per slide by the caller's `key`, so opening a different slide
     * still re-derives from what is stored.
     */
    const [kind, setKind] = useState<Kind>(parsed.kind);

    /*
     * 🔴 Adopt an href that arrives from OUTSIDE after mount — a restored draft,
     * or opening a slide. Local state is initialised at mount, so without this a
     * draft restored a moment later left the dropdown reading "Nothing" while the
     * href said /shop.
     *
     * Only when it parses to a real destination: an EMPTY href is ambiguous
     * between "nothing" and "kind chosen, value still pending", and local state
     * is the only thing that knows which.
     */
    useEffect(() => {
        const next = parseHref(href).kind;
        if (next !== 'none') setKind(next);
    }, [href]);

    // The stored href still wins for the VALUE, so an external change is picked up.
    const current = { kind, value: parsed.kind === kind ? parsed.value : '' };
    const label = (o: LinkTarget) => (lang === 'en' && o.en ? o.en : o.ar);

    const options: Record<string, LinkTarget[]> = {
        category: targets.categories,
        product: targets.products,
        event: targets.events,
    };
    const list = options[current.kind];

    // Only offer campaigns when one exists; an empty dropdown reads as broken.
    const kinds: Kind[] = ['none', 'shop', 'category', 'product', ...(targets.events.length ? (['event'] as Kind[]) : []), 'url'];

    /*
     * ⚠️ The shared admin <Select>, not a native one. A native <select> cannot
     * restyle the OS option highlight, so its open list showed a bright blue bar
     * against the dark panel — the client flagged exactly that. Only the free-text
     * URL field still uses this class.
     */
    const field = 'mt-1 w-full rounded-lg border border-neutral-700 bg-neutral-950 px-3 py-2 text-white outline-none focus:border-brand-gold';

    return (
        <div className="grid gap-3">
            <label className="block">
                <span className="text-sm text-neutral-300">{t('admin.hero.linkKind')}</span>
                <div className="mt-1">
                    <Select
                        value={current.kind}
                        onChange={(v) => {
                            const next = v as Kind;
                            setKind(next);
                            // 🔑 `shop` commits immediately since it needs no second
                            // choice; the rest clear the href, because a category slug
                            // is meaningless once the kind is "product". The banner is
                            // simply not a link until the second choice is made.
                            onChange(buildHref(next, ''));
                        }}
                        options={kinds.map((k) => ({ value: k, label: t(`admin.hero.linkKinds.${k}`) }))}
                    />
                </div>
            </label>

            {list && (
                <label className="block">
                    <span className="text-sm text-neutral-300">{t(`admin.hero.linkPick.${current.kind}`)}</span>
                    <div className="mt-1">
                        <Select
                            value={current.value}
                            onChange={(v) => onChange(buildHref(kind, v))}
                            placeholder={t('admin.hero.linkChoose')}
                            options={list.map((o) => ({ value: o.value, label: label(o) }))}
                        />
                    </div>
                </label>
            )}

            {current.kind === 'url' && (
                <label className="block">
                    <span className="text-sm text-neutral-300">{t('admin.hero.linkUrl')}</span>
                    <input
                        type="text"
                        dir="ltr"
                        placeholder="https://instagram.com/retab_dates"
                        value={current.value}
                        onChange={(e) => onChange(buildHref('url', e.target.value))}
                        className={field}
                    />
                    <span className="mt-1 block text-xs text-neutral-500">{t('admin.hero.linkUrlHint')}</span>
                </label>
            )}

            {/* What the shopper will actually open, in plain words — so the choice
                can be checked at a glance without knowing what a path is. */}
            <p className="text-xs text-neutral-500">
                {current.kind === 'none'
                    ? t('admin.hero.linkNoneHint')
                    : t('admin.hero.linkResult', { path: buildHref(current.kind, current.value) || '—' })}
            </p>
        </div>
    );
}
