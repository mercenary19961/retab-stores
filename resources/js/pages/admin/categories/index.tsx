import { Head, router, useForm } from '@inertiajs/react';
import { AlertTriangle, ArrowDown, ArrowUp, CornerDownRight, Eye, EyeOff, FolderTree, ImageOff, Pencil, Plus, Sparkles, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useState, type FormEvent, type ReactNode } from 'react';

import Button from '@/components/admin/button';
import HintTooltip from '@/components/admin/hint-tooltip';
import Modal from '@/components/admin/modal';
import Select from '@/components/admin/select';
import StatusToggle from '@/components/admin/status-toggle';
import StatusPill from '@/components/status-pill';
import { useCan } from '@/hooks/use-can';
import { useAdminT } from '@/i18n/use-admin-t';
import AdminLayout from '@/layouts/admin-layout';
import { CARD, THEAD } from '@/lib/admin-ui';

/**
 * Categories: the storefront's top-menu groups and the categories products are
 * filed under.
 *
 * 🔑 Two levels, and a category is EITHER a group (top level, holds subcategories,
 * opens a navbar dropdown) OR a leaf (holds products). The server enforces the
 * shape; this page mirrors it so a choice the server would refuse is never
 * offered in the first place — the parent list only shows eligible groups, and
 * the delete button explains why it is off instead of failing on click.
 */

type Blocker = 'category_has_children' | 'category_protected';

export interface CategoryRow {
    id: number;
    name_ar: string;
    name_en: string | null;
    slug: string;
    parent_id: number | null;
    sort_order: number;
    is_active: boolean;
    image: string | null;
    products_count: number;
    children_count: number;
    is_offers_bucket: boolean;
    delete_blocker: Blocker | null;
    /** Whether shoppers can reach it: hidden by staff, live, or switched on but empty. */
    store_state: 'live' | 'empty' | 'hidden';
}

interface TreeRow {
    row: CategoryRow;
    depth: 0 | 1;
    /** First / last among its siblings — which move arrow is available. */
    first: boolean;
    last: boolean;
}

/** Top-level categories in order, each followed by its own children in order. */
function toTree(rows: CategoryRow[]): TreeRow[] {
    // The server already sorts by sort_order then id; keep that order per level.
    const top = rows.filter((r) => r.parent_id === null);
    const out: TreeRow[] = [];

    top.forEach((parent, i) => {
        out.push({ row: parent, depth: 0, first: i === 0, last: i === top.length - 1 });
        const kids = rows.filter((r) => r.parent_id === parent.id);
        kids.forEach((kid, j) => out.push({ row: kid, depth: 1, first: j === 0, last: j === kids.length - 1 }));
    });

    return out;
}

const FIELD =
    'mt-1 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm focus:ring-2 focus:ring-brand-teal/40 focus:outline-none disabled:cursor-not-allowed disabled:opacity-60 dark:border-neutral-700 dark:bg-neutral-950';

function Field({ label, hint, error, children }: { label: string; hint?: ReactNode; error?: string; children: ReactNode }) {
    return (
        <label className="block">
            <span className="text-sm font-medium text-neutral-700 dark:text-neutral-200">{label}</span>
            {children}
            {error ? (
                <span className="mt-1 block text-xs text-red-600 dark:text-red-400">{error}</span>
            ) : hint ? (
                <span className="mt-1 block text-xs text-neutral-500">{hint}</span>
            ) : null}
        </label>
    );
}

/** A small square icon button for the reorder arrows. */
function ArrowButton({ label, onClick, disabled, children }: { label: string; onClick: () => void; disabled: boolean; children: ReactNode }) {
    return (
        <button
            type="button"
            onClick={onClick}
            disabled={disabled}
            aria-label={label}
            title={label}
            className="rounded-md p-1 text-neutral-400 transition-colors hover:bg-neutral-800 hover:text-neutral-100 disabled:pointer-events-none disabled:opacity-25"
        >
            {children}
        </button>
    );
}

type FormData = {
    name_ar: string;
    name_en: string;
    slug: string;
    parent_id: string;
    is_active: boolean;
    image: File | null;
    remove_image: boolean;
    _method?: 'put';
};

/**
 * Create / edit dialog. Mounted fresh on every open (keyed by the caller), so a
 * cancelled attempt can never leave its half-typed values in the next one.
 */
function CategoryDialog({ category, rows, onClose }: { category: CategoryRow | null; rows: CategoryRow[]; onClose: () => void }) {
    const { t } = useAdminT();
    const form = useForm<FormData>({
        name_ar: category?.name_ar ?? '',
        name_en: category?.name_en ?? '',
        slug: category?.slug ?? '',
        parent_id: category?.parent_id ? String(category.parent_id) : '',
        is_active: category?.is_active ?? true,
        image: null,
        remove_image: false,
        // PHP only parses a multipart body on POST, so an edit POSTs and spoofs PUT.
        ...(category ? { _method: 'put' as const } : {}),
    });
    const { data, setData, errors, processing } = form;

    // A group must stay top-level (a third level has nowhere to render), and
    // Special Offers is found by store events at the top level.
    const parentLocked = category ? (category.children_count > 0 ? 'group' : category.is_offers_bucket ? 'offers' : null) : null;

    // Eligible parents: top-level, not itself, and holding no products — a
    // category holds products OR subcategories, never both.
    const groups = rows.filter((r) => r.parent_id === null && r.id !== category?.id && r.products_count === 0 && !r.is_offers_bucket);

    const newPreview = useMemo(() => (data.image ? URL.createObjectURL(data.image) : null), [data.image]);
    useEffect(() => () => void (newPreview && URL.revokeObjectURL(newPreview)), [newPreview]);
    const preview = newPreview ?? (data.remove_image ? null : (category?.image ?? null));

    const slugChanged = category !== null && data.slug.trim() !== '' && data.slug.trim() !== category.slug;

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(category ? `/admin/categories/${category.id}` : '/admin/categories', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <Modal open onClose={onClose} title={t(category ? 'admin.categories.edit' : 'admin.categories.new')} size="md">
            <form onSubmit={submit} className="space-y-4">
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label={t('admin.categories.form.nameAr')} error={errors.name_ar}>
                        <input dir="rtl" value={data.name_ar} onChange={(e) => setData('name_ar', e.target.value)} className={FIELD} autoFocus />
                    </Field>
                    <Field label={t('admin.categories.form.nameEn')} hint={t('admin.categories.form.nameEnHint')} error={errors.name_en}>
                        <input dir="ltr" value={data.name_en} onChange={(e) => setData('name_en', e.target.value)} className={FIELD} />
                    </Field>
                </div>

                <Field
                    label={t('admin.categories.form.parent')}
                    hint={
                        parentLocked === 'group'
                            ? t('admin.categories.form.parentLockedGroup')
                            : parentLocked === 'offers'
                              ? t('admin.categories.form.parentLockedOffers')
                              : t('admin.categories.form.parentHint')
                    }
                    error={errors.parent_id}
                >
                    <select
                        value={data.parent_id}
                        onChange={(e) => setData('parent_id', e.target.value)}
                        disabled={parentLocked !== null}
                        className={FIELD}
                    >
                        <option value="">{t('admin.categories.form.topLevel')}</option>
                        {groups.map((g) => (
                            <option key={g.id} value={g.id}>
                                {g.name_ar}
                                {g.name_en ? ` · ${g.name_en}` : ''}
                            </option>
                        ))}
                    </select>
                </Field>

                <Field
                    label={t('admin.categories.form.slug')}
                    hint={
                        category?.is_offers_bucket ? (
                            t('admin.categories.form.slugLocked')
                        ) : slugChanged ? (
                            <span className="text-amber-600 dark:text-amber-400">{t('admin.categories.form.slugChanged')}</span>
                        ) : data.slug.trim() ? (
                            <span dir="ltr">{t('admin.categories.form.slugPreview', { slug: data.slug.trim() })}</span>
                        ) : undefined
                    }
                    error={errors.slug}
                >
                    <input
                        dir="ltr"
                        value={data.slug}
                        onChange={(e) => setData('slug', e.target.value.toLowerCase())}
                        placeholder={t('admin.categories.form.slugPlaceholder')}
                        disabled={category?.is_offers_bucket}
                        className={`${FIELD} font-mono`}
                    />
                </Field>

                <div>
                    <span className="text-sm font-medium text-neutral-700 dark:text-neutral-200">{t('admin.categories.form.image')}</span>
                    <div className="mt-1 flex items-center gap-4">
                        {/* Neutral ground, so a transparent tile reads as it will on the
                            homepage instead of vanishing into the panel. */}
                        <div className="flex h-24 w-24 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-neutral-700 bg-neutral-100">
                            {preview ? (
                                <img src={preview} alt="" className="max-h-full max-w-full object-contain" />
                            ) : (
                                <ImageOff className="h-6 w-6 text-neutral-400" />
                            )}
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            <label className="inline-flex cursor-pointer items-center justify-center rounded-lg border border-neutral-300 px-3 py-1.5 text-xs font-semibold text-neutral-700 hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-200 dark:hover:bg-neutral-800">
                                {t(preview ? 'admin.categories.form.replaceImage' : 'admin.categories.form.chooseImage')}
                                <input
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp,image/gif"
                                    aria-label={t('admin.categories.form.image')}
                                    className="sr-only"
                                    onChange={(e) => {
                                        setData((d) => ({ ...d, image: e.target.files?.[0] ?? null, remove_image: false }));
                                        e.target.value = '';
                                    }}
                                />
                            </label>
                            {data.image ? (
                                <Button size="sm" variant="ghost" onClick={() => setData('image', null)}>
                                    {t('admin.categories.form.removeImage')}
                                </Button>
                            ) : category?.image && !data.remove_image ? (
                                <Button size="sm" variant="ghost" icon={Trash2} onClick={() => setData('remove_image', true)}>
                                    {t('admin.categories.form.removeImage')}
                                </Button>
                            ) : data.remove_image ? (
                                <Button size="sm" variant="ghost" onClick={() => setData('remove_image', false)}>
                                    {t('admin.categories.form.undoRemove')}
                                </Button>
                            ) : null}
                        </div>
                    </div>
                    <p className="mt-1.5 text-xs text-neutral-500">
                        {data.remove_image ? t('admin.categories.form.imageRemoved') : t('admin.categories.form.imageHint')}
                    </p>
                    {errors.image && <p className="mt-1 text-xs text-red-600 dark:text-red-400">{errors.image}</p>}
                </div>

                <label className="flex items-start gap-3">
                    <input
                        type="checkbox"
                        checked={data.is_active}
                        onChange={(e) => setData('is_active', e.target.checked)}
                        className="accent-brand-teal mt-0.5 h-4 w-4"
                    />
                    <span>
                        <span className="block text-sm font-medium text-neutral-700 dark:text-neutral-200">{t('admin.categories.form.visible')}</span>
                        <span className="block text-xs text-neutral-500">{t('admin.categories.form.visibleHint')}</span>
                    </span>
                </label>

                <div className="flex justify-end gap-2 pt-2">
                    <Button variant="secondary" onClick={onClose}>
                        {t('admin.common.cancel')}
                    </Button>
                    <Button type="submit" variant="primary" disabled={processing || !data.name_ar.trim()}>
                        {t(category ? 'admin.categories.save' : 'admin.categories.create')}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}

/**
 * Delete confirmation with a choice of where the products go.
 *
 * 🔑 Deleting a category never deletes its products. The admin either moves them
 * to another category, or leaves them without one: they stay on sale and are
 * listed under the "No category" filter on Products. Typing the confirm word is
 * kept from ConfirmDeleteButton, since a category itself cannot be restored.
 */
function CategoryDeleteDialog({ category, rows, onClose }: { category: CategoryRow; rows: CategoryRow[]; onClose: () => void }) {
    const { t } = useAdminT();
    const confirmWord = t('admin.deleteModal.confirmWord');
    const [target, setTarget] = useState('');
    const [text, setText] = useState('');
    const [busy, setBusy] = useState(false);

    const ready = text.trim().toLowerCase() === confirmWord.toLowerCase();
    const hasProducts = category.products_count > 0;
    // Products only live in a leaf: never a menu group, never this category.
    const targets = rows.filter((r) => r.children_count === 0 && r.id !== category.id);

    const confirm = () => {
        if (!ready || busy) return;
        setBusy(true);
        router.delete(`/admin/categories/${category.id}`, {
            data: { move_to: target || null },
            preserveScroll: true,
            onSuccess: onClose,
            onFinish: () => setBusy(false),
        });
    };

    return (
        <Modal open onClose={onClose} size="sm" title={t('admin.categories.deleteDialog.title')}>
            <div className="space-y-4">
                <div className="flex items-start gap-3">
                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-500/15 text-red-600 dark:text-red-400">
                        <AlertTriangle className="h-5 w-5" />
                    </div>
                    {/* No dir="auto": the sentence follows the panel's language. With
                        auto, an Arabic category name at the start flipped the whole
                        English sentence right-to-left and scrambled it. */}
                    <p className="text-sm text-neutral-700 dark:text-neutral-200">
                        {hasProducts
                            ? t('admin.categories.deleteDialog.lead', { name: category.name_ar, n: category.products_count })
                            : t('admin.categories.deleteDialog.leadEmpty', { name: category.name_ar })}
                    </p>
                </div>

                {hasProducts && (
                    <div>
                        <span className="text-sm font-medium text-neutral-700 dark:text-neutral-200">
                            {t('admin.categories.deleteDialog.moveTo')}
                        </span>
                        <Select
                            value={target}
                            onChange={setTarget}
                            options={[
                                { value: '', label: t('admin.categories.deleteDialog.leaveUncategorized') },
                                ...targets.map((r) => ({ value: String(r.id), label: r.name_en ? `${r.name_ar} · ${r.name_en}` : r.name_ar })),
                            ]}
                            className="mt-1 w-full"
                        />
                        <p className="mt-1.5 text-xs text-neutral-500">
                            {t(target ? 'admin.categories.deleteDialog.movedHint' : 'admin.categories.deleteDialog.uncategorizedHint')}
                        </p>
                    </div>
                )}

                <label className="block">
                    <span className="text-sm text-neutral-600 dark:text-neutral-300">{t('admin.deleteModal.prompt', { word: confirmWord })}</span>
                    <input
                        dir="auto"
                        value={text}
                        onChange={(e) => setText(e.target.value)}
                        onKeyDown={(e) => e.key === 'Enter' && confirm()}
                        placeholder={confirmWord}
                        autoComplete="off"
                        autoFocus
                        className="mt-1 w-full rounded-lg border border-red-300 px-3 py-2 text-sm focus:ring-2 focus:ring-red-500/40 focus:outline-none dark:border-red-900 dark:bg-neutral-950"
                    />
                </label>

                <div className="flex justify-end gap-2 pt-1">
                    <Button variant="secondary" onClick={onClose}>
                        {t('admin.common.cancel')}
                    </Button>
                    <Button variant="danger" icon={Trash2} disabled={!ready || busy} onClick={confirm}>
                        {t('admin.categories.deleteDialog.confirm')}
                    </Button>
                </div>
            </div>
        </Modal>
    );
}

export default function CategoriesIndex({ categories }: { categories: CategoryRow[] }) {
    const { t } = useAdminT();
    const can = useCan();
    const canManage = can('categories.manage');
    const canSeeProducts = can('products.view');
    const [dialog, setDialog] = useState<{ category: CategoryRow | null } | null>(null);
    const [deleting, setDeleting] = useState<CategoryRow | null>(null);

    const tree = useMemo(() => toTree(categories), [categories]);

    const move = (row: CategoryRow, direction: 'up' | 'down') =>
        router.patch(`/admin/categories/${row.id}/move`, { direction }, { preserveScroll: true, preserveState: true });

    return (
        <AdminLayout>
            <Head title={t('admin.categories.title')} />

            <div className="mb-6 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold text-neutral-100">{t('admin.categories.title')}</h1>
                    <p className="mt-1 max-w-2xl text-sm text-neutral-400">{t('admin.categories.subtitle')}</p>
                </div>
                {canManage && (
                    <Button variant="primary" icon={Plus} onClick={() => setDialog({ category: null })}>
                        {t('admin.categories.new')}
                    </Button>
                )}
            </div>

            {tree.length > 0 ? (
                <div className={`${CARD} overflow-hidden`}>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className={THEAD}>
                                <tr>
                                    <th className="w-16 px-4 py-3 text-start font-medium">{t('admin.categories.columns.tile')}</th>
                                    <th className="px-4 py-3 text-start font-medium">{t('admin.categories.columns.category')}</th>
                                    <th className="px-4 py-3 text-start font-medium">{t('admin.categories.columns.link')}</th>
                                    <th className="px-4 py-3 text-start font-medium">{t('admin.categories.columns.products')}</th>
                                    {canManage && <th className="px-4 py-3 text-start font-medium">{t('admin.categories.columns.order')}</th>}
                                    <th className="px-4 py-3 text-start font-medium">{t('admin.categories.columns.status')}</th>
                                    {canManage && <th className="px-4 py-3 text-end font-medium">{t('admin.categories.columns.actions')}</th>}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-neutral-800">
                                {tree.map(({ row, depth, first, last }) => {
                                    const isGroup = row.children_count > 0;

                                    return (
                                        <tr key={row.id} className={`hover:bg-neutral-800/40 ${depth === 0 && isGroup ? 'bg-neutral-800/20' : ''}`}>
                                            <td className="px-4 py-3">
                                                <HintTooltip label={t(row.image ? 'admin.categories.homepageTile' : 'admin.categories.noTile')}>
                                                    <span className="flex h-10 w-10 items-center justify-center overflow-hidden rounded-md bg-neutral-100">
                                                        {row.image ? (
                                                            <img
                                                                src={row.image}
                                                                alt=""
                                                                className="max-h-full max-w-full object-contain"
                                                                loading="lazy"
                                                            />
                                                        ) : (
                                                            <ImageOff className="h-4 w-4 text-neutral-400" />
                                                        )}
                                                    </span>
                                                </HintTooltip>
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className={`flex items-center gap-2 ${depth === 1 ? 'ps-6' : ''}`}>
                                                    {depth === 1 && (
                                                        <CornerDownRight className="h-4 w-4 shrink-0 text-neutral-600 rtl:-scale-x-100" aria-hidden />
                                                    )}
                                                    <div className="min-w-0">
                                                        <div className="flex flex-wrap items-center gap-2">
                                                            <span className={`text-neutral-100 ${isGroup ? 'font-semibold' : 'font-medium'}`}>
                                                                {row.name_ar}
                                                            </span>
                                                            {isGroup && (
                                                                <HintTooltip label={t('admin.categories.groupHint')}>
                                                                    <span tabIndex={0}>
                                                                        <StatusPill tone="idle" icon={FolderTree}>
                                                                            {t('admin.categories.group')}
                                                                        </StatusPill>
                                                                    </span>
                                                                </HintTooltip>
                                                            )}
                                                            {row.is_offers_bucket && (
                                                                <StatusPill tone="idle" icon={Sparkles}>
                                                                    {t('admin.categories.usedByEvents')}
                                                                </StatusPill>
                                                            )}
                                                        </div>
                                                        {row.name_en && <div className="text-xs text-neutral-500">{row.name_en}</div>}
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3">
                                                <code dir="ltr" className="text-xs text-neutral-400">
                                                    {row.slug}
                                                </code>
                                            </td>
                                            <td className="px-4 py-3 text-xs whitespace-nowrap tabular-nums">
                                                {isGroup ? (
                                                    <span className="text-neutral-400">
                                                        {t('admin.categories.subcategories', { n: row.children_count })}
                                                    </span>
                                                ) : row.products_count > 0 ? (
                                                    canSeeProducts ? (
                                                        <a href={`/admin/products?category=${row.id}`} className="text-neutral-200 hover:underline">
                                                            {t('admin.categories.productCount', { n: row.products_count })}
                                                        </a>
                                                    ) : (
                                                        <span className="text-neutral-200">
                                                            {t('admin.categories.productCount', { n: row.products_count })}
                                                        </span>
                                                    )
                                                ) : (
                                                    <span className="text-neutral-500">{t('admin.categories.noProducts')}</span>
                                                )}
                                            </td>
                                            {canManage && (
                                                <td className="px-4 py-3">
                                                    <div className="flex items-center gap-0.5">
                                                        <ArrowButton
                                                            label={t('admin.categories.moveUp')}
                                                            disabled={first}
                                                            onClick={() => move(row, 'up')}
                                                        >
                                                            <ArrowUp className="h-4 w-4" />
                                                        </ArrowButton>
                                                        <ArrowButton
                                                            label={t('admin.categories.moveDown')}
                                                            disabled={last}
                                                            onClick={() => move(row, 'down')}
                                                        >
                                                            <ArrowDown className="h-4 w-4" />
                                                        </ArrowButton>
                                                    </div>
                                                </td>
                                            )}
                                            <td className="px-4 py-3">
                                                {canManage ? (
                                                    <StatusToggle
                                                        tone={row.is_active ? 'active' : 'idle'}
                                                        icon={row.is_active ? Eye : EyeOff}
                                                        label={t(row.is_active ? 'admin.categories.visible' : 'admin.categories.hidden')}
                                                        hint={t(row.is_active ? 'admin.categories.hideHint' : 'admin.categories.showHint')}
                                                        url={`/admin/categories/${row.id}/toggle`}
                                                    />
                                                ) : (
                                                    <StatusPill tone={row.is_active ? 'active' : 'idle'} icon={row.is_active ? Eye : EyeOff}>
                                                        {t(row.is_active ? 'admin.categories.visible' : 'admin.categories.hidden')}
                                                    </StatusPill>
                                                )}
                                                {/* Switched on, but kept off the store until it holds a
                                                    visible product. Said in words so it does not look broken. */}
                                                {row.store_state === 'empty' && (
                                                    <p className="mt-1 max-w-[12rem] text-xs text-amber-500/80">
                                                        {t(isGroup ? 'admin.categories.notOnStoreGroup' : 'admin.categories.notOnStore')}
                                                    </p>
                                                )}
                                            </td>
                                            {canManage && (
                                                <td className="px-4 py-3">
                                                    <div className="flex items-center justify-end gap-2">
                                                        <Button
                                                            size="sm"
                                                            variant="secondary"
                                                            icon={Pencil}
                                                            onClick={() => setDialog({ category: row })}
                                                        >
                                                            {t('admin.common.edit')}
                                                        </Button>
                                                        {row.delete_blocker ? (
                                                            // aria-disabled, not `disabled`, so the hint explaining
                                                            // WHY still shows on hover and focus.
                                                            <HintTooltip label={t(`admin.categories.blocked.${row.delete_blocker}`)}>
                                                                <button
                                                                    type="button"
                                                                    aria-disabled
                                                                    className="inline-flex cursor-not-allowed items-center gap-1.5 rounded-lg border border-red-500/20 px-3 py-1.5 text-xs font-semibold text-red-300/40"
                                                                >
                                                                    <Trash2 className="h-3.5 w-3.5" />
                                                                    {t('admin.common.delete')}
                                                                </button>
                                                            </HintTooltip>
                                                        ) : (
                                                            <Button size="sm" variant="danger" icon={Trash2} onClick={() => setDeleting(row)}>
                                                                {t('admin.common.delete')}
                                                            </Button>
                                                        )}
                                                    </div>
                                                </td>
                                            )}
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                </div>
            ) : (
                <div className="rounded-xl border border-dashed border-neutral-700 px-6 py-14 text-center">
                    <FolderTree className="mx-auto h-7 w-7 text-neutral-600" />
                    <p className="mt-3 text-sm font-medium text-neutral-200">{t('admin.categories.emptyTitle')}</p>
                    <p className="mx-auto mt-1 max-w-md text-xs text-neutral-500">{t('admin.categories.empty')}</p>
                    {canManage && (
                        <div className="mt-5">
                            <Button variant="primary" icon={Plus} onClick={() => setDialog({ category: null })}>
                                {t('admin.categories.new')}
                            </Button>
                        </div>
                    )}
                </div>
            )}

            {deleting && <CategoryDeleteDialog key={deleting.id} category={deleting} rows={categories} onClose={() => setDeleting(null)} />}

            {dialog && (
                <CategoryDialog key={dialog.category?.id ?? 'new'} category={dialog.category} rows={categories} onClose={() => setDialog(null)} />
            )}
        </AdminLayout>
    );
}
