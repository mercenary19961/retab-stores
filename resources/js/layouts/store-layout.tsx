import { Link, usePage } from '@inertiajs/react';
import { type PropsWithChildren } from 'react';

interface Category {
    id: number;
    name_ar: string;
    slug: string;
}

export default function StoreLayout({ children, categories = [] }: PropsWithChildren<{ categories?: Category[] }>) {
    const cartCount = (usePage().props as { cart?: { count?: number } }).cart?.count ?? 0;

    return (
        <div dir="rtl" lang="ar" className="min-h-screen bg-[#faf8f5] font-sans text-[#1f2937]">
            <header className="bg-[#2f4f4f] text-white">
                <div className="mx-auto flex max-w-6xl items-center justify-between px-4 py-4">
                    <Link href="/" className="text-2xl font-bold">رطاب للتمور</Link>
                    <nav className="flex gap-5 text-sm">
                        <Link href="/" className="hover:underline">الرئيسية</Link>
                        <Link href="/cart" className="hover:underline">
                            السلة{cartCount > 0 ? ` (${cartCount})` : ''}
                        </Link>
                        <Link href="/login" className="hover:underline">حسابي</Link>
                    </nav>
                </div>

                {categories.length > 0 && (
                    <div className="border-t border-white/10">
                        <div className="mx-auto flex max-w-6xl flex-wrap gap-2 px-4 py-2 text-sm">
                            {categories.map((c) => (
                                <Link
                                    key={c.id}
                                    href={`/?category=${c.slug}`}
                                    className="rounded-full px-3 py-1 transition hover:bg-white/10"
                                >
                                    {c.name_ar}
                                </Link>
                            ))}
                        </div>
                    </div>
                )}
            </header>

            <main className="mx-auto max-w-6xl px-4 py-8">{children}</main>

            <footer className="mt-12 bg-[#2f4f4f] py-6 text-center text-sm text-white/80">
                © {new Date().getFullYear()} رطاب للتمور — جميع الحقوق محفوظة
            </footer>
        </div>
    );
}
