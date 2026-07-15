import StoreNavbar from '@/components/store/navbar';
import StoreFooter from '@/components/store/footer';
import { type PropsWithChildren } from 'react';

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
    return (
        <div className="flex min-h-screen flex-col overflow-x-clip bg-[#faf8f5] font-sans text-[#1f2937]">
            <StoreNavbar />

            {bare ? (
                <div className="flex-1">{children}</div>
            ) : (
                <main className="mx-auto w-full max-w-6xl flex-1 px-4 py-8">{children}</main>
            )}

            <StoreFooter />
        </div>
    );
}
