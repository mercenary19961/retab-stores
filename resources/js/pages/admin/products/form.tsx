import ProductFormBody, { type Category, type Product } from '@/components/admin/product-form-body';
import { useAdminT } from '@/i18n/use-admin-t';
import AdminLayout from '@/layouts/admin-layout';
import { Head } from '@inertiajs/react';

export default function ProductForm({ product, categories }: { product: Product | null; categories: Category[] }) {
    const { t } = useAdminT();
    const editing = product !== null;

    return (
        <AdminLayout>
            <Head title={editing ? t('admin.products.form.editHead', { name: product.name_ar }) : t('admin.products.form.newTitle')} />

            {/* The header (back link, title, Save) is rendered by the form body, which
                owns the state the Save button is gated on. */}
            <div className="max-w-6xl">
                <ProductFormBody
                    product={product}
                    categories={categories}
                    title={editing ? t('admin.products.form.editTitle') : t('admin.products.form.newTitle')}
                    back="/admin/products"
                    backLabel={t('admin.nav.products')}
                />
            </div>
        </AdminLayout>
    );
}
