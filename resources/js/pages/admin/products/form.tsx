import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Upload } from 'lucide-react';
import { type FormEvent } from 'react';
import AdminLayout from '@/layouts/admin-layout';
import Button from '@/components/admin/button';
import { useHighlightFields } from '@/hooks/use-highlight-fields';
import { useAdminT } from '@/i18n/use-admin-t';

interface Category {
    id: number;
    name_ar: string;
}

interface ProductImage {
    id: number;
    url: string | null;
    is_primary: boolean;
}

interface Product {
    id: number;
    category_id: number;
    name_ar: string;
    name_en: string | null;
    slug: string | null;
    description_ar: string | null;
    description_en: string | null;
    price: number;
    sale_price: number | null;
    sku: string;
    smacc_sku: string | null;
    barcode: string | null;
    stock: number;
    low_stock_threshold: number | null;
    is_active: boolean;
    is_featured: boolean;
    images?: ProductImage[];
}

export default function ProductForm({ product, categories }: { product: Product | null; categories: Category[] }) {
    const { t } = useAdminT();
    const editing = product !== null;
    useHighlightFields();

    const { data, setData, post, put, processing, errors } = useForm({
        category_id: product?.category_id ?? categories[0]?.id ?? '',
        name_ar: product?.name_ar ?? '',
        name_en: product?.name_en ?? '',
        slug: product?.slug ?? '',
        description_ar: product?.description_ar ?? '',
        description_en: product?.description_en ?? '',
        price: product?.price ?? '',
        sale_price: product?.sale_price ?? '',
        sku: product?.sku ?? '',
        smacc_sku: product?.smacc_sku ?? '',
        barcode: product?.barcode ?? '',
        stock: product?.stock ?? 0,
        low_stock_threshold: product?.low_stock_threshold ?? '',
        is_active: product?.is_active ?? true,
        is_featured: product?.is_featured ?? false,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        if (editing) {
            put(`/admin/products/${product.id}`);
        } else {
            post('/admin/products');
        }
    };

    const imageForm = useForm<{ images: File[] }>({ images: [] });

    const uploadImages = (e: FormEvent) => {
        e.preventDefault();
        if (!product || imageForm.data.images.length === 0) return;
        imageForm.post(`/admin/products/${product.id}/images`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => imageForm.reset('images'),
        });
    };

    const text = (name: keyof typeof data, label: string, opts: { required?: boolean; type?: string; placeholder?: string } = {}) => (
        <label className="block" id={`field-${name}`}>
            <span className="text-sm text-neutral-600 dark:text-neutral-300">
                {label}
                {opts.required && <span className="text-red-500"> *</span>}
            </span>
            <input
                type={opts.type ?? 'text'}
                value={data[name] as string | number}
                placeholder={opts.placeholder}
                onChange={(e) => setData(name, e.target.value)}
                className="mt-1 w-full rounded border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
            />
            {errors[name] && <span className="text-xs text-red-500">{errors[name]}</span>}
        </label>
    );

    return (
        <AdminLayout>
            <Head title={editing ? t('admin.products.form.editHead', { name: product.name_ar }) : t('admin.products.form.newTitle')} />

            <Link href="/admin/products" className="inline-flex items-center gap-1 text-sm text-neutral-500 hover:underline">
                <ArrowLeft className="h-4 w-4 rtl:rotate-180" /> {t('admin.nav.products')}
            </Link>
            <h1 className="mb-6 mt-1 text-2xl font-bold">{editing ? t('admin.products.form.editTitle') : t('admin.products.form.newTitle')}</h1>

            <form onSubmit={submit} className="max-w-3xl space-y-6">
                <section className="space-y-4 rounded-lg border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-900">
                    <h2 className="font-bold">{t('admin.products.form.details')}</h2>
                    <label className="block" id="field-category_id">
                        <span className="text-sm text-neutral-600 dark:text-neutral-300">{t('admin.products.form.category')} *</span>
                        <select
                            value={data.category_id}
                            onChange={(e) => setData('category_id', Number(e.target.value))}
                            className="mt-1 w-full rounded border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                        >
                            {categories.map((c) => (
                                <option key={c.id} value={c.id}>{c.name_ar}</option>
                            ))}
                        </select>
                        {errors.category_id && <span className="text-xs text-red-500">{errors.category_id}</span>}
                    </label>
                    <div className="grid gap-4 sm:grid-cols-2">
                        {text('name_ar', t('admin.products.form.nameAr'), { required: true })}
                        {text('name_en', t('admin.products.form.nameEn'))}
                    </div>
                    {text('slug', t('admin.products.form.slug'), { placeholder: t('admin.products.form.slugPlaceholder') })}
                    <label className="block" id="field-description_ar">
                        <span className="text-sm text-neutral-600 dark:text-neutral-300">{t('admin.products.form.descAr')}</span>
                        <textarea
                            value={data.description_ar}
                            onChange={(e) => setData('description_ar', e.target.value)}
                            rows={3}
                            className="mt-1 w-full rounded border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                        />
                    </label>
                    <label className="block" id="field-description_en">
                        <span className="text-sm text-neutral-600 dark:text-neutral-300">{t('admin.products.form.descEn')}</span>
                        <textarea
                            value={data.description_en}
                            onChange={(e) => setData('description_en', e.target.value)}
                            rows={3}
                            className="mt-1 w-full rounded border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                        />
                    </label>
                </section>

                <section className="space-y-4 rounded-lg border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-900">
                    <h2 className="font-bold">{t('admin.products.form.pricingInventory')}</h2>
                    <div className="grid gap-4 sm:grid-cols-2">
                        {text('price', t('admin.products.form.priceSar'), { required: true, type: 'number' })}
                        {text('sale_price', t('admin.products.form.salePriceSar'), { type: 'number' })}
                        {text('sku', t('admin.products.form.sku'), { required: true })}
                        {text('smacc_sku', t('admin.products.form.smaccSku'))}
                        {text('barcode', t('admin.products.form.barcode'))}
                        {text('stock', t('admin.products.form.stock'), { required: true, type: 'number' })}
                        {text('low_stock_threshold', t('admin.products.form.lowStock'), { type: 'number' })}
                    </div>
                </section>

                <section className="space-y-3 rounded-lg border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-900">
                    <h2 className="font-bold">{t('admin.products.form.visibility')}</h2>
                    <label className="flex items-center gap-2 text-sm" id="field-is_active">
                        <input type="checkbox" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} />
                        {t('admin.products.form.activeLabel')}
                    </label>
                    <label className="flex items-center gap-2 text-sm" id="field-is_featured">
                        <input type="checkbox" checked={data.is_featured} onChange={(e) => setData('is_featured', e.target.checked)} />
                        {t('admin.products.form.featuredLabel')}
                    </label>
                </section>

                <div className="flex gap-3">
                    <Button type="submit" variant="primary" disabled={processing}>
                        {editing ? t('admin.products.form.saveChanges') : t('admin.products.form.createProduct')}
                    </Button>
                    <Button href="/admin/products" variant="secondary">{t('admin.common.cancel')}</Button>
                </div>
            </form>

            {/* Images — only after the product exists (kept outside the text form). */}
            {editing && (
                <section className="mt-6 max-w-3xl space-y-4 rounded-lg border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-900">
                    <h2 className="font-bold">{t('admin.products.form.images')}</h2>

                    {product.images && product.images.length > 0 ? (
                        <div className="grid grid-cols-3 gap-3 sm:grid-cols-4">
                            {product.images.map((img) => (
                                <div key={img.id} className={`relative overflow-hidden rounded-lg border ${img.is_primary ? 'border-neutral-900 dark:border-white' : 'border-neutral-200 dark:border-neutral-700'}`}>
                                    {img.url && <img src={img.url} alt="" className="aspect-square w-full object-cover" />}
                                    <div className="flex items-center justify-between gap-1 p-1 text-xs">
                                        {img.is_primary ? (
                                            <span className="font-semibold text-neutral-900 dark:text-white">{t('admin.products.form.primary')}</span>
                                        ) : (
                                            <button
                                                type="button"
                                                onClick={() => router.put(`/admin/products/${product.id}/images/${img.id}/primary`, {}, { preserveScroll: true })}
                                                className="font-medium text-blue-600 hover:underline dark:text-blue-400"
                                            >
                                                {t('admin.products.form.setPrimary')}
                                            </button>
                                        )}
                                        <button
                                            type="button"
                                            onClick={() => router.delete(`/admin/products/${product.id}/images/${img.id}`, { preserveScroll: true })}
                                            className="font-medium text-red-600 hover:underline dark:text-red-400"
                                        >
                                            {t('admin.common.delete')}
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <p className="text-sm text-neutral-400">{t('admin.products.form.noImages')}</p>
                    )}

                    <form onSubmit={uploadImages} className="flex items-center gap-3 border-t border-neutral-100 pt-4 dark:border-neutral-800">
                        <input
                            type="file"
                            accept="image/jpeg,image/png,image/webp,image/gif"
                            multiple
                            onChange={(e) => imageForm.setData('images', Array.from(e.target.files ?? []))}
                            className="text-sm"
                        />
                        <Button type="submit" variant="primary" icon={Upload} disabled={imageForm.processing || imageForm.data.images.length === 0}>
                            {t('admin.common.upload')}
                        </Button>
                    </form>
                    {imageForm.errors.images && <p className="text-xs text-red-500">{imageForm.errors.images}</p>}
                </section>
            )}
        </AdminLayout>
    );
}
