import { useLanguage } from '@/contexts/LanguageContext';

// `object` (not Record<string, unknown>) so concrete page interfaces without an
// index signature are accepted; the cast happens once, inside the picker.
type LocalizedRow = object | null | undefined;

/**
 * Returns a picker for DB content that ships both locales (e.g. name_ar +
 * name_en). Reads the column for the active language, falling back to Arabic
 * (the required primary), then an empty string. Client-side so the language
 * toggle is instant — no server round-trip (mirrors Sky Amman's approach of
 * shipping both locales and picking on the client).
 *
 * Usage: const localized = useLocalized(); localized(product, 'name')
 */
export function useLocalized() {
    const { language } = useLanguage();

    return (row: LocalizedRow, field: string): string => {
        if (!row) return '';
        const record = row as Record<string, unknown>;
        const value = record[`${field}_${language}`] ?? record[`${field}_ar`];
        return typeof value === 'string' ? value : '';
    };
}
