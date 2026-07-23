import { useLanguage } from '@/contexts/LanguageContext';
import { Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

interface AuthLayoutProps {
    children: React.ReactNode;
    name?: string;
    title?: string;
    description?: string;
}

export default function AuthSimpleLayout({ children, title, description }: AuthLayoutProps) {
    const { t } = useTranslation();
    const { toggleLanguage } = useLanguage();

    return (
        <div className="relative flex min-h-svh flex-col items-center justify-center bg-brand-cream px-6 py-12">
            {/* Language toggle — logical `end` corner so it flips sides in RTL/LTR. */}
            <button
                type="button"
                onClick={toggleLanguage}
                className="absolute end-6 top-6 rounded-full border border-brand-gold/40 bg-white/70 px-3 py-1 text-sm text-brand-gold transition-colors hover:bg-brand-gold/10"
            >
                {t('common.switchLanguage')}
            </button>

            <div className="w-full max-w-sm">
                <div className="rounded-2xl border border-brand-gold/15 bg-white px-8 py-10 shadow-sm">
                    <div className="flex flex-col items-center gap-5">
                        <Link href={route('home')} aria-label={t('brand')}>
                            <img src="/images/brand/logo.png" alt={t('brand')} className="h-16 w-auto" />
                        </Link>

                        {(title || description) && (
                            <div className="space-y-1.5 text-center">
                                {title && <h1 className="text-xl font-bold text-brand-teal">{title}</h1>}
                                {description && <p className="text-sm text-neutral-500">{description}</p>}
                            </div>
                        )}
                    </div>

                    <div className="mt-8">{children}</div>
                </div>
            </div>
        </div>
    );
}
