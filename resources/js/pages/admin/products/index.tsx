import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '@/layouts/admin-layout';

interface ProductRow {
    id: number;
    name_ar: string;
    image: string | null;
    sku: string;
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

interface Paginator<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
}

export default function ProductsIndex({
    products,
    filters,
    categories,
}: {
    products: Paginator<ProductRow>;
    filters: { search: string | null; category: number | null };
    categories: Category[];
}) {
    const [search, setSearch] = useState(filters.search ?? '');

    const query = (next: Record<string, unknown>) => {
        router.get(
            '/admin/products',
            { search: search || undefined, category: filters.category || undefined, ...next },
            { preserveState: true, preserveScroll: true },
        );
    };

    const destroy = (p: ProductRow) => {
        if (!window.confirm(`Delete "${p.name_ar}"? It will be hidden but order history is kept.`)) return;
        router.delete(`/admin/products/${p.id}`, { preserveScroll: true });
    };

    return (
        <AdminLayout title="Products">
            <Head title="Products" />

            <div className="mb-4 flex flex-wrap items-center gap-3">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        query({});
                    }}
                    className="flex gap-2"
                >
                    <input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search name or SKU…"
                        className="rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                    />
                    <button type="submit" className="rounded bg-neutral-900 px-3 py-1.5 text-sm text-white dark:bg-white dark:text-neutral-900">
                        Search
                    </button>
                </form>

                <select
                    value={filters.category ?? ''}
                    onChange={(e) => query({ category: e.target.value || undefined })}
                    className="rounded border border-neutral-300 px-3 py-1.5 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                >
                    <option value="">All categories</option>
                    {categories.map((c) => (
                        <option key={c.id} value={c.id}>{c.name_ar}</option>
                    ))}
                </select>

                <Link
                    href="/admin/products/create"
                    className="ml-auto rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700"
                >
                    + New product
                </Link>
            </div>

            <div className="overflow-hidden rounded-lg border border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
                <table className="w-full text-sm">
                    <thead className="border-b border-neutral-200 text-left text-neutral-500 dark:border-neutral-800">
                        <tr>
                            <th className="px-4 py-3 font-medium">Product</th>
                            <th className="px-4 py-3 font-medium">SKU</th>
                            <th className="px-4 py-3 font-medium">Category</th>
                            <th className="px-4 py-3 font-medium">Price</th>
                            <th className="px-4 py-3 font-medium">Stock</th>
                            <th className="px-4 py-3 font-medium">Status</th>
                            <th className="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        {products.data.length === 0 && (
                            <tr>
                                <td colSpan={7} className="px-4 py-8 text-center text-neutral-400">No products.</td>
                            </tr>
                        )}
                        {products.data.map((p) => (
                            <tr key={p.id} className="border-b border-neutral-100 last:border-0 dark:border-neutral-800">
                                <td className="px-4 py-3">
                                    <div className="flex items-center gap-2">
                                        {p.image ? (
                                            <img src={p.image} alt="" className="h-9 w-9 rounded object-cover" />
                                        ) : (
                                            <div className="flex h-9 w-9 items-center justify-center rounded bg-neutral-100 text-sm dark:bg-neutral-800">🌴</div>
                                        )}
                                        <span>{p.name_ar}</span>
                                        {p.is_featured && <span className="rounded bg-amber-100 px-1.5 py-0.5 text-xs text-amber-800 dark:bg-amber-950 dark:text-amber-200">Featured</span>}
                                    </div>
                                </td>
                                <td className="px-4 py-3 font-mono text-neutral-500">{p.sku}</td>
                                <td className="px-4 py-3">{p.category ?? '—'}</td>
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
                                <td className="px-4 py-3 text-right">
                                    <Link href={`/admin/products/${p.id}/edit`} className="text-blue-600 hover:underline dark:text-blue-400">Edit</Link>
                                    <button type="button" onClick={() => destroy(p)} className="ms-3 text-red-600 hover:underline dark:text-red-400">Delete</button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

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
