import { Head, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Columns3, MoveHorizontal, Pencil, Plus } from 'lucide-react';
import AdminLayout from '@/layouts/admin-layout';
import Button from '@/components/admin/button';
import ConfirmDeleteButton from '@/components/admin/confirm-delete-button';
import ExportButtons from '@/components/admin/export-buttons';
import Modal from '@/components/admin/modal';
import ProductFormBody, { type Product } from '@/components/admin/product-form-body';
import ResizableTh from '@/components/admin/resizable-th';
import Select from '@/components/admin/select';
import StickyScrollWrapper from '@/components/admin/sticky-scroll-wrapper';
import UndoButton, { type UndoMeta } from '@/components/admin/undo-button';
import { useResizableColumns, type ColumnDef } from '@/hooks/use-resizable-columns';
import { useAdminT } from '@/i18n/use-admin-t';

const COLUMNS: ColumnDef[] = [
    { key: 'product', defaultWidth: 300, minWidth: 160 },
    { key: 'sku', defaultWidth: 120, minWidth: 80 },
    { key: 'smacc_sku', defaultWidth: 140, minWidth: 90 },
    { key: 'category', defaultWidth: 150, minWidth: 90 },
    { key: 'price', defaultWidth: 110, minWidth: 70 },
    { key: 'stock', defaultWidth: 90, minWidth: 60 },
    { key: 'status', defaultWidth: 110, minWidth: 80 },
    { key: 'actions', defaultWidth: 170, minWidth: 130 },
];

interface ProductRow {
    id: number;
    name_ar: string;
    name_en: string | null;
    image: string | null;
    sku: string;
    smacc_sku: string | null;
    category: { name_ar: string; name_en: string | null } | null;
    price: number;
    sale_price: number | null;
    stock: number;
    is_low_stock: boolean;
    is_active: boolean;
    is_featured: boolean;
    is_coming_soon: boolean;
    needs_price: boolean;
    needs_image: boolean;
    needs_description: boolean;
}

interface Category {
    id: number;
    name_ar: string;
    name_en: string | null;
}

interface Filters {
    search: string | null;
    category: number | null;
    status: string | null;
    sort: string | null;
    direction: 'asc' | 'desc';
}

interface Paginator<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
}

// Edit modal body: fetches the full product (fields + images), then reuses the
// shared form. Re-fetches after image edits so the gallery stays current.
function ProductEditor({ productId, categories, onSaved }: { productId: number; categories: Category[]; onSaved: () => void }) {
    const { t } = useAdminT();
    const [product, setProduct] = useState<Product | null>(null);
    const [failed, setFailed] = useState(false);
    const [reload, setReload] = useState(0);

    useEffect(() => {
        let alive = true;
        fetch(`/admin/products/${productId}/detail`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then((r) => {
                if (!r.ok) throw new Error();
                return r.json();
            })
            .then((d: { product: Product }) => alive && setProduct(d.product))
            .catch(() => alive && setFailed(true));
        return () => { alive = false; };
    }, [productId, reload]);

    if (failed) return <p className="py-6 text-sm text-red-500">{t('admin.products.detailLoadError')}</p>;
    if (!product) return <p className="py-8 text-center text-sm text-neutral-400">{t('admin.common.loading')}</p>;

    return (
        <ProductFormBody
            key={product.id}
            product={product}
            categories={categories}
            modal
            onSaved={onSaved}
            onImageChanged={() => setReload((n) => n + 1)}
        />
    );
}

export default function ProductsIndex({
    products,
    filters,
    categories,
    draftCount = 0,
    undoMeta = null,
}: {
    products: Paginator<ProductRow>;
    filters: Filters;
    categories: Category[];
    draftCount?: number;
    undoMeta?: UndoMeta | null;
}) {
    const { t, i18n } = useAdminT();
    const [search, setSearch] = useState(filters.search ?? '');
    // EN-first admin: show the English name/label when set, else the Arabic.
    const loc = (ar: string, en: string | null) => (i18n.language === 'en' && en ? en : ar);
    const rc = useResizableColumns({ tableKey: 'products', columns: COLUMNS });
    const [editing, setEditing] = useState<ProductRow | 'new' | null>(null);

    const query = (next: Record<string, unknown>) => {
        router.get(
            '/admin/products',
            {
                search: search || undefined,
                category: filters.category || undefined,
                status: filters.status || undefined,
                sort: filters.sort || undefined,
                direction: filters.sort ? filters.direction : undefined,
                ...next,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const toggleSort = (col: string) => {
        const direction = filters.sort === col && filters.direction === 'asc' ? 'desc' : 'asc';
        query({ sort: col, direction });
    };

    const exportParams = {
        search: filters.search,
        category: filters.category,
        status: filters.status,
        sort: filters.sort,
        direction: filters.sort ? filters.direction : undefined,
    };

    return (
        <AdminLayout title={t('admin.products.title')}>
            <Head title={t('admin.products.title')} />

            <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        query({ page: undefined });
                    }}
                    className="flex w-full gap-2 sm:w-auto"
                >
                    <input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder={t('admin.products.searchPlaceholder')}
                        className="min-w-0 flex-1 rounded border border-neutral-300 px-3 py-1.5 text-sm sm:w-56 sm:flex-none dark:border-neutral-700 dark:bg-neutral-800"
                    />
                    <button type="submit" className="shrink-0 rounded bg-neutral-900 px-3 py-1.5 text-sm text-white dark:bg-white dark:text-neutral-900">
                        {t('admin.common.search')}
                    </button>
                </form>

                <Select
                    value={filters.category ? String(filters.category) : ''}
                    onChange={(v) => query({ category: v || undefined, page: undefined })}
                    options={[
                        { value: '', label: t('admin.products.allCategories') },
                        ...categories.map((c) => ({ value: String(c.id), label: loc(c.name_ar, c.name_en) })),
                    ]}
                    className="w-full sm:w-auto"
                />

                <Select
                    value={filters.status ?? ''}
                    onChange={(v) => query({ status: v || undefined, page: undefined })}
                    options={[
                        { value: '', label: t('admin.products.statusAll') },
                        { value: 'active', label: t('admin.products.statusActive') },
                        { value: 'draft', label: draftCount > 0 ? `${t('admin.products.statusDrafts')} (${draftCount})` : t('admin.products.statusDrafts') },
                    ]}
                    className="w-full sm:w-auto"
                />

                <div className="sm:ms-auto">
                    <Button variant="primary" icon={Plus} className="w-full sm:w-auto" onClick={() => setEditing('new')}>{t('admin.products.newProduct')}</Button>
                </div>
            </div>

            {/* Count + undo + reset/hint + export */}
            <div className="mb-3 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                <div className="flex flex-wrap items-center gap-3">
                    <span className="text-sm text-neutral-400">{t('admin.products.count', { n: products.total })}</span>
                    <UndoButton section="products" undoMeta={undoMeta} />
                    {rc.isDefault ? (
                        <span className="hidden items-center gap-1.5 text-xs text-neutral-500 lg:inline-flex">
                            <MoveHorizontal className="h-3.5 w-3.5" /> {t('admin.common.dragToResize')}
                        </span>
                    ) : (
                        <Button size="sm" variant="ghost" icon={Columns3} onClick={rc.resetAll}>{t('admin.common.resetColumns')}</Button>
                    )}
                </div>
                <ExportButtons base="/admin/products/export" params={exportParams} />
            </div>

            <StickyScrollWrapper className="rounded-lg border border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
                <table className="min-w-full table-fixed text-sm" style={{ width: rc.tableWidth }}>
                    <thead className="border-b border-neutral-200 bg-neutral-50 text-left text-neutral-600 dark:border-neutral-800 dark:bg-neutral-800/50 dark:text-neutral-300">
                        <tr>
                            <ResizableTh colKey="product" width={rc.widths.product} resizeProps={rc.getResizeHandleProps('product')} resizing={rc.resizing === 'product'} sortKey="name_ar" sort={filters.sort} direction={filters.direction} onSort={toggleSort}>{t('admin.products.cols.product')}</ResizableTh>
                            <ResizableTh colKey="sku" width={rc.widths.sku} resizeProps={rc.getResizeHandleProps('sku')} resizing={rc.resizing === 'sku'} sortKey="sku" sort={filters.sort} direction={filters.direction} onSort={toggleSort}>{t('admin.products.cols.sku')}</ResizableTh>
                            <ResizableTh colKey="smacc_sku" width={rc.widths.smacc_sku} resizeProps={rc.getResizeHandleProps('smacc_sku')} resizing={rc.resizing === 'smacc_sku'} sortKey="smacc_sku" sort={filters.sort} direction={filters.direction} onSort={toggleSort}>{t('admin.products.cols.smaccSku')}</ResizableTh>
                            <ResizableTh colKey="category" width={rc.widths.category} resizeProps={rc.getResizeHandleProps('category')} resizing={rc.resizing === 'category'} sortKey="category" sort={filters.sort} direction={filters.direction} onSort={toggleSort}>{t('admin.products.cols.category')}</ResizableTh>
                            <ResizableTh colKey="price" width={rc.widths.price} resizeProps={rc.getResizeHandleProps('price')} resizing={rc.resizing === 'price'} sortKey="price" sort={filters.sort} direction={filters.direction} onSort={toggleSort}>{t('admin.products.cols.price')}</ResizableTh>
                            <ResizableTh colKey="stock" width={rc.widths.stock} resizeProps={rc.getResizeHandleProps('stock')} resizing={rc.resizing === 'stock'} sortKey="stock" sort={filters.sort} direction={filters.direction} onSort={toggleSort}>{t('admin.products.cols.stock')}</ResizableTh>
                            <ResizableTh colKey="status" width={rc.widths.status} resizeProps={rc.getResizeHandleProps('status')} resizing={rc.resizing === 'status'} sortKey="is_active" sort={filters.sort} direction={filters.direction} onSort={toggleSort}>{t('admin.products.cols.status')}</ResizableTh>
                            <ResizableTh colKey="actions" width={rc.widths.actions} resizeProps={rc.getResizeHandleProps('actions')} resizing={rc.resizing === 'actions'} className="text-end">{t('admin.common.actions')}</ResizableTh>
                        </tr>
                    </thead>
                    <tbody>
                        {products.data.length === 0 && (
                            <tr>
                                <td colSpan={8} className="px-4 py-8 text-center text-neutral-400">{t('admin.products.empty')}</td>
                            </tr>
                        )}
                        {products.data.map((p) => (
                            <tr key={p.id} className="border-b border-neutral-100 last:border-0 dark:border-neutral-800">
                                <td className="px-4 py-3">
                                    <div className="flex min-w-0 items-center gap-2">
                                        {p.image ? (
                                            <img src={p.image} alt="" className="h-9 w-9 shrink-0 rounded object-cover" />
                                        ) : (
                                            <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded bg-neutral-100 text-sm dark:bg-neutral-800">🌴</div>
                                        )}
                                        <span dir="auto" className="truncate">{loc(p.name_ar, p.name_en)}</span>
                                        {p.is_featured && <span className="shrink-0 rounded bg-amber-100 px-1.5 py-0.5 text-xs text-amber-800 dark:bg-amber-950 dark:text-amber-200">{t('admin.products.featured')}</span>}
                                    </div>
                                </td>
                                <td className="truncate px-4 py-3 font-mono text-neutral-500">{p.sku}</td>
                                <td className="truncate px-4 py-3 font-mono text-neutral-500">{p.smacc_sku ?? '—'}</td>
                                <td className="truncate px-4 py-3" dir="auto">{p.category ? loc(p.category.name_ar, p.category.name_en) : '—'}</td>
                                <td className="px-4 py-3">
                                    {p.sale_price !== null ? (
                                        <span>
                                            <span className="text-neutral-400 line-through">{p.price}</span>{' '}
                                            <span className="font-medium">{p.sale_price}</span>
                                        </span>
                                    ) : (
                                        p.price
                                    )}
                                </td>
                                <td className="px-4 py-3">
                                    <span className={p.is_low_stock ? 'font-semibold text-red-600 dark:text-red-400' : ''}>{p.stock}</span>
                                </td>
                                <td className="px-4 py-3">
                                    <div className="flex flex-col items-start gap-1">
                                        {p.is_active ? (
                                            <span className="rounded-full bg-green-100 px-2 py-0.5 text-xs text-green-800 dark:bg-green-950 dark:text-green-200">{t('admin.products.active')}</span>
                                        ) : (
                                            <span className="rounded-full bg-neutral-100 px-2 py-0.5 text-xs text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300">{t('admin.products.hidden')}</span>
                                        )}
                                        {!p.is_active && p.is_coming_soon && (
                                            <span className="rounded-full bg-teal-100 px-2 py-0.5 text-xs text-teal-800 dark:bg-teal-950 dark:text-teal-200">{t('admin.products.comingSoon')}</span>
                                        )}
                                        {!p.is_active && (p.needs_price || p.needs_image || p.needs_description) && (
                                            <span className="flex flex-wrap gap-1">
                                                {p.needs_price && <span className="rounded bg-amber-100 px-1.5 py-0.5 text-[11px] text-amber-800 dark:bg-amber-950 dark:text-amber-200">{t('admin.products.needsPrice')}</span>}
                                                {p.needs_image && <span className="rounded bg-amber-100 px-1.5 py-0.5 text-[11px] text-amber-800 dark:bg-amber-950 dark:text-amber-200">{t('admin.products.needsImage')}</span>}
                                                {p.needs_description && <span className="rounded bg-amber-100 px-1.5 py-0.5 text-[11px] text-amber-800 dark:bg-amber-950 dark:text-amber-200">{t('admin.products.needsDescription')}</span>}
                                            </span>
                                        )}
                                    </div>
                                </td>
                                <td className="px-4 py-3">
                                    <div className="flex items-center justify-end gap-2">
                                        <Button size="sm" variant="secondary" icon={Pencil} onClick={() => setEditing(p)}>{t('admin.common.edit')}</Button>
                                        <ConfirmDeleteButton
                                            itemName={loc(p.name_ar, p.name_en)}
                                            reversible
                                            onConfirm={() => router.delete(`/admin/products/${p.id}`, { preserveScroll: true })}
                                        />
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </StickyScrollWrapper>

            {products.total > products.data.length && (
                <div className="mt-4 flex flex-wrap gap-1">
                    {products.links.map((link, i) => (
                        <button
                            key={i}
                            type="button"
                            disabled={!link.url}
                            onClick={() => link.url && router.get(link.url, {}, { preserveState: true, preserveScroll: true })}
                            className={`rounded px-3 py-1 text-sm ${link.active ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-900' : 'text-neutral-600 disabled:opacity-40 dark:text-neutral-300'}`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>
            )}

            <Modal
                open={editing !== null}
                onClose={() => setEditing(null)}
                size="lg"
                title={editing && editing !== 'new' ? t('admin.products.form.editHead', { name: loc(editing.name_ar, editing.name_en) }) : t('admin.products.form.newTitle')}
            >
                {editing === 'new' && <ProductFormBody modal product={null} categories={categories} onSaved={() => setEditing(null)} />}
                {editing && editing !== 'new' && <ProductEditor key={editing.id} productId={editing.id} categories={categories} onSaved={() => setEditing(null)} />}
            </Modal>
        </AdminLayout>
    );
}
