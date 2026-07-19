import { Head, router, usePage } from '@inertiajs/react';
import { FileText, History, Megaphone, Pencil, Send } from 'lucide-react';
import { type FormEvent, type ReactNode, useState } from 'react';
import AdminLayout from '@/layouts/admin-layout';
import Button from '@/components/admin/button';
import Select from '@/components/admin/select';
import { useAdminT } from '@/i18n/use-admin-t';

const PLACEHOLDER_RE = /\{\{\s*(\d+)\s*\}\}/g;

/** Distinct {{n}} placeholders in a template body, as sorted numbers. */
function detectPlaceholders(body: string): number[] {
    const nums = new Set<number>();
    for (const m of body.matchAll(PLACEHOLDER_RE)) nums.add(Number(m[1]));
    return Array.from(nums).sort((a, b) => a - b);
}

/**
 * Render a template body with its {{n}} placeholders highlighted. When `values`
 * is given (campaign preview), filled variables show substituted in teal and
 * unfilled ones stay as {{n}} in amber.
 */
function renderTemplate(body: string, values?: string[]): ReactNode {
    const out: ReactNode[] = [];
    let last = 0;
    let key = 0;
    for (const m of body.matchAll(PLACEHOLDER_RE)) {
        const idx = m.index ?? 0;
        if (idx > last) out.push(body.slice(last, idx));
        const n = Number(m[1]);
        const val = values?.[n - 1]?.trim();
        out.push(
            <span key={`v${key++}`} className={`rounded px-1 ${val ? 'bg-brand-teal/20 text-brand-teal' : 'bg-amber-500/20 text-amber-700 dark:text-amber-300'}`}>
                {val || `{{${n}}}`}
            </span>,
        );
        last = idx + m[0].length;
    }
    if (last < body.length) out.push(body.slice(last));
    return out;
}

interface Template {
    id: number;
    name: string;
    language: string;
    category: string;
    body: string;
    param_count: number;
    status: string;
}

interface Campaign {
    id: number;
    template: string | null;
    params: string[] | null;
    status: string;
    audience_count: number;
    sent_at: string | null;
    stats: Record<string, number>;
}

const inputCls = 'mt-1 w-full rounded border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-950';

const emptyTemplate = { name: '', language: 'ar', category: 'marketing', body: '', param_count: 0, status: 'draft' };

// WhatsApp brand glyph (lucide has no brand icons); brand green is #25D366.
function WhatsappIcon({ className }: { className?: string }) {
    return (
        <svg viewBox="0 0 24 24" fill="currentColor" className={className} aria-hidden="true">
            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.71.306 1.263.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.885-9.885 9.885M20.52 3.449C18.24 1.245 15.24 0 12.045 0 5.463 0 .104 5.334.101 11.892c0 2.096.549 4.14 1.595 5.945L0 24l6.335-1.652a12.062 12.062 0 005.71 1.447h.006c6.585 0 11.946-5.335 11.949-11.896 0-3.176-1.24-6.165-3.495-8.411" />
        </svg>
    );
}

export default function MarketingIndex({
    templates,
    campaigns,
    segmentCounts,
    segments,
    messageRate,
    rateCurrency,
}: {
    templates: Template[];
    campaigns: Campaign[];
    segmentCounts: Record<string, number>;
    segments: string[];
    messageRate: number;
    rateCurrency: string;
}) {
    const { t: tr, i18n } = useAdminT();
    const flash = (usePage().props as { flash?: { success?: string | null; error?: string | null } }).flash;

    // Localized display labels for enum-like values (category / status / language / delivery funnel).
    // The stored value stays English — it's what our API and Meta expect — only the label localizes;
    // unknown values fall back to the raw string.
    const label = (group: string, key: string) => tr(`admin.marketing.${group}.${key}`, { defaultValue: key });

    // Header title: only the "WhatsApp" word (+ icon) is brand-green; the rest white.
    const titleText = tr('admin.marketing.title');
    const brandWord = tr('admin.marketing.brandWord');
    const brandIdx = titleText.indexOf(brandWord);
    const marketingTitle = brandIdx === -1 ? (
        <span className="inline-flex items-center gap-2">
            <WhatsappIcon className="h-5 w-5 text-[#25D366]" /> {titleText}
        </span>
    ) : (
        <span className="inline-flex items-center gap-2 text-white">
            <WhatsappIcon className="h-5 w-5 text-[#25D366]" />
            <span>
                {titleText.slice(0, brandIdx)}
                <span className="text-[#25D366]">{brandWord}</span>
                {titleText.slice(brandIdx + brandWord.length)}
            </span>
        </span>
    );

    // Template registry form (create or edit-in-place).
    const [editing, setEditing] = useState<number | null>(null);
    const [tpl, setTpl] = useState<Record<string, string | number>>(emptyTemplate);

    // Campaign composer.
    const [templateId, setTemplateId] = useState<number | ''>('');
    const [params, setParams] = useState<string[]>([]);
    const [segment, setSegment] = useState('all');
    const selected = templates.find((t) => t.id === templateId);
    // Live recipient count for the chosen segment (drives the "Send to N" button).
    const audienceCount = segmentCounts[segment] ?? 0;
    // Rough send cost: recipients × per-message marketing rate.
    const estCost = (audienceCount * messageRate).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    // Template body helpers (live preview + auto variable detection).
    const body = String(tpl.body);
    const detected = detectPlaceholders(body);
    const bodyExample = i18n.language === 'en'
        ? 'Ramadan offer: {{1}}% off {{2}}, order now! 🌴'
        : 'عرض رمضان: خصم {{1}}٪ على {{2}}، اطلب الآن! 🌴';

    // Disable Add/Update until the template form differs from its baseline
    // (the edited template, or the empty defaults when creating).
    const tplBaseline = editing ? templates.find((tp) => tp.id === editing) : undefined;
    const templateDirty = (['name', 'language', 'category', 'body', 'param_count', 'status'] as const).some(
        (k) => String(tpl[k]) !== String(tplBaseline ? tplBaseline[k] : emptyTemplate[k as keyof typeof emptyTemplate]),
    );

    const saveTemplate = (e: FormEvent) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: () => { setEditing(null); setTpl(emptyTemplate); } };
        if (editing) router.put(`/admin/marketing/templates/${editing}`, tpl, opts);
        else router.post('/admin/marketing/templates', tpl, opts);
    };

    const sendCampaign = (e: FormEvent) => {
        e.preventDefault();
        if (!selected) return;
        router.post('/admin/marketing/campaigns', {
            whatsapp_template_id: selected.id,
            params: params.slice(0, selected.param_count),
            segment,
        }, { preserveScroll: true, onSuccess: () => { setTemplateId(''); setParams([]); setSegment('all'); } });
    };

    return (
        <AdminLayout title={marketingTitle}>
            <Head title={tr('admin.marketing.title')} />

            {flash?.success && <div className="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{flash.success}</div>}
            {flash?.error && <div className="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{flash.error}</div>}

            <div className="grid gap-6 lg:grid-cols-2">
                {/* Templates registry */}
                <section className="rounded-lg border border-neutral-200 bg-white p-5 dark:border-neutral-800 dark:bg-neutral-900">
                    <h2 className="mb-1 flex items-center gap-2 font-bold"><FileText className="h-4 w-4 text-brand-gold" /> {tr('admin.marketing.templates')}</h2>
                    <p className="mb-4 text-sm text-neutral-500">{tr('admin.marketing.templatesDesc')}</p>

                    <ul className="mb-5 space-y-2 text-sm">
                        {templates.length === 0 && <li className="text-neutral-400">{tr('admin.marketing.noTemplates')}</li>}
                        {templates.map((t) => (
                            <li key={t.id} className="flex items-center justify-between rounded border border-neutral-100 px-3 py-2 dark:border-neutral-800">
                                <div>
                                    <span className="font-mono">{t.name}</span>
                                    <span className="ms-2 text-xs text-neutral-400">{label('lang', t.language)} · {label('categories', t.category)} · {t.param_count} {tr('admin.marketing.vars')}</span>
                                </div>
                                <div className="flex items-center gap-2">
                                    <span className={`rounded-full px-2 py-0.5 text-xs ${t.status === 'approved' ? 'bg-green-100 text-green-700' : 'bg-neutral-100 text-neutral-500 dark:bg-neutral-800'}`}>
                                        {label('templateStatus', t.status)}
                                    </span>
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        icon={Pencil}
                                        onClick={() => { setEditing(t.id); setTpl({ name: t.name, language: t.language, category: t.category, body: t.body, param_count: t.param_count, status: t.status }); }}
                                    >
                                        {tr('admin.common.edit')}
                                    </Button>
                                </div>
                            </li>
                        ))}
                    </ul>

                    <form onSubmit={saveTemplate} className="space-y-3 border-t border-neutral-100 pt-4 dark:border-neutral-800">
                        <p className="text-sm font-semibold">{editing ? tr('admin.marketing.editTemplate') : tr('admin.marketing.addTemplate')}</p>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <label className="block text-sm">
                                <span className="text-neutral-500">{tr('admin.marketing.metaName')}</span>
                                <input value={String(tpl.name)} placeholder={tr('admin.marketing.namePlaceholder')} onChange={(e) => setTpl({ ...tpl, name: e.target.value })} className={`${inputCls} font-mono`} />
                                <span className="mt-1 block text-xs text-neutral-400">{tr('admin.marketing.hints.metaName')}</span>
                            </label>
                            <label className="block text-sm">
                                <span className="text-neutral-500">{tr('admin.marketing.varsCount')}</span>
                                <input type="number" min={0} max={10} value={Number(tpl.param_count)} onChange={(e) => setTpl({ ...tpl, param_count: Number(e.target.value) })} className={inputCls} />
                                {Number(tpl.param_count) !== detected.length ? (
                                    <span className="mt-1 block text-xs text-amber-600 dark:text-amber-400">{tr('admin.marketing.hints.mismatch', { body: detected.length, count: Number(tpl.param_count) })}</span>
                                ) : (
                                    <span className="mt-1 block text-xs text-neutral-400">{tr('admin.marketing.hints.varsCount')}</span>
                                )}
                            </label>
                            <label className="block text-sm">
                                <span className="text-neutral-500">{tr('admin.marketing.language')}</span>
                                <Select
                                    value={String(tpl.language)}
                                    onChange={(v) => setTpl({ ...tpl, language: v })}
                                    options={[{ value: 'ar', label: label('lang', 'ar') }, { value: 'en', label: label('lang', 'en') }]}
                                    className="mt-1 w-full"
                                />
                                <span className="mt-1 block text-xs text-neutral-400">{tr('admin.marketing.hints.language')}</span>
                            </label>
                            <label className="block text-sm">
                                <span className="text-neutral-500">{tr('admin.marketing.category')}</span>
                                <Select
                                    value={String(tpl.category)}
                                    onChange={(v) => setTpl({ ...tpl, category: v })}
                                    options={[{ value: 'marketing', label: label('categories', 'marketing') }, { value: 'utility', label: label('categories', 'utility') }]}
                                    className="mt-1 w-full"
                                />
                                <span className="mt-1 block text-xs text-neutral-400">{tr('admin.marketing.hints.category')}</span>
                            </label>
                        </div>
                        <label className="block text-sm">
                            <span className="text-neutral-500">{tr('admin.marketing.bodyPreview')}</span>
                            <textarea
                                dir="auto"
                                rows={3}
                                value={body}
                                placeholder={bodyExample}
                                onChange={(e) => setTpl({ ...tpl, body: e.target.value, param_count: detectPlaceholders(e.target.value).length })}
                                className={inputCls}
                            />
                            <span className="mt-1 block text-xs text-neutral-400">{tr('admin.marketing.hints.body')}</span>
                        </label>
                        {body.trim() && (
                            <div className="rounded-md border border-neutral-200 bg-neutral-50 p-3 text-sm dark:border-neutral-800 dark:bg-neutral-950">
                                <p className="mb-1 text-xs font-medium uppercase text-neutral-400">{tr('admin.marketing.livePreview')}</p>
                                <p dir="auto" className="whitespace-pre-wrap text-neutral-700 dark:text-neutral-200">{renderTemplate(body)}</p>
                            </div>
                        )}
                        <label className="block text-sm">
                            <span className="text-neutral-500">{tr('admin.marketing.metaStatus')}</span>
                            <Select
                                value={String(tpl.status)}
                                onChange={(v) => setTpl({ ...tpl, status: v })}
                                options={['draft', 'pending', 'approved', 'rejected'].map((s) => ({ value: s, label: label('templateStatus', s) }))}
                                className="mt-1 w-full"
                            />
                            <span className="mt-1 block text-xs text-neutral-400">{tr('admin.marketing.hints.status')}</span>
                        </label>
                        <div className="flex gap-2">
                            <Button type="submit" variant="primary" disabled={!templateDirty}>{editing ? tr('admin.marketing.update') : tr('admin.marketing.add')}</Button>
                            {editing && (
                                <Button type="button" variant="secondary" onClick={() => { setEditing(null); setTpl(emptyTemplate); }}>{tr('admin.common.cancel')}</Button>
                            )}
                        </div>
                    </form>
                </section>

                {/* Campaign composer + history */}
                <div className="space-y-6">
                    <section className="rounded-lg border border-neutral-200 bg-white p-5 dark:border-neutral-800 dark:bg-neutral-900">
                        <h2 className="mb-1 flex items-center gap-2 font-bold"><Megaphone className="h-4 w-4 text-brand-gold" /> {tr('admin.marketing.sendCampaign')}</h2>
                        <p className="mb-4 text-sm text-neutral-500">{tr('admin.marketing.audienceNote', { count: audienceCount })}</p>

                        <form onSubmit={sendCampaign} className="space-y-3">
                            <label className="block text-sm">
                                <span className="text-neutral-500">{tr('admin.marketing.template')}</span>
                                <Select
                                    value={templateId ? String(templateId) : ''}
                                    onChange={(v) => { const id = Number(v) || ''; setTemplateId(id); setParams([]); }}
                                    options={[
                                        { value: '', label: tr('admin.marketing.pickTemplate') },
                                        ...templates.filter((t) => t.status === 'approved').map((t) => ({ value: String(t.id), label: `${t.name} (${label('lang', t.language)})` })),
                                    ]}
                                    className="mt-1 w-full"
                                />
                            </label>

                            <label className="block text-sm">
                                <span className="text-neutral-500">{tr('admin.marketing.segment')}</span>
                                <Select
                                    value={segment}
                                    onChange={(v) => setSegment(v)}
                                    options={segments.map((s) => ({ value: s, label: `${label('segments', s)} (${tr('admin.marketing.people', { count: segmentCounts[s] ?? 0 })})` }))}
                                    className="mt-1 w-full"
                                />
                                <span className="mt-1 block text-xs text-neutral-400">{tr('admin.marketing.segmentHint')}</span>
                            </label>

                            {selected && Array.from({ length: selected.param_count }).map((_, i) => (
                                <label key={i} className="block text-sm">
                                    <span className="text-neutral-500">{tr('admin.marketing.variable', { n: i + 1 })}</span>
                                    <input
                                        dir="auto"
                                        value={params[i] ?? ''}
                                        onChange={(e) => { const next = [...params]; next[i] = e.target.value; setParams(next); }}
                                        className={inputCls}
                                    />
                                </label>
                            ))}

                            {selected && selected.body && (
                                <div className="rounded-md border border-neutral-200 bg-neutral-50 p-3 text-sm dark:border-neutral-800 dark:bg-neutral-950">
                                    <p className="mb-1 text-xs font-medium uppercase text-neutral-400">{tr('admin.marketing.customerPreview')}</p>
                                    {/* dir="auto" must sit on the message itself, not the wrapper: on the wrapper it
                                        would detect direction from the English label above and force the whole box LTR,
                                        misplacing Arabic punctuation. On the message it mirrors WhatsApp's own
                                        first-strong-character direction detection. */}
                                    <p dir="auto" className="whitespace-pre-wrap text-neutral-700 dark:text-neutral-200">{renderTemplate(selected.body, params)}</p>
                                </div>
                            )}

                            {audienceCount > 0 && (
                                <div className="rounded-md border border-neutral-200 bg-neutral-50 px-3 py-2 text-sm dark:border-neutral-800 dark:bg-neutral-950">
                                    <span className="text-neutral-600 dark:text-neutral-300">{tr('admin.marketing.estCost', { cost: estCost, currency: rateCurrency })}</span>
                                    <span className="mt-0.5 block text-xs text-neutral-400">{tr('admin.marketing.estCostHint', { count: audienceCount, rate: messageRate, currency: rateCurrency })}</span>
                                </div>
                            )}

                            <Button type="submit" variant="primary" icon={Send} disabled={!selected || audienceCount === 0}>
                                {tr('admin.marketing.sendTo', { count: audienceCount })}
                            </Button>
                        </form>
                    </section>

                    <section className="rounded-lg border border-neutral-200 bg-white p-5 dark:border-neutral-800 dark:bg-neutral-900">
                        <h2 className="mb-3 flex items-center gap-2 font-bold"><History className="h-4 w-4 text-brand-gold" /> {tr('admin.marketing.campaignHistory')}</h2>
                        <ul className="space-y-2 text-sm">
                            {campaigns.length === 0 && <li className="text-neutral-400">{tr('admin.marketing.noCampaigns')}</li>}
                            {campaigns.map((c) => (
                                <li key={c.id} className="rounded border border-neutral-100 px-3 py-2 dark:border-neutral-800">
                                    <div className="flex items-center justify-between">
                                        <span className="font-mono">#{c.id} {c.template}</span>
                                        <span className="text-xs text-neutral-400">{c.sent_at ?? label('templateStatus', c.status)}</span>
                                    </div>
                                    <div className="mt-1 text-xs text-neutral-500">
                                        {tr('admin.marketing.audience', { count: c.audience_count })}
                                        {Object.entries(c.stats).map(([k, v]) => ` · ${label('funnel', k)} ${v}`)}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </section>
                </div>
            </div>
        </AdminLayout>
    );
}
