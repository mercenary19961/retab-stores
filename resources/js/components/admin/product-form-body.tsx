import { router, useForm } from '@inertiajs/react';
import { Eye, Image as ImageIcon, Info, Star, Tag, Trash2, Upload } from 'lucide-react';
import { type FormEvent, useEffect, useMemo } from 'react';
import Button from '@/components/admin/button';
import Select from '@/components/admin/select';
import { useHighlightFields } from '@/hooks/use-highlight-fields';
import { useAdminT } from '@/i18n/use-admin-t';

export interface Category {
    id: number;
    name_ar: string;
    name_en: string | null;
}

export interface ProductImage {
    id: number;
    url: string | null;
    is_primary: boolean;
}

export interface Product {
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
    is_coming_soon: boolean;
    images?: ProductImage[];
}

const INPUT = 'mt-1 w-full rounded border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800';
const CARD = 'space-y-4 rounded-lg border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-900';

/**
 * The product create/edit form body (details, pricing, visibility, images),
 * shared by the full page and the in-list modal. In `modal` mode, saves and
 * image edits preserve state and call back (close / re-fetch). Every product
 * must have at least one image: on create it's collected + sent with the form;
 * on edit the Save button is blocked while the product has no images.
 */
export default function ProductFormBody({
    product,
    categories,
    modal = false,
    onSaved,
    onImageChanged,
}: {
    product: Product | null;
    categories: Category[];
    modal?: boolean;
    onSaved?: () => void;
    onImageChanged?: () => void;
}) {
    const { t, i18n } = useAdminT();
    const editing = product !== null;
    useHighlightFields();

    // EN-first admin: show a category's English name when set, else the Arabic.
    const catLabel = (c: Category) => (i18n.language === 'en' && c.name_en ? c.name_en : c.name_ar);

    const { data, setData, post, put, processing, errors, isDirty, transform } = useForm({
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
        is_coming_soon: product?.is_coming_soon ?? false,
        images: [] as File[], // create only — the new product's images, sent with the form
    });

    // Every product needs at least one image (create: chosen files; edit: existing).
    const hasImages = editing ? (product?.images?.length ?? 0) > 0 : data.images.length > 0;

    // Object-URL previews for the create picker (revoked when the selection changes).
    const previews = useMemo(() => data.images.map((f) => URL.createObjectURL(f)), [data.images]);
    useEffect(() => () => previews.forEach((u) => URL.revokeObjectURL(u)), [previews]);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        if (!hasImages) return;
        if (editing) {
            put(`/admin/products/${product.id}`, modal ? { preserveScroll: true, preserveState: true, onSuccess: onSaved } : {});
        } else {
            // Booleans as '1'/'0' so they survive the multipart (FormData) encoding.
            transform((d) => ({ ...d, is_active: d.is_active ? '1' : '0', is_featured: d.is_featured ? '1' : '0', is_coming_soon: d.is_coming_soon ? '1' : '0' }));
            post('/admin/products', {
                forceFormData: true,
                preserveScroll: true,
                ...(modal ? { preserveState: true, onSuccess: onSaved } : {}),
            });
        }
    };

    // Existing-image edits (edit mode). In modal, keep the modal open + re-fetch.
    const imgOpts = modal ? { preserveScroll: true, preserveState: true, onSuccess: onImageChanged } : { preserveScroll: true };
    const imageForm = useForm<{ images: File[] }>({ images: [] });

    const uploadImages = (e: FormEvent) => {
        e.preventDefault();
        if (!product || imageForm.data.images.length === 0) return;
        imageForm.post(`/admin/products/${product.id}/images`, {
            forceFormData: true,
            preserveScroll: true,
            preserveState: modal,
            onSuccess: () => {
                imageForm.reset('images');
                onImageChanged?.();
            },
        });
    };

    const setPrimary = (imageId: number) => product && router.put(`/admin/products/${product.id}/images/${imageId}/primary`, {}, imgOpts);
    const deleteImage = (imageId: number) => product && router.delete(`/admin/products/${product.id}/images/${imageId}`, imgOpts);

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
                className={INPUT}
            />
            {errors[name] && <span className="text-xs text-red-500">{errors[name]}</span>}
        </label>
    );

    return (
        <div className="space-y-6">
            <form onSubmit={submit} className="space-y-6">
                <section className={CARD}>
                    <h2 className="flex items-center gap-2 font-bold"><Info className="h-4 w-4 text-brand-gold" /> {t('admin.products.form.details')}</h2>
                    <label className="block" id="field-category_id">
                        <span className="text-sm text-neutral-600 dark:text-neutral-300">{t('admin.products.form.category')} *</span>
                        <Select
                            value={String(data.category_id)}
                            onChange={(v) => setData('category_id', Number(v))}
                            options={categories.map((c) => ({ value: String(c.id), label: catLabel(c) }))}
                            className="mt-1 w-full"
                        />
                        {errors.category_id && <span className="text-xs text-red-500">{errors.category_id}</span>}
                    </label>
                    <div className="grid gap-4 sm:grid-cols-2">
                        {text('name_ar', t('admin.products.form.nameAr'), { required: true })}
                        {text('name_en', t('admin.products.form.nameEn'))}
                    </div>
                    {text('slug', t('admin.products.form.slug'), { placeholder: t('admin.products.form.slugPlaceholder') })}
                    <label className="block" id="field-description_ar">
                        <span className="text-sm text-neutral-600 dark:text-neutral-300">{t('admin.products.form.descAr')}</span>
                        <textarea value={data.description_ar} onChange={(e) => setData('description_ar', e.target.value)} rows={3} className={INPUT} />
                    </label>
                    <label className="block" id="field-description_en">
                        <span className="text-sm text-neutral-600 dark:text-neutral-300">{t('admin.products.form.descEn')}</span>
                        <textarea value={data.description_en} onChange={(e) => setData('description_en', e.target.value)} rows={3} className={INPUT} />
                    </label>
                </section>

                <section className={CARD}>
                    <h2 className="flex items-center gap-2 font-bold"><Tag className="h-4 w-4 text-brand-gold" /> {t('admin.products.form.pricingInventory')}</h2>
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
                    <h2 className="flex items-center gap-2 font-bold"><Eye className="h-4 w-4 text-brand-gold" /> {t('admin.products.form.visibility')}</h2>
                    <label className="flex items-center gap-2 text-sm" id="field-is_active">
                        <input type="checkbox" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} className="accent-brand-gold" />
                        {t('admin.products.form.activeLabel')}
                    </label>
                    <label className="flex items-center gap-2 text-sm" id="field-is_featured">
                        <input type="checkbox" checked={data.is_featured} onChange={(e) => setData('is_featured', e.target.checked)} className="accent-brand-gold" />
                        {t('admin.products.form.featuredLabel')}
                    </label>
                    <label className="flex items-start gap-2 text-sm" id="field-is_coming_soon">
                        <input type="checkbox" checked={data.is_coming_soon} onChange={(e) => setData('is_coming_soon', e.target.checked)} className="mt-0.5 accent-brand-gold" />
                        <span>
                            {t('admin.products.form.comingSoonLabel')}
                            <span className="block text-xs text-neutral-400">{t('admin.products.form.comingSoonHint')}</span>
                        </span>
                    </label>
                </section>

                {/* On create, images are chosen here and sent with the product. */}
                {!editing && (
                    <section className={CARD} id="field-images">
                        <h2 className="flex items-center gap-2 font-bold"><ImageIcon className="h-4 w-4 text-brand-gold" /> {t('admin.products.form.images')} <span className="text-red-500">*</span></h2>
                        {previews.length > 0 && (
                            <div className="grid grid-cols-3 gap-3 sm:grid-cols-4">
                                {previews.map((url, i) => (
                                    <img key={i} src={url} alt="" className={`aspect-square w-full rounded-lg border object-cover ${i === 0 ? 'border-brand-gold' : 'border-neutral-200 dark:border-neutral-700'}`} />
                                ))}
                            </div>
                        )}
                        <div className="flex flex-wrap items-center gap-3">
                            <input
                                type="file"
                                accept="image/jpeg,image/png,image/webp,image/gif"
                                multiple
                                onChange={(e) => setData('images', Array.from(e.target.files ?? []))}
                                className="text-sm"
                            />
                            <span className="text-xs text-neutral-400">{t('admin.products.form.selectImages')}</span>
                        </div>
                        {errors['images' as keyof typeof errors] && <p className="text-xs text-red-500">{errors['images' as keyof typeof errors]}</p>}
                    </section>
                )}

                <div className="space-y-2">
                    <Button type="submit" variant="primary" disabled={processing || !isDirty || !hasImages}>
                        {editing ? t('admin.products.form.saveChanges') : t('admin.products.form.createProduct')}
                    </Button>
                    {!hasImages && <p className="text-xs text-red-500">{t('admin.products.form.imageRequired')}</p>}
                </div>
            </form>

            {/* Existing product images (edit only) — managed via dedicated endpoints. */}
            {editing && (
                <section className={CARD}>
                    <h2 className="flex items-center gap-2 font-bold"><ImageIcon className="h-4 w-4 text-brand-gold" /> {t('admin.products.form.images')}</h2>

                    {product.images && product.images.length > 0 ? (
                        <div className="grid grid-cols-3 gap-3 sm:grid-cols-4">
                            {product.images.map((img) => (
                                <div key={img.id} className={`relative overflow-hidden rounded-lg border ${img.is_primary ? 'border-brand-gold' : 'border-neutral-200 dark:border-neutral-700'}`}>
                                    {img.url && <img src={img.url} alt="" className="aspect-square w-full object-cover" />}
                                    <div className="flex items-center justify-between gap-1 p-1.5">
                                        {img.is_primary ? (
                                            <span title={t('admin.products.form.primary')} className="text-brand-gold">
                                                <Star className="h-4 w-4 fill-current" />
                                            </span>
                                        ) : (
                                            <button type="button" onClick={() => setPrimary(img.id)} title={t('admin.products.form.setPrimary')} className="text-neutral-400 transition-colors hover:text-brand-gold">
                                                <Star className="h-4 w-4" />
                                            </button>
                                        )}
                                        <button type="button" onClick={() => deleteImage(img.id)} title={t('admin.common.delete')} className="text-neutral-400 transition-colors hover:text-red-500">
                                            <Trash2 className="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <p className="text-sm text-red-500">{t('admin.products.form.imageRequired')}</p>
                    )}

                    <form onSubmit={uploadImages} className="flex flex-wrap items-center gap-3 border-t border-neutral-100 pt-4 dark:border-neutral-800">
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
        </div>
    );
}
