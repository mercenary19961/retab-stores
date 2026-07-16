import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Columns3, MoveHorizontal, Pencil, Plus, Trash2 } from 'lucide-react';
import AdminLayout from '@/layouts/admin-layout';
import Button from '@/components/admin/button';
import ExportButtons from '@/components/admin/export-buttons';
import ResizableTh from '@/components/admin/resizable-th';
import StickyScrollWrapper from '@/components/admin/sticky-scroll-wrapper';
import UndoButton, { type UndoMeta } from '@/components/admin/undo-button';
import { useResizableColumns, type ColumnDef } from '@/hooks/use-resizable-columns';

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
    image: string | null;
    sku: string;
    smacc_sku: string | null;
    category: string | null;
    price: number;
    sale_price: number | null;
    stock: number;
    is_low_stock: boolean;
    is_active: boolean;
    is_featured: boolean;
}

interface Category {
    id: number;
    name_ar: string;
}

interface Filters {
    search: string | null;
    category: number | null;
    sort: string | null;
    direction: 'asc' | 'desc';
}

interface Paginator<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
}

export default function ProductsIndex({
    products,
    filters,
    categories,
    undoMeta = null,
}: {
    products: Paginator<ProductRow>;
    filters: Filters;
    categories: Category[];
    undoMeta?: UndoMeta | null;
}) {
    const [search, setSearch] = useState(filters.search ?? '');
    const rc = useResizableColumns({ tableKey: 'products', columns: COLUMNS });

    const query = (next: Record<string, unknown>) => {
        router.get(
            '/admin/products',
            {
                search: search || undefined,
                category: filters.category || undefined,
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

    const destroy = (p: ProductRow) => {
        if (!window.confirm(`Delete "${p.name_ar}"? It will be hidden but order history is kept.`)) return;
        router.delete(`/admin/products/${p.id}`, { preserveScroll: true });
    };

    const exportParams = {
        search: filters.search,
        category: filters.category,
        sort: filters.sort,
        direction: filters.sort ? filters.direction : undefined,
    };

    return (
        <AdminLayout title="Products">
            <Head title="Products" />

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
                        placeholder="Search name or SKU…"
                        className="min-w-0 flex-1 rounded border border-neutral-300 px-3 py-1.5 text-sm sm:w-56 sm:flex-none dark:border-neutral-700 dark:bg-neutral-800"
                    />
                    <button type="submit" className="shrink-0 rounded bg-neutral-900 px-3 py-1.5 text-sm text-white dark:bg-white dark:text-neutral-900">
                        Search
                    </button>
                </form>

                <select
                    value={filters.category ?? ''}
                    onChange={(e) => query({ category: e.target.value || undefined, page: undefined })}
                    className="w-full rounded border border-neutral-300 px-3 py-1.5 text-sm sm:w-auto dark:border-neutral-700 dark:bg-neutral-800"
                >
                    <option value="">All categories</option>
                    {categories.map((c) => (
                        <option key={c.id} value={c.id}>{c.name_ar}</option>
                    ))}
                </select>

                <div className="sm:ms-auto">
                    <Button href="/admin/products/create" variant="primary" icon={Plus} className="w-full sm:w-auto">New product</Button>
                </div>
            </div>

            {/* Count + undo + reset/hint + export */}
            <div className="mb-3 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                <div className="flex flex-wrap items-center gap-3">
                    <span className="text-sm text-neutral-400">{products.total} products</span>
                    <UndoButton section="products" undoMeta={undoMeta} />
                    {rc.isDefault ? (
                        <span className="hidden items-center gap-1.5 text-xs text-neutral-500 lg:inline-flex">
                            <MoveHorizontal className="h-3.5 w-3.5" /> Drag column edges to resize
                        </span>
                    ) : (
                        <Button size="sm" variant="ghost" icon={Columns3} onClick={rc.resetAll}>Reset columns</Button>
                    )}
                </div>
                <ExportButtons base="/admin/products/export" params={exportParams} />
            </div>

            <StickyScrollWrapper className="rounded-lg border border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
                <table className="min-w-full table-fixed text-sm" style={{ width: rc.tableWidth }}>
                    <thead className="border-b border-neutral-200 text-left text-neutral-500 dark:border-neutral-800">
                        <tr>
                            <ResizableTh colKey="product" width={rc.widths.product} resizeProps={rc.getResizeHandleProps('product')} resizing={rc.resizing === 'product'} sortKey="name_ar" sort={filters.sort} direction={filters.direction} onSort={toggleSort}>Product</ResizableTh>
                            <ResizableTh colKey="sku" width={rc.widths.sku} resizeProps={rc.getResizeHandleProps('sku')} resizing={rc.resizing === 'sku'} sortKey="sku" sort={filters.sort} direction={filters.direction} onSort={toggleSort}>SKU</ResizableTh>
                            <ResizableTh colKey="smacc_sku" width={rc.widths.smacc_sku} resizeProps={rc.getResizeHandleProps('smacc_sku')} resizing={rc.resizing === 'smacc_sku'} sortKey="smacc_sku" sort={filters.sort} direction={filters.direction} onSort={toggleSort}>SMACC SKU</ResizableTh>
                            <ResizableTh colKey="category" width={rc.widths.category} resizeProps={rc.getResizeHandleProps('category')} resizing={rc.resizing === 'category'} sortKey="category" sort={filters.sort} direction={filters.direction} onSort={toggleSort}>Category</ResizableTh>
                            <ResizableTh colKey="price" width={rc.widths.price} resizeProps={rc.getResizeHandleProps('price')} resizing={rc.resizing === 'price'} sortKey="price" sort={filters.sort} direction={filters.direction} onSort={toggleSort}>Price</ResizableTh>
                            <ResizableTh colKey="stock" width={rc.widths.stock} resizeProps={rc.getResizeHandleProps('stock')} resizing={rc.resizing === 'stock'} sortKey="stock" sort={filters.sort} direction={filters.direction} onSort={toggleSort}>Stock</ResizableTh>
                            <ResizableTh colKey="status" width={rc.widths.status} resizeProps={rc.getResizeHandleProps('status')} resizing={rc.resizing === 'status'} sortKey="is_active" sort={filters.sort} direction={filters.direction} onSort={toggleSort}>Status</ResizableTh>
                            <ResizableTh colKey="actions" width={rc.widths.actions} resizeProps={rc.getResizeHandleProps('actions')} resizing={rc.resizing === 'actions'} className="text-end">Actions</ResizableTh>
                        </tr>
                    </thead>
                    <tbody>
                        {products.data.length === 0 && (
                            <tr>
                                <td colSpan={8} className="px-4 py-8 text-center text-neutral-400">No products.</td>
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
                                        <span dir="auto" className="truncate">{p.name_ar}</span>
                                        {p.is_featured && <span className="shrink-0 rounded bg-amber-100 px-1.5 py-0.5 text-xs text-amber-800 dark:bg-amber-950 dark:text-amber-200">Featured</span>}
                                    </div>
                                </td>
                                <td className="truncate px-4 py-3 font-mono text-neutral-500">{p.sku}</td>
                                <td className="truncate px-4 py-3 font-mono text-neutral-500">{p.smacc_sku ?? '—'}</td>
                                <td className="truncate px-4 py-3" dir="auto">{p.category ?? '—'}</td>
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
                                    {p.is_active ? (
                                        <span className="rounded-full bg-green-100 px-2 py-0.5 text-xs text-green-800 dark:bg-green-950 dark:text-green-200">Active</span>
                                    ) : (
                                        <span className="rounded-full bg-neutral-100 px-2 py-0.5 text-xs text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300">Hidden</span>
                                    )}
                                </td>
                                <td className="px-4 py-3">
                                    <div className="flex items-center justify-end gap-2">
                                        <Button size="sm" variant="secondary" icon={Pencil} href={`/admin/products/${p.id}/edit`}>Edit</Button>
                                        <Button size="sm" variant="danger" icon={Trash2} onClick={() => destroy(p)}>Delete</Button>
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
        </AdminLayout>
    );
}
