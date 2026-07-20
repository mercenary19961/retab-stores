import { Head } from '@inertiajs/react';
import { Clock, MapPin, Navigation, Phone, Star } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { useLocalized } from '@/lib/localize';
import StoreLayout from '@/layouts/store-layout';

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

            <h1 className="mb-2 text-center font-heading font-black text-brand-teal text-[clamp(1.75rem,4vw,2.75rem)]">
                {t('branches.heading')}
            </h1>
            <p className="mb-10 text-center text-brand-teal/70">{t('branches.subtitle')}</p>

            <div className="grid gap-8 md:grid-cols-2">
                {branches.map((b) => {
                    // Keyless Google Maps embed + a directions deep link (opens navigation).
                    const embed = `https://maps.google.com/maps?q=${b.lat},${b.lng}&z=15&hl=${lang}&output=embed`;
                    const directions = `https://www.google.com/maps/dir/?api=1&destination=${b.lat},${b.lng}`;

                    return (
                        <div key={b.key} className="overflow-hidden rounded-2xl border border-brand-gold/20 bg-white shadow-sm">
                            <iframe
                                src={embed}
                                title={localized(b, 'name')}
                                loading="lazy"
                                referrerPolicy="no-referrer-when-downgrade"
                                className="h-56 w-full border-0"
                            />

                            <div className="space-y-4 p-6">
                                <div className="flex items-start justify-between gap-3">
                                    <h2 className="font-heading text-xl font-bold text-brand-teal">{localized(b, 'name')}</h2>
                                    <span className="inline-flex shrink-0 items-center gap-1 rounded-full bg-brand-cream px-2.5 py-1 text-sm font-semibold text-brand-teal">
                                        <Star className="size-4 fill-brand-gold text-brand-gold" />
                                        {b.rating.toFixed(1)}
                                        <span className="text-brand-teal/50">({b.reviews})</span>
                                    </span>
                                </div>

                                <p className="flex items-start gap-2 text-sm text-brand-teal/80">
                                    <MapPin className="mt-0.5 size-4 shrink-0 text-brand-gold" />
                                    {localized(b, 'address')}
                                </p>
                                <p className="flex items-center gap-2 text-sm text-brand-teal/80">
                                    <Clock className="size-4 shrink-0 text-brand-gold" />
                                    {localized(b, 'hours')}
                                </p>

                                <div className="flex flex-wrap gap-3 pt-1">
                                    <a
                                        href={directions}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="inline-flex items-center gap-2 rounded-full bg-brand-teal px-5 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-brand-teal/90"
                                    >
                                        <Navigation className="size-4" />
                                        {t('branches.directions')}
                                    </a>
                                    <a
                                        href={`tel:${b.phone}`}
                                        dir="ltr"
                                        aria-label={t('branches.call')}
                                        className="inline-flex items-center gap-2 rounded-full border border-brand-gold/40 px-5 py-2.5 text-sm font-semibold text-brand-teal transition-colors hover:bg-brand-gold/10"
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
