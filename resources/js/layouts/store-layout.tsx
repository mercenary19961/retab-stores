import { useLanguage } from '@/contexts/LanguageContext';
import { useLocalized } from '@/lib/localize';
import { Link, usePage } from '@inertiajs/react';
import { type PropsWithChildren } from 'react';
import { useTranslation } from 'react-i18next';

interface Category {
    id: number;
    name_ar: string;
    name_en: string | null;
    slug: string;
}

export default function StoreLayout({ children, categories = [] }: PropsWithChildren<{ categories?: Category[] }>) {
    const { t } = useTranslation();
    const { toggleLanguage } = useLanguage();
    const localized = useLocalized();
    const props = usePage().props as { cart?: { count?: number }; auth?: { user?: unknown } };
    const cartCount = props.cart?.count ?? 0;
    const loggedIn = Boolean(props.auth?.user);

    return (
        <div className="min-h-screen bg-[#faf8f5] font-sans text-[#1f2937]">
            <header className="bg-[#2f4f4f] text-white">
                <div className="mx-auto flex max-w-6xl items-center justify-between px-4 py-4">
                    <Link href="/" className="text-2xl font-bold">{t('brand')}</Link>
                    <nav className="flex items-center gap-5 text-sm">
                        <Link href="/" className="hover:underline">{t('common.home')}</Link>
                        <Link href="/cart" className="hover:underline">
                            {t('common.cart')}{cartCount > 0 ? ` (${cartCount})` : ''}
                        </Link>
                        <Link href={loggedIn ? '/account' : '/login/whatsapp'} className="hover:underline">
                            {t('common.myAccount')}
                        </Link>
                        <button
                            type="button"
                            onClick={toggleLanguage}
                            className="rounded-full border border-white/30 px-3 py-1 transition hover:bg-white/10"
                        >
                            {t('common.switchLanguage')}
                        </button>
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
                                    {localized(c, 'name')}
                                </Link>
                            ))}
                        </div>
                    </div>
                )}
            </header>

            <main className="mx-auto max-w-6xl px-4 py-8">{children}</main>

            <footer className="mt-12 bg-[#2f4f4f] py-6 text-center text-sm text-white/80">
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
