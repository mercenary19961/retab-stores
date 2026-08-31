import StoreLayout from '@/layouts/store-layout';
import { useLocalized } from '@/lib/localize';
import { Head } from '@inertiajs/react';
import { Clock, MapPin, Navigation, Phone, Star } from 'lucide-react';
import { useTranslation } from 'react-i18next';

interface Branch {
    key: string;
    name_ar: string;
    name_en: string | null;
    address_ar: string;
    address_en: string | null;
    phone: string;
    hours_ar: string;
    hours_en: string | null;
    lat: number;
    lng: number;
    rating: number;
    reviews: number;
}

export default function Branches({ branches }: { branches: Branch[] }) {
    const { t, i18n } = useTranslation();
    const localized = useLocalized();
    const lang = i18n.language === 'en' ? 'en' : 'ar';

    return (
        <StoreLayout>
            <Head title={t('branches.headTitle')}>
                <meta name="description" content={t('branches.metaDescription')} />
            </Head>

            <h1 className="font-heading text-brand-teal mb-2 text-center text-[clamp(1.75rem,4vw,2.75rem)] font-black">{t('branches.heading')}</h1>
            <p className="text-brand-teal/70 mb-10 text-center">{t('branches.subtitle')}</p>

            {/* One shop centres at a readable width; the two-column grid is kept
                for the day a second one is added back. */}
            <div className={`grid gap-8 ${branches.length > 1 ? 'md:grid-cols-2' : 'mx-auto max-w-2xl'}`}>
                {branches.map((b) => {
                    // Keyless Google Maps embed + a directions deep link (opens navigation).
                    const embed = `https://maps.google.com/maps?q=${b.lat},${b.lng}&z=15&hl=${lang}&output=embed`;
                    const directions = `https://www.google.com/maps/dir/?api=1&destination=${b.lat},${b.lng}`;

                    return (
                        <div key={b.key} className="border-brand-gold/20 overflow-hidden rounded-2xl border bg-white shadow-sm">
                            <iframe
                                src={embed}
                                title={localized(b, 'name')}
                                loading="lazy"
                                referrerPolicy="no-referrer-when-downgrade"
                                className="h-56 w-full border-0"
                            />

                            <div className="space-y-4 p-6">
                                <div className="flex items-start justify-between gap-3">
                                    <h2 className="font-heading text-brand-teal text-xl font-bold">{localized(b, 'name')}</h2>
                                    <span className="bg-brand-cream text-brand-teal inline-flex shrink-0 items-center gap-1 rounded-full px-2.5 py-1 text-sm font-semibold">
                                        <Star className="fill-brand-gold text-brand-gold size-4" />
                                        {b.rating.toFixed(1)}
                                        <span className="text-brand-teal/50">({b.reviews})</span>
                                    </span>
                                </div>

                                <p className="text-brand-teal/80 flex items-start gap-2 text-sm">
                                    <MapPin className="text-brand-gold mt-0.5 size-4 shrink-0" />
                                    {localized(b, 'address')}
                                </p>
                                <p className="text-brand-teal/80 flex items-center gap-2 text-sm">
                                    <Clock className="text-brand-gold size-4 shrink-0" />
                                    {localized(b, 'hours')}
                                </p>

                                <div className="flex flex-wrap gap-3 pt-1">
                                    <a
                                        href={directions}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="bg-brand-teal hover:bg-brand-teal/90 inline-flex items-center gap-2 rounded-full px-5 py-2.5 text-sm font-semibold text-white transition-colors"
                                    >
                                        <Navigation className="size-4" />
                                        {t('branches.directions')}
                                    </a>
                                    <a
                                        href={`tel:${b.phone}`}
                                        dir="ltr"
                                        aria-label={t('branches.call')}
                                        className="border-brand-gold/40 text-brand-teal hover:bg-brand-gold/10 inline-flex items-center gap-2 rounded-full border px-5 py-2.5 text-sm font-semibold transition-colors"
                                    >
                                        <Phone className="size-4" />
                                        {b.phone.replace('+966', '0')}
                                    </a>
                                </div>
                            </div>
                        </div>
                    );
                })}
            </div>
        </StoreLayout>
    );
}
