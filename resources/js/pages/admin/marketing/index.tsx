import { Head, router, usePage } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
import AdminLayout from '@/layouts/admin-layout';

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
        <AdminLayout title="WhatsApp Marketing">
            <Head title="WhatsApp Marketing" />

            {flash?.success && <div className="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{flash.success}</div>}
            {flash?.error && <div className="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{flash.error}</div>}

            <div className="grid gap-6 lg:grid-cols-2">
                {/* Templates registry */}
                <section className="rounded-lg border border-neutral-200 bg-white p-5 dark:border-neutral-800 dark:bg-neutral-900">
                    <h2 className="mb-1 font-bold">Templates</h2>
                    <p className="mb-4 text-sm text-neutral-500">
                        Author + get approval in Meta Business Manager, then mirror the template here and set its status to approved.
                    </p>

                    <ul className="mb-5 space-y-2 text-sm">
                        {templates.length === 0 && <li className="text-neutral-400">No templates yet.</li>}
                        {templates.map((t) => (
                            <li key={t.id} className="flex items-center justify-between rounded border border-neutral-100 px-3 py-2 dark:border-neutral-800">
                                <div>
                                    <span className="font-mono">{t.name}</span>
                                    <span className="ms-2 text-xs text-neutral-400">{t.language} · {t.category} · {t.param_count} vars</span>
                                </div>
                                <div className="flex items-center gap-2">
                                    <span className={`rounded-full px-2 py-0.5 text-xs ${t.status === 'approved' ? 'bg-green-100 text-green-700' : 'bg-neutral-100 text-neutral-500 dark:bg-neutral-800'}`}>
                                        {t.status}
                                    </span>
                                    <button
                                        type="button"
                                        className="text-xs text-blue-600 underline dark:text-blue-400"
                                        onClick={() => { setEditing(t.id); setTpl({ name: t.name, language: t.language, category: t.category, body: t.body, param_count: t.param_count, status: t.status }); }}
                                    >
                                        Edit
                                    </button>
                                </div>
                            </li>
                        ))}
                    </ul>

                    <form onSubmit={saveTemplate} className="space-y-3 border-t border-neutral-100 pt-4 dark:border-neutral-800">
                        <p className="text-sm font-semibold">{editing ? 'Edit template' : 'Add template'}</p>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <label className="block text-sm">
                                <span className="text-neutral-500">Meta name (snake_case)</span>
                                <input value={String(tpl.name)} onChange={(e) => setTpl({ ...tpl, name: e.target.value })} className={`${inputCls} font-mono`} />
                            </label>
                            <label className="block text-sm">
                                <span className="text-neutral-500">Variables count</span>
                                <input type="number" min={0} max={10} value={Number(tpl.param_count)} onChange={(e) => setTpl({ ...tpl, param_count: Number(e.target.value) })} className={inputCls} />
                            </label>
                            <label className="block text-sm">
                                <span className="text-neutral-500">Language</span>
                                <select value={String(tpl.language)} onChange={(e) => setTpl({ ...tpl, language: e.target.value })} className={inputCls}>
                                    <option value="ar">ar</option>
                                    <option value="en">en</option>
                                </select>
                            </label>
                            <label className="block text-sm">
                                <span className="text-neutral-500">Category</span>
                                <select value={String(tpl.category)} onChange={(e) => setTpl({ ...tpl, category: e.target.value })} className={inputCls}>
                                    <option value="marketing">marketing</option>
                                    <option value="utility">utility</option>
                                </select>
                            </label>
                        </div>
                        <label className="block text-sm">
                            <span className="text-neutral-500">Body preview ({'{{1}}'} placeholders)</span>
                            <textarea dir="auto" rows={3} value={String(tpl.body)} onChange={(e) => setTpl({ ...tpl, body: e.target.value })} className={inputCls} />
                        </label>
                        <label className="block text-sm">
                            <span className="text-neutral-500">Meta approval status</span>
                            <select value={String(tpl.status)} onChange={(e) => setTpl({ ...tpl, status: e.target.value })} className={inputCls}>
                                {['draft', 'pending', 'approved', 'rejected'].map((s) => <option key={s} value={s}>{s}</option>)}
                            </select>
                        </label>
                        <div className="flex gap-2">
                            <button type="submit" className="rounded-lg bg-neutral-900 px-4 py-2 text-sm font-semibold text-white dark:bg-white dark:text-neutral-900">
                                {editing ? 'Update' : 'Add'}
                            </button>
                            {editing && (
                                <button type="button" onClick={() => { setEditing(null); setTpl(emptyTemplate); }} className="rounded-lg border border-neutral-300 px-4 py-2 text-sm dark:border-neutral-700">
                                    Cancel
                                </button>
                            )}
                        </div>
                    </form>
                </section>

                {/* Campaign composer + history */}
                <div className="space-y-6">
                    <section className="rounded-lg border border-neutral-200 bg-white p-5 dark:border-neutral-800 dark:bg-neutral-900">
                        <h2 className="mb-1 font-bold">Send a campaign</h2>
                        <p className="mb-4 text-sm text-neutral-500">
                            Goes to the opt-in segment: <b>{audienceCount}</b> customers. Approved templates only.
                        </p>

                        <form onSubmit={sendCampaign} className="space-y-3">
                            <label className="block text-sm">
                                <span className="text-neutral-500">Template</span>
                                <select
                                    value={templateId}
                                    onChange={(e) => { const id = Number(e.target.value) || ''; setTemplateId(id); setParams([]); }}
                                    className={inputCls}
                                >
                                    <option value="">— pick an approved template —</option>
                                    {templates.filter((t) => t.status === 'approved').map((t) => (
                                        <option key={t.id} value={t.id}>{t.name} ({t.language})</option>
                                    ))}
                                </select>
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

                            <button
                                type="submit"
                                disabled={!selected}
                                className="rounded-lg bg-neutral-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50 dark:bg-white dark:text-neutral-900"
                            >
                                Send to {audienceCount} customers
                            </button>
                        </form>
                    </section>

                    <section className="rounded-lg border border-neutral-200 bg-white p-5 dark:border-neutral-800 dark:bg-neutral-900">
                        <h2 className="mb-3 font-bold">Campaign history</h2>
                        <ul className="space-y-2 text-sm">
                            {campaigns.length === 0 && <li className="text-neutral-400">No campaigns yet.</li>}
                            {campaigns.map((c) => (
                                <li key={c.id} className="rounded border border-neutral-100 px-3 py-2 dark:border-neutral-800">
                                    <div className="flex items-center justify-between">
                                        <span className="font-mono">#{c.id} {c.template}</span>
                                        <span className="text-xs text-neutral-400">{c.sent_at ?? c.status}</span>
                                    </div>
                                    <div className="mt-1 text-xs text-neutral-500">
                                        audience {c.audience_count}
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
