import StoreNavbar from '@/components/store/navbar';
import { Link } from '@inertiajs/react';
import { type PropsWithChildren } from 'react';
import { useTranslation } from 'react-i18next';

interface Category {
    id: number;
    name_ar: string;
    name_en: string | null;
    slug: string;
}

// `categories` is accepted for backward-compat with pages that still pass it;
// the navbar now sources its category tree from shared props (navCategories).
// `bare` skips the constrained <main> so a page can render full-bleed sections
// (the homepage hero/banner) and manage its own inner containers.
export default function StoreLayout({
    children,
    bare = false,
}: PropsWithChildren<{ categories?: Category[]; bare?: boolean }>) {
    const { t } = useTranslation();

    return (
        <div className="flex min-h-screen flex-col overflow-x-hidden bg-[#faf8f5] font-sans text-[#1f2937]">
            <StoreNavbar />

            {bare ? (
                <div className="flex-1">{children}</div>
            ) : (
                <main className="mx-auto w-full max-w-6xl flex-1 px-4 py-8">{children}</main>
            )}

            <footer className="mt-12 bg-brand-teal py-6 text-center text-sm text-white/80">
                <nav className="mb-2 flex justify-center gap-4">
                    <Link href="/pages/returns-policy" className="hover:underline">{t('footer.returnsPolicy')}</Link>
                    <Link href="/pages/about" className="hover:underline">{t('footer.about')}</Link>
                    <Link href="/pages/contact" className="hover:underline">{t('footer.contact')}</Link>
                </nav>
                © {new Date().getFullYear()} {t('brand')} — {t('common.rightsReserved')}
            </footer>
        </div>
    );
}
