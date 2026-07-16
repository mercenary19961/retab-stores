import { Link, router, usePage } from '@inertiajs/react';
import {
    Boxes,
    FileText,
    History,
    Languages,
    LayoutDashboard,
    LogOut,
    Megaphone,
    Package,
    RotateCcw,
    Settings,
    ShoppingBag,
    Star,
    Users,
    type LucideIcon,
} from 'lucide-react';
import { useEffect, useState, type PropsWithChildren } from 'react';
import { I18nextProvider, useTranslation } from 'react-i18next';
import adminI18n from '@/i18n/admin';
import GlobalSearch from '@/components/admin/global-search';
import UndoToast from '@/components/admin/undo-toast';

type AdminLocale = 'en' | 'ar';
const STORAGE_KEY = 'retab_admin_locale';

const NAV: { key: string; href: string; icon: LucideIcon }[] = [
    { key: 'dashboard', href: '/admin/dashboard', icon: LayoutDashboard },
    { key: 'orders', href: '/admin/orders', icon: ShoppingBag },
    { key: 'products', href: '/admin/products', icon: Package },
    { key: 'inventory', href: '/admin/stock-import', icon: Boxes },
    { key: 'returns', href: '/admin/returns', icon: RotateCcw },
    { key: 'customers', href: '/admin/customers', icon: Users },
    { key: 'marketing', href: '/admin/marketing', icon: Megaphone },
    { key: 'reviews', href: '/admin/client-reviews', icon: Star },
    { key: 'contentPages', href: '/admin/content-pages', icon: FileText },
    { key: 'settings', href: '/admin/settings', icon: Settings },
    { key: 'changeLog', href: '/admin/change-log', icon: History },
];

function AdminShell({ children, title }: PropsWithChildren<{ title?: string }>) {
    const { t, i18n } = useTranslation();
    const page = usePage();
    const props = page.props as {
        auth?: { user?: { name?: string | null; email?: string | null } };
        flash?: { success?: string | null; error?: string | null };
    };
    const user = props.auth?.user;
    const flash = props.flash;
    const currentPath = page.url.split('?')[0];

    // Admin language: English by default, persisted in localStorage. RTL scoped
    // to this subtree via the root `dir` attribute (document.dir stays the
    // storefront's — we never touch it here).
    const [locale, setLocale] = useState<AdminLocale>('en');
    const isRTL = locale === 'ar';

    useEffect(() => {
        const saved = typeof window !== 'undefined' ? (localStorage.getItem(STORAGE_KEY) as AdminLocale | null) : null;
        if (saved === 'ar' || saved === 'en') setLocale(saved);
    }, []);

    useEffect(() => {
        i18n.changeLanguage(locale);
    }, [locale, i18n]);

    const toggleLocale = () => {
        const next: AdminLocale = locale === 'en' ? 'ar' : 'en';
        setLocale(next);
        if (typeof window !== 'undefined') localStorage.setItem(STORAGE_KEY, next);
    };

    return (
        <div dir={isRTL ? 'rtl' : 'ltr'} lang={locale} className="dark flex min-h-screen bg-neutral-950 font-sans text-neutral-100">
            {/* Sidebar */}
            <aside className="flex w-60 shrink-0 flex-col border-e border-neutral-800 bg-neutral-900">
                <Link href="/admin/dashboard" className="flex h-16 items-center border-b border-neutral-800 px-5 text-lg font-bold text-brand-gold">
                    {t('admin.brand')}
                </Link>
                <nav className="flex-1 space-y-1 overflow-y-auto p-3">
                    {NAV.map((item) => {
                        const active =
                            currentPath === item.href ||
                            (item.href !== '/admin/dashboard' && currentPath.startsWith(item.href));
                        const Icon = item.icon;
                        return (
                            <Link
                                key={item.key}
                                href={item.href}
                                className={
                                    active
                                        ? 'flex items-center gap-3 rounded-lg bg-brand-teal/25 px-3 py-2 text-sm font-semibold text-brand-gold'
                                        : 'flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-neutral-400 transition-colors hover:bg-neutral-800 hover:text-neutral-100'
                                }
                            >
                                <Icon className="h-5 w-5 shrink-0" />
                                <span>{t(`admin.nav.${item.key}`)}</span>
                            </Link>
                        );
                    })}
                </nav>
            </aside>

            {/* Main column */}
            <div className="flex min-w-0 flex-1 flex-col">
                <header className="flex h-16 shrink-0 items-center gap-4 border-b border-neutral-800 bg-neutral-900 px-6">
                    <h1 className="hidden shrink-0 truncate text-lg font-semibold lg:block">{title}</h1>
                    <div className="flex flex-1 justify-center">
                        <GlobalSearch />
                    </div>
                    <div className="flex shrink-0 items-center gap-4 text-sm">
                        <button
                            type="button"
                            onClick={toggleLocale}
                            className="flex items-center gap-1.5 rounded-lg px-2 py-1.5 text-neutral-300 transition-colors hover:bg-neutral-800"
                        >
                            <Languages className="h-4 w-4" />
                            <span>{locale === 'en' ? 'العربية' : 'English'}</span>
                        </button>
                        {user && <span className="hidden text-neutral-400 sm:inline">{user.name ?? user.email}</span>}
                        <button
                            type="button"
                            onClick={() => router.post('/logout')}
                            className="flex items-center gap-1.5 text-neutral-400 transition-colors hover:text-white"
                        >
                            <LogOut className="h-4 w-4" />
                            <span className="hidden sm:inline">{t('admin.logout')}</span>
                        </button>
                    </div>
                </header>

                <main className="flex-1 overflow-y-auto p-6">
                    {flash?.success && (
                        <div className="mb-4 rounded-lg border border-green-900 bg-green-950 px-4 py-3 text-sm text-green-200">
                            {flash.success}
                        </div>
                    )}
                    {flash?.error && (
                        <div className="mb-4 rounded-lg border border-red-900 bg-red-950 px-4 py-3 text-sm text-red-200">
                            {flash.error}
                        </div>
                    )}
                    {children}
                </main>
            </div>

            <UndoToast />
        </div>
    );
}

export default function AdminLayout(props: PropsWithChildren<{ title?: string }>) {
    return (
        <I18nextProvider i18n={adminI18n}>
            <AdminShell {...props} />
        </I18nextProvider>
    );
}
