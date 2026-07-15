import { Link, router, usePage } from '@inertiajs/react';
import { type PropsWithChildren } from 'react';

interface NavItem {
    label: string;
    href: string;
}

const NAV: NavItem[] = [
    { label: 'Orders', href: '/admin/orders' },
    { label: 'Products', href: '/admin/products' },
    { label: 'Inventory', href: '/admin/stock-import' },
    { label: 'Reviews', href: '/admin/client-reviews' },
    { label: 'Change Log', href: '/admin/change-log' },
];

export default function AdminLayout({ children, title }: PropsWithChildren<{ title?: string }>) {
    const page = usePage();
    const props = page.props as {
        auth?: { user?: { name?: string | null; email?: string | null } };
        flash?: { success?: string | null; error?: string | null };
    };
    const user = props.auth?.user;
    const flash = props.flash;
    const currentPath = page.url.split('?')[0];

    return (
        <div dir="ltr" lang="en" className="min-h-screen bg-neutral-50 text-neutral-900 dark:bg-neutral-950 dark:text-neutral-100">
            <header className="border-b border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
                <div className="mx-auto flex max-w-7xl items-center justify-between px-4 py-3">
                    <div className="flex items-center gap-8">
                        <Link href="/admin/orders" className="text-lg font-bold">
                            Retab Admin
                        </Link>
                        <nav className="flex gap-4 text-sm">
                            {NAV.map((item) => {
                                const active = currentPath.startsWith(item.href);
                                return (
                                    <Link
                                        key={item.href}
                                        href={item.href}
                                        className={
                                            active
                                                ? 'font-semibold text-neutral-900 dark:text-white'
                                                : 'text-neutral-500 hover:text-neutral-900 dark:hover:text-white'
                                        }
                                    >
                                        {item.label}
                                    </Link>
                                );
                            })}
                        </nav>
                    </div>
                    <div className="flex items-center gap-4 text-sm">
                        {user && <span className="text-neutral-500">{user.name ?? user.email}</span>}
                        <button
                            type="button"
                            onClick={() => router.post('/logout')}
                            className="text-neutral-500 hover:text-neutral-900 dark:hover:text-white"
                        >
                            Log out
                        </button>
                    </div>
                </div>
            </header>

            <main className="mx-auto max-w-7xl px-4 py-6">
                {flash?.success && (
                    <div className="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200">
                        {flash.success}
                    </div>
                )}
                {flash?.error && (
                    <div className="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
                        {flash.error}
                    </div>
                )}
                {title && <h1 className="mb-6 text-2xl font-bold">{title}</h1>}
                {children}
            </main>
        </div>
    );
}
