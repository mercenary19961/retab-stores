import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import AdminLayout from '@/layouts/admin-layout';
import ProductFormBody, { type Category, type Product } from '@/components/admin/product-form-body';
import { useAdminT } from '@/i18n/use-admin-t';

export default function ProductForm({ product, categories }: { product: Product | null; categories: Category[] }) {
    const { t } = useAdminT();
    const editing = product !== null;

    return (
        <AdminLayout>
            <Head title={editing ? t('admin.products.form.editHead', { name: product.name_ar }) : t('admin.products.form.newTitle')} />

            <Link href="/admin/products" className="inline-flex items-center gap-1 text-sm text-neutral-500 hover:underline">
                <ArrowLeft className="h-4 w-4 rtl:rotate-180" /> {t('admin.nav.products')}
            </Link>
            <h1 className="mb-6 mt-1 text-2xl font-bold">{editing ? t('admin.products.form.editTitle') : t('admin.products.form.newTitle')}</h1>

            <div className="max-w-3xl">
                <ProductFormBody product={product} categories={categories} />
            </div>
        </AdminLayout>
    );
}
