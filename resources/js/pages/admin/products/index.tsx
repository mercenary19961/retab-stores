import Button from '@/components/admin/button';
import ConfirmDeleteButton from '@/components/admin/confirm-delete-button';
import ExportButtons from '@/components/admin/export-buttons';
import Modal from '@/components/admin/modal';
import Pagination from '@/components/admin/pagination';
import ProductFormBody, { type Product } from '@/components/admin/product-form-body';
import ResizableTh from '@/components/admin/resizable-th';
import Select from '@/components/admin/select';
import StatusToggle from '@/components/admin/status-toggle';
import StickyScrollWrapper from '@/components/admin/sticky-scroll-wrapper';
import UndoButton, { type UndoMeta } from '@/components/admin/undo-button';
import CopyText from '@/components/copy-text';
import ImageLightbox from '@/components/image-lightbox';
import StatusPill from '@/components/status-pill';
import { useResizableColumns, type ColumnDef } from '@/hooks/use-resizable-columns';
import { useAdminT } from '@/i18n/use-admin-t';
import AdminLayout from '@/layouts/admin-layout';
import { Head, router } from '@inertiajs/react';
import {
    AlignLeft,
    ArrowDown,
    ArrowUp,
    Columns3,
    Eye,
    EyeOff,
    ImageOff,
    LayoutGrid,
    MoveHorizontal,
    Pencil,
    Plus,
    Sparkles,
    Table,
    Tag,
} from 'lucide-react';
import { useEffect, useState } from 'react';

const COLUMNS: ColumnDef[] = [
    { key: 'product', defaultWidth: 300, minWidth: 160 },
    { key: 'sku', defaultWidth: 120, minWidth: 80 },
    { key: 'smacc_sku', defaultWidth: 140, minWidth: 90 },
    { key: 'category', defaultWidth: 150, minWidth: 90 },
    { key: 'price', defaultWidth: 110, minWidth: 70 },
    { key: 'stock', defaultWidth: 90, minWidth: 60 },
    // Holds the visibility toggle plus up to three completeness pills.
    { key: 'status', defaultWidth: 160, minWidth: 120 },
    { key: 'actions', defaultWidth: 170, minWidth: 130 },
];

interface ProductRow {
    id: number;
    name_ar: string;
    name_en: string | null;
    image: string | null;
    images: string[];
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
    per_page: number;
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
        return () => {
            alive = false;
        };
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
    const [view, setView] = useState<'table' | 'cards'>('table');

    // Persist the table/card choice so it survives navigation + reloads.
    useEffect(() => {
        const saved = localStorage.getItem('admin.products.view');
        if (saved === 'cards' || saved === 'table') setView(saved);
    }, []);
    const changeView = (v: 'table' | 'cards') => {
        setView(v);
        try {
            localStorage.setItem('admin.products.view', v);
        } catch {
            /* storage unavailable — ignore */
        }
    };

    // Click a product thumbnail → open its images in the shared zoom viewer.
    const [lightbox, setLightbox] = useState<{ images: string[]; name: string } | null>(null);
    const [lightboxIndex, setLightboxIndex] = useState(0);
    const openLightbox = (p: ProductRow) => {
        if (p.images.length === 0) return;
        setLightboxIndex(0);
        setLightbox({ images: p.images, name: loc(p.name_ar, p.name_en) });
    };

    const query = (next: Record<string, unknown>) => {
        router.get(
            '/admin/products',
            {
                search: search || undefined,
                category: filters.category || undefined,
                status: filters.status || undefined,
                sort: filters.sort || undefined,
                direction: filters.sort ? filters.direction : undefined,
                per_page: filters.per_page || undefined,
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

    const sortOptions = [
        { value: '', label: t('admin.products.sortDefault') },
        { value: 'name_ar', label: t('admin.products.cols.product') },
        { value: 'sku', label: t('admin.products.cols.sku') },
        { value: 'smacc_sku', label: t('admin.products.cols.smaccSku') },
        { value: 'category', label: t('admin.products.cols.category') },
        { value: 'price', label: t('admin.products.cols.price') },
        { value: 'stock', label: t('admin.products.cols.stock') },
        { value: 'is_active', label: t('admin.products.cols.status') },
    ];

    // Shared cell renderers so the table + card views can't drift apart.
    const renderPrice = (p: ProductRow) =>
        p.sale_price !== null ? (
            <span>
                <span className="text-neutral-400 line-through">{p.price}</span> <span className="font-medium">{p.sale_price}</span>
            </span>
        ) : (
            p.price
        );

    const statusBadges = (p: ProductRow) => (
        <>
            {p.is_active ? (
                <StatusToggle
                    tone="active"
                    icon={Eye}
                    label={t('admin.products.active')}
                    url={`/admin/products/${p.id}/toggle-active`}
                    hint={t('admin.products.deactivateHint')}
                />
            ) : (
                <StatusToggle
                    tone="idle"
                    icon={EyeOff}
                    label={t('admin.products.hidden')}
                    url={`/admin/products/${p.id}/toggle-active`}
                    disabled={p.needs_image}
                    hint={p.needs_image ? t('admin.products.activateBlockedNoImage') : t('admin.products.activateHint')}
                />
            )}
            {!p.is_active && p.is_coming_soon && (
                <StatusPill tone="idle" icon={Sparkles}>
                    {t('admin.products.comingSoon')}
                </StatusPill>
            )}
            {/* What a draft still needs. Deliberately `idle` rather than amber: the
                row already says Hidden and the page has a Drafts filter, so three
                tinted chips per row across 63 drafts would only be wallpaper. */}
            {!p.is_active && (p.needs_price || p.needs_image || p.needs_description) && (
                <span className="flex flex-wrap gap-1">
                    {p.needs_price && (
                        <StatusPill tone="idle" icon={Tag}>
                            {t('admin.products.needsPrice')}
                        </StatusPill>
                    )}
                    {p.needs_image && (
                        <StatusPill tone="idle" icon={ImageOff}>
                            {t('admin.products.needsImage')}
                        </StatusPill>
                    )}
                    {p.needs_description && (
                        <StatusPill tone="idle" icon={AlignLeft}>
                            {t('admin.products.needsDescription')}
                        </StatusPill>
                    )}
                </span>
            )}
        </>
    );

    // Product thumbnail: a 🌴 placeholder when imageless, otherwise a button that
    // opens the image viewer. `box` sizes it, `ph` sizes the placeholder emoji.
    const thumb = (p: ProductRow, box: string, ph: string) =>
        p.image ? (
            <button
                type="button"
                onClick={() => openLightbox(p)}
                aria-label={t('admin.products.viewImages')}
                title={t('admin.products.viewImages')}
                className={`${box} shrink-0 cursor-zoom-in overflow-hidden rounded`}
            >
                <img src={p.image} alt="" className="h-full w-full object-cover" />
            </button>
        ) : (
            <div className={`flex ${box} shrink-0 items-center justify-center rounded bg-neutral-100 ${ph} dark:bg-neutral-800`}>🌴</div>
        );

    const rowActions = (p: ProductRow) => (
        <>
            <Button size="sm" variant="secondary" icon={Pencil} onClick={() => setEditing(p)}>
                {t('admin.common.edit')}
            </Button>
            <ConfirmDeleteButton
                itemName={loc(p.name_ar, p.name_en)}
                reversible
                onConfirm={() => router.delete(`/admin/products/${p.id}`, { preserveScroll: true })}
            />
        </>
    );

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
                    <button
                        type="submit"
                        className="shrink-0 rounded bg-neutral-900 px-3 py-1.5 text-sm text-white dark:bg-white dark:text-neutral-900"
                    >
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
                        {
                            /*
                             * Labelled "Hidden", not "Drafts", because that is what it
                             * filters: is_active = false, which covers both unfinished
                             * imports AND products deliberately taken off the shopfront
                             * (e.g. everything still on Zid photography). The query
                             * VALUE stays `draft` so the dashboard's existing
                             * ?status=draft action link keeps working.
                             */
                            value: 'draft',
                            label: draftCount > 0 ? `${t('admin.products.hidden')} (${draftCount})` : t('admin.products.hidden'),
                        },
                        { value: 'coming_soon', label: t('admin.products.comingSoon') },
                    ]}
                    className="w-full sm:w-auto"
                />

                <div className="sm:ms-auto">
                    <Button variant="primary" icon={Plus} className="w-full sm:w-auto" onClick={() => setEditing('new')}>
                        {t('admin.products.newProduct')}
                    </Button>
                </div>
            </div>

            {/* Count + undo + reset/hint + sort + view toggle + export */}
            <div className="mb-3 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                <div className="flex flex-wrap items-center gap-3">
                    <span className="text-sm text-neutral-400">{t('admin.products.count', { n: products.total })}</span>
                    <UndoButton section="products" undoMeta={undoMeta} />
                    {view === 'table' &&
                        (rc.isDefault ? (
                            <span className="hidden items-center gap-1.5 text-xs text-neutral-500 lg:inline-flex">
                                <MoveHorizontal className="h-3.5 w-3.5" /> {t('admin.common.dragToResize')}
                            </span>
                        ) : (
                            <Button size="sm" variant="ghost" icon={Columns3} onClick={rc.resetAll}>
                                {t('admin.common.resetColumns')}
                            </Button>
                        ))}
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    {/* Card view has no sortable headers, so surface sorting here. */}
                    {view === 'cards' && (
                        <div className="flex items-center gap-1.5">
                            <Select
                                value={filters.sort ?? ''}
                                onChange={(v) => query({ sort: v || undefined, direction: v ? filters.direction : undefined, page: undefined })}
                                options={sortOptions}
                                className="w-40"
                            />
                            {filters.sort && (
                                <button
                                    type="button"
                                    onClick={() => query({ direction: filters.direction === 'asc' ? 'desc' : 'asc' })}
                                    title={t('admin.products.sortBy')}
                                    className="rounded border border-neutral-300 p-1.5 text-neutral-600 hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
                                >
                                    {filters.direction === 'asc' ? <ArrowUp className="h-4 w-4" /> : <ArrowDown className="h-4 w-4" />}
                                </button>
                            )}
                        </div>
                    )}

                    <div className="inline-flex rounded-lg border border-neutral-300 p-0.5 dark:border-neutral-700">
                        <button
                            type="button"
                            onClick={() => changeView('table')}
                            aria-pressed={view === 'table'}
                            aria-label={t('admin.products.viewTable')}
                            title={t('admin.products.viewTable')}
                            className={`rounded-md p-1.5 transition-colors ${view === 'table' ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-900' : 'text-neutral-500 hover:text-neutral-900 dark:hover:text-white'}`}
                        >
                            <Table className="h-4 w-4" />
                        </button>
                        <button
                            type="button"
                            onClick={() => changeView('cards')}
                            aria-pressed={view === 'cards'}
                            aria-label={t('admin.products.viewCards')}
                            title={t('admin.products.viewCards')}
                            className={`rounded-md p-1.5 transition-colors ${view === 'cards' ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-900' : 'text-neutral-500 hover:text-neutral-900 dark:hover:text-white'}`}
                        >
                            <LayoutGrid className="h-4 w-4" />
                        </button>
                    </div>

                    <ExportButtons base="/admin/products/export" params={exportParams} />
                </div>
            </div>

            {view === 'table' ? (
                <StickyScrollWrapper className="rounded-lg border border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
                    <table className="min-w-full table-fixed text-sm" style={{ width: rc.tableWidth }}>
                        <thead className="border-b border-neutral-200 bg-neutral-50 text-left text-neutral-600 dark:border-neutral-800 dark:bg-neutral-800/50 dark:text-neutral-300">
                            <tr>
                                <ResizableTh
                                    colKey="product"
                                    width={rc.widths.product}
                                    resizeProps={rc.getResizeHandleProps('product')}
                                    resizing={rc.resizing === 'product'}
                                    sortKey="name_ar"
                                    sort={filters.sort}
                                    direction={filters.direction}
                                    onSort={toggleSort}
                                >
                                    {t('admin.products.cols.product')}
                                </ResizableTh>
                                <ResizableTh
                                    colKey="sku"
                                    width={rc.widths.sku}
                                    resizeProps={rc.getResizeHandleProps('sku')}
                                    resizing={rc.resizing === 'sku'}
                                    sortKey="sku"
                                    sort={filters.sort}
                                    direction={filters.direction}
                                    onSort={toggleSort}
                                >
                                    {t('admin.products.cols.sku')}
                                </ResizableTh>
                                <ResizableTh
                                    colKey="smacc_sku"
                                    width={rc.widths.smacc_sku}
                                    resizeProps={rc.getResizeHandleProps('smacc_sku')}
                                    resizing={rc.resizing === 'smacc_sku'}
                                    sortKey="smacc_sku"
                                    sort={filters.sort}
                                    direction={filters.direction}
                                    onSort={toggleSort}
                                >
                                    {t('admin.products.cols.smaccSku')}
                                </ResizableTh>
                                <ResizableTh
                                    colKey="category"
                                    width={rc.widths.category}
                                    resizeProps={rc.getResizeHandleProps('category')}
                                    resizing={rc.resizing === 'category'}
                                    sortKey="category"
                                    sort={filters.sort}
                                    direction={filters.direction}
                                    onSort={toggleSort}
                                >
                                    {t('admin.products.cols.category')}
                                </ResizableTh>
                                <ResizableTh
                                    colKey="price"
                                    width={rc.widths.price}
                                    resizeProps={rc.getResizeHandleProps('price')}
                                    resizing={rc.resizing === 'price'}
                                    sortKey="price"
                                    sort={filters.sort}
                                    direction={filters.direction}
                                    onSort={toggleSort}
                                >
                                    {t('admin.products.cols.price')}
                                </ResizableTh>
                                <ResizableTh
                                    colKey="stock"
                                    width={rc.widths.stock}
                                    resizeProps={rc.getResizeHandleProps('stock')}
                                    resizing={rc.resizing === 'stock'}
                                    sortKey="stock"
                                    sort={filters.sort}
                                    direction={filters.direction}
                                    onSort={toggleSort}
                                >
                                    {t('admin.products.cols.stock')}
                                </ResizableTh>
                                <ResizableTh
                                    colKey="status"
                                    width={rc.widths.status}
                                    resizeProps={rc.getResizeHandleProps('status')}
                                    resizing={rc.resizing === 'status'}
                                    sortKey="is_active"
                                    sort={filters.sort}
                                    direction={filters.direction}
                                    onSort={toggleSort}
                                >
                                    {t('admin.products.cols.status')}
                                </ResizableTh>
                                <ResizableTh
                                    colKey="actions"
                                    width={rc.widths.actions}
                                    resizeProps={rc.getResizeHandleProps('actions')}
                                    resizing={rc.resizing === 'actions'}
                                    className="text-end"
                                >
                                    {t('admin.common.actions')}
                                </ResizableTh>
                            </tr>
                        </thead>
                        <tbody>
                            {products.data.length === 0 && (
                                <tr>
                                    <td colSpan={8} className="px-4 py-8 text-center text-neutral-400">
                                        {t('admin.products.empty')}
                                    </td>
                                </tr>
                            )}
                            {products.data.map((p) => (
                                <tr key={p.id} className="border-b border-neutral-100 last:border-0 dark:border-neutral-800">
                                    <td className="px-4 py-3">
                                        <div className="flex min-w-0 items-center gap-2">
                                            {thumb(p, 'h-9 w-9', 'text-sm')}
                                            <span dir="auto" className="truncate">
                                                {loc(p.name_ar, p.name_en)}
                                            </span>
                                            {p.is_featured && (
                                                <span className="shrink-0 rounded bg-amber-100 px-1.5 py-0.5 text-xs text-amber-800 dark:bg-amber-950 dark:text-amber-200">
                                                    {t('admin.products.featured')}
                                                </span>
                                            )}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 font-mono text-neutral-500">
                                        <CopyText value={p.sku} copyLabel={t('admin.common.copy')} copiedLabel={t('admin.common.copied')} />
                                    </td>
                                    <td className="px-4 py-3 font-mono text-neutral-500">
                                        {p.smacc_sku ? (
                                            <CopyText value={p.smacc_sku} copyLabel={t('admin.common.copy')} copiedLabel={t('admin.common.copied')} />
                                        ) : (
                                            '—'
                                        )}
                                    </td>
                                    <td className="truncate px-4 py-3" dir="auto">
                                        {p.category ? loc(p.category.name_ar, p.category.name_en) : '—'}
                                    </td>
                                    <td className="px-4 py-3">{renderPrice(p)}</td>
                                    <td className="px-4 py-3">
                                        <span className={p.is_low_stock ? 'font-semibold text-red-600 dark:text-red-400' : ''}>{p.stock}</span>
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex flex-col items-start gap-1">{statusBadges(p)}</div>
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center justify-end gap-2">{rowActions(p)}</div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </StickyScrollWrapper>
            ) : products.data.length === 0 ? (
                <div className="rounded-lg border border-neutral-200 bg-white px-4 py-10 text-center text-neutral-400 dark:border-neutral-800 dark:bg-neutral-900">
                    {t('admin.products.empty')}
                </div>
            ) : (
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    {products.data.map((p) => (
                        <div
                            key={p.id}
                            className="flex flex-col rounded-lg border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-900"
                        >
                            <div className="flex items-start gap-3">
                                {thumb(p, 'h-16 w-16', 'text-xl')}
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-start gap-1.5">
                                        <span dir="auto" className="line-clamp-2 font-medium">
                                            {loc(p.name_ar, p.name_en)}
                                        </span>
                                        {p.is_featured && (
                                            <span className="shrink-0 rounded bg-amber-100 px-1.5 py-0.5 text-xs text-amber-800 dark:bg-amber-950 dark:text-amber-200">
                                                {t('admin.products.featured')}
                                            </span>
                                        )}
                                    </div>
                                    <p dir="auto" className="mt-0.5 truncate text-xs text-neutral-500">
                                        {p.category ? loc(p.category.name_ar, p.category.name_en) : '—'}
                                    </p>
                                </div>
                            </div>

                            <dl className="mt-3 space-y-1.5 border-t border-neutral-100 pt-3 text-sm dark:border-neutral-800">
                                <div className="flex items-center justify-between gap-2">
                                    <dt className="text-neutral-400">{t('admin.products.cols.sku')}</dt>
                                    <dd className="min-w-0 font-mono text-neutral-500">
                                        <CopyText value={p.sku} copyLabel={t('admin.common.copy')} copiedLabel={t('admin.common.copied')} />
                                    </dd>
                                </div>
                                <div className="flex items-center justify-between gap-2">
                                    <dt className="text-neutral-400">{t('admin.products.cols.smaccSku')}</dt>
                                    <dd className="min-w-0 font-mono text-neutral-500">
                                        {p.smacc_sku ? (
                                            <CopyText value={p.smacc_sku} copyLabel={t('admin.common.copy')} copiedLabel={t('admin.common.copied')} />
                                        ) : (
                                            '—'
                                        )}
                                    </dd>
                                </div>
                                <div className="flex items-center justify-between gap-2">
                                    <dt className="text-neutral-400">{t('admin.products.cols.price')}</dt>
                                    <dd>{renderPrice(p)}</dd>
                                </div>
                                <div className="flex items-center justify-between gap-2">
                                    <dt className="text-neutral-400">{t('admin.products.cols.stock')}</dt>
                                    <dd className={p.is_low_stock ? 'font-semibold text-red-600 dark:text-red-400' : ''}>{p.stock}</dd>
                                </div>
                            </dl>

                            <div className="mt-3 flex flex-wrap gap-1">{statusBadges(p)}</div>

                            <div className="mt-4 flex items-center justify-end gap-2 border-t border-neutral-100 pt-3 dark:border-neutral-800">
                                {rowActions(p)}
                            </div>
                        </div>
                    ))}
                </div>
            )}

            <Pagination paginator={products} perPage={filters.per_page} />

            <Modal
                open={editing !== null}
                onClose={() => setEditing(null)}
                size="lg"
                title={
                    editing && editing !== 'new'
                        ? t('admin.products.form.editHead', { name: loc(editing.name_ar, editing.name_en) })
                        : t('admin.products.form.newTitle')
                }
            >
                {editing === 'new' && <ProductFormBody modal product={null} categories={categories} onSaved={() => setEditing(null)} />}
                {editing && editing !== 'new' && (
                    <ProductEditor key={editing.id} productId={editing.id} categories={categories} onSaved={() => setEditing(null)} />
                )}
            </Modal>

            <ImageLightbox
                open={lightbox !== null}
                onOpenChange={(v) => !v && setLightbox(null)}
                images={lightbox?.images ?? []}
                imagesFull={lightbox?.images ?? []}
                name={lightbox?.name ?? ''}
                active={lightboxIndex}
                setActive={setLightboxIndex}
                labels={{
                    close: t('admin.common.close'),
                    zoomIn: t('admin.common.zoomIn'),
                    zoomOut: t('admin.common.zoomOut'),
                    resetZoom: t('admin.common.resetZoom'),
                    previous: t('admin.common.prevImage'),
                    next: t('admin.common.nextImage'),
                }}
            />
        </AdminLayout>
    );
}
