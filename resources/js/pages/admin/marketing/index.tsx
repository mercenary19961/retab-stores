import { Head, router, usePage } from '@inertiajs/react';
import { FileText, History, Megaphone, Pencil, Send } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import AdminLayout from '@/layouts/admin-layout';
import Button from '@/components/admin/button';
import Select from '@/components/admin/select';
import { useAdminT } from '@/i18n/use-admin-t';

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

export default function MarketingIndex({
    templates,
    campaigns,
    audienceCount,
}: {
    templates: Template[];
    campaigns: Campaign[];
    audienceCount: number;
}) {
    const { t: tr } = useAdminT();
    const flash = (usePage().props as { flash?: { success?: string | null; error?: string | null } }).flash;

    // Template registry form (create or edit-in-place).
    const [editing, setEditing] = useState<number | null>(null);
    const [tpl, setTpl] = useState<Record<string, string | number>>(emptyTemplate);

    // Campaign composer.
    const [templateId, setTemplateId] = useState<number | ''>('');
    const [params, setParams] = useState<string[]>([]);
    const selected = templates.find((t) => t.id === templateId);

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
        }, { preserveScroll: true, onSuccess: () => { setTemplateId(''); setParams([]); } });
    };

    return (
        <AdminLayout title={tr('admin.marketing.title')}>
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
                                    <span className="ms-2 text-xs text-neutral-400">{t.language} · {t.category} · {t.param_count} {tr('admin.marketing.vars')}</span>
                                </div>
                                <div className="flex items-center gap-2">
                                    <span className={`rounded-full px-2 py-0.5 text-xs ${t.status === 'approved' ? 'bg-green-100 text-green-700' : 'bg-neutral-100 text-neutral-500 dark:bg-neutral-800'}`}>
                                        {t.status}
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
                                <input value={String(tpl.name)} onChange={(e) => setTpl({ ...tpl, name: e.target.value })} className={`${inputCls} font-mono`} />
                            </label>
                            <label className="block text-sm">
                                <span className="text-neutral-500">{tr('admin.marketing.varsCount')}</span>
                                <input type="number" min={0} max={10} value={Number(tpl.param_count)} onChange={(e) => setTpl({ ...tpl, param_count: Number(e.target.value) })} className={inputCls} />
                            </label>
                            <label className="block text-sm">
                                <span className="text-neutral-500">{tr('admin.marketing.language')}</span>
                                <Select value={String(tpl.language)} onChange={(e) => setTpl({ ...tpl, language: e.target.value })} className="mt-1 w-full">
                                    <option value="ar">ar</option>
                                    <option value="en">en</option>
                                </Select>
                            </label>
                            <label className="block text-sm">
                                <span className="text-neutral-500">{tr('admin.marketing.category')}</span>
                                <Select value={String(tpl.category)} onChange={(e) => setTpl({ ...tpl, category: e.target.value })} className="mt-1 w-full">
                                    <option value="marketing">marketing</option>
                                    <option value="utility">utility</option>
                                </Select>
                            </label>
                        </div>
                        <label className="block text-sm">
                            <span className="text-neutral-500">{tr('admin.marketing.bodyPreview')}</span>
                            <textarea dir="auto" rows={3} value={String(tpl.body)} onChange={(e) => setTpl({ ...tpl, body: e.target.value })} className={inputCls} />
                        </label>
                        <label className="block text-sm">
                            <span className="text-neutral-500">{tr('admin.marketing.metaStatus')}</span>
                            <Select value={String(tpl.status)} onChange={(e) => setTpl({ ...tpl, status: e.target.value })} className="mt-1 w-full">
                                {['draft', 'pending', 'approved', 'rejected'].map((s) => <option key={s} value={s}>{s}</option>)}
                            </Select>
                        </label>
                        <div className="flex gap-2">
                            <Button type="submit" variant="primary">{editing ? tr('admin.marketing.update') : tr('admin.marketing.add')}</Button>
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
                                    value={templateId}
                                    onChange={(e) => { const id = Number(e.target.value) || ''; setTemplateId(id); setParams([]); }}
                                    className="mt-1 w-full"
                                >
                                    <option value="">{tr('admin.marketing.pickTemplate')}</option>
                                    {templates.filter((t) => t.status === 'approved').map((t) => (
                                        <option key={t.id} value={t.id}>{t.name} ({t.language})</option>
                                    ))}
                                </Select>
                            </label>

                            {selected && selected.body && (
                                <p className="rounded bg-neutral-50 p-3 text-sm text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300" dir="auto">{selected.body}</p>
                            )}

                            {selected && Array.from({ length: selected.param_count }).map((_, i) => (
                                <label key={i} className="block text-sm">
                                    <span className="text-neutral-500">{'{{'}{i + 1}{'}}'}</span>
                                    <input
                                        dir="auto"
                                        value={params[i] ?? ''}
                                        onChange={(e) => { const next = [...params]; next[i] = e.target.value; setParams(next); }}
                                        className={inputCls}
                                    />
                                </label>
                            ))}

                            <Button type="submit" variant="primary" icon={Send} disabled={!selected}>
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
                                        <span className="text-xs text-neutral-400">{c.sent_at ?? c.status}</span>
                                    </div>
                                    <div className="mt-1 text-xs text-neutral-500">
                                        {tr('admin.marketing.audience', { count: c.audience_count })}
                                        {Object.entries(c.stats).map(([k, v]) => ` · ${k} ${v}`)}
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
