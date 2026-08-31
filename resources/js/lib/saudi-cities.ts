import { normalize } from './search';

/**
 * Saudi cities for the shipping portal's destination picker.
 *
 * 🔑 THE ENGLISH NAME IS THE VALUE, the Arabic name is only how you find it.
 * OTO's rate lookup takes a city string and matches it on their side; their own
 * documented example uses English transliterations ("ar rass", "Riyadh"), and the
 * store's configured origin (`OTO_ORIGIN_CITY`) is English too. So selecting a
 * suggestion always fills the English spelling, whichever language you typed.
 * Sending Arabic would be a guess about a matcher we cannot see.
 *
 * ⚠️ The input this feeds stays FREE TEXT on purpose. These are suggestions, not a
 * whitelist — if OTO ever wants a spelling that is not here, the admin can still
 * type it, which is what keeps a wrong entry an annoyance rather than a blocker.
 *
 * ⏭ OTO publishes a `getCities` endpoint (documented as available on every plan,
 * Free included). Wiring it would guarantee the spellings match theirs exactly.
 * It is deliberately not used here: a typeahead that waits on a network round trip
 * is worse than one that answers instantly, this list has to carry the Arabic names
 * regardless, and the page has to keep working when OTO is unreachable — which is
 * the permanent state in local dev. Reach for it if a spelling ever mismatches.
 */
export interface SaudiCity {
    /** Sent to OTO. */
    en: string;
    ar: string;
    /** Offered before anything is typed. The destinations that actually recur. */
    major?: boolean;
}

export const SAUDI_CITIES: SaudiCity[] = [
    // Riyadh region
    { en: 'Riyadh', ar: 'الرياض', major: true },
    { en: 'Al Kharj', ar: 'الخرج', major: true },
    { en: 'Ad Diriyah', ar: 'الدرعية' },
    { en: 'Al Majmaah', ar: 'المجمعة' },
    { en: 'Al Zulfi', ar: 'الزلفي' },
    { en: 'Al Duwadmi', ar: 'الدوادمي' },
    { en: 'Al Quwayiyah', ar: 'القويعية' },
    { en: 'Shaqra', ar: 'شقراء' },
    { en: 'Afif', ar: 'عفيف' },
    { en: 'Wadi Ad Dawasir', ar: 'وادي الدواسر' },
    { en: 'Al Aflaj', ar: 'الأفلاج' },
    { en: 'As Sulayyil', ar: 'السليل' },
    { en: 'Hotat Bani Tamim', ar: 'حوطة بني تميم' },
    { en: 'Huraymila', ar: 'حريملاء' },
    { en: 'Dhurma', ar: 'ضرما' },
    { en: 'Al Muzahimiyah', ar: 'المزاحمية' },
    { en: 'Thadiq', ar: 'ثادق' },
    { en: 'Rumah', ar: 'رماح' },

    // Makkah region
    { en: 'Jeddah', ar: 'جدة', major: true },
    { en: 'Makkah', ar: 'مكة المكرمة', major: true },
    { en: 'Taif', ar: 'الطائف', major: true },
    { en: 'Rabigh', ar: 'رابغ' },
    { en: 'Al Qunfudhah', ar: 'القنفذة' },
    { en: 'Al Lith', ar: 'الليث' },
    { en: 'Khulais', ar: 'خليص' },
    { en: 'Al Jumum', ar: 'الجموم' },
    { en: 'Turubah', ar: 'تربة' },
    { en: 'Ranyah', ar: 'رنية' },
    { en: 'Bahrah', ar: 'بحرة' },

    // Madinah region
    { en: 'Madinah', ar: 'المدينة المنورة', major: true },
    { en: 'Yanbu', ar: 'ينبع', major: true },
    { en: 'Al Ula', ar: 'العلا' },
    { en: 'Badr', ar: 'بدر' },
    { en: 'Khaybar', ar: 'خيبر' },
    { en: 'Al Hinakiyah', ar: 'الحناكية' },
    { en: 'Mahd Adh Dhahab', ar: 'مهد الذهب' },

    // Eastern Province
    { en: 'Dammam', ar: 'الدمام', major: true },
    { en: 'Al Khobar', ar: 'الخبر', major: true },
    { en: 'Dhahran', ar: 'الظهران', major: true },
    { en: 'Al Ahsa', ar: 'الأحساء', major: true },
    { en: 'Hofuf', ar: 'الهفوف' },
    { en: 'Jubail', ar: 'الجبيل', major: true },
    { en: 'Qatif', ar: 'القطيف' },
    { en: 'Hafar Al Batin', ar: 'حفر الباطن' },
    { en: 'Ras Tanura', ar: 'رأس تنورة' },
    { en: 'Khafji', ar: 'الخفجي' },
    { en: 'Abqaiq', ar: 'بقيق' },
    { en: 'Nairyah', ar: 'النعيرية' },
    { en: 'Safwa', ar: 'صفوى' },
    { en: 'Saihat', ar: 'سيهات' },

    // Qassim
    { en: 'Buraydah', ar: 'بريدة', major: true },
    { en: 'Unaizah', ar: 'عنيزة', major: true },
    { en: 'Ar Rass', ar: 'الرس' },
    { en: 'Al Bukayriyah', ar: 'البكيرية' },
    { en: 'Al Mithnab', ar: 'المذنب' },
    { en: 'Riyadh Al Khabra', ar: 'رياض الخبراء' },
    { en: 'Uyun Al Jiwa', ar: 'عيون الجواء' },
    { en: 'Al Badayea', ar: 'البدائع' },

    // Asir
    { en: 'Abha', ar: 'أبها', major: true },
    { en: 'Khamis Mushait', ar: 'خميس مشيط', major: true },
    { en: 'Bisha', ar: 'بيشة' },
    { en: 'Mahayel Asir', ar: 'محايل عسير' },
    { en: 'Ahad Rafidah', ar: 'أحد رفيدة' },
    { en: 'Sarat Abidah', ar: 'سراة عبيدة' },
    { en: 'Rijal Almaa', ar: 'رجال ألمع' },
    { en: 'An Namas', ar: 'النماص' },
    { en: 'Tathleeth', ar: 'تثليث' },

    // Tabuk
    { en: 'Tabuk', ar: 'تبوك', major: true },
    { en: 'Duba', ar: 'ضباء' },
    { en: 'Haql', ar: 'حقل' },
    { en: 'Umluj', ar: 'أملج' },
    { en: 'Tayma', ar: 'تيماء' },
    { en: 'Al Wajh', ar: 'الوجه' },

    // Hail
    { en: 'Hail', ar: 'حائل', major: true },
    { en: 'Baqaa', ar: 'بقعاء' },
    { en: 'Al Shinan', ar: 'الشنان' },

    // Northern Borders
    { en: 'Arar', ar: 'عرعر' },
    { en: 'Rafha', ar: 'رفحاء' },
    { en: 'Turaif', ar: 'طريف' },

    // Jazan
    { en: 'Jazan', ar: 'جازان', major: true },
    { en: 'Sabya', ar: 'صبيا' },
    { en: 'Abu Arish', ar: 'أبو عريش' },
    { en: 'Samtah', ar: 'صامطة' },
    { en: 'Ahad Al Masarihah', ar: 'أحد المسارحة' },
    { en: 'Baish', ar: 'بيش' },
    { en: 'Farasan', ar: 'فرسان' },

    // Najran
    { en: 'Najran', ar: 'نجران', major: true },
    { en: 'Sharurah', ar: 'شرورة' },
    { en: 'Habuna', ar: 'حبونا' },

    // Al Bahah
    { en: 'Al Bahah', ar: 'الباحة', major: true },
    { en: 'Baljurashi', ar: 'بلجرشي' },
    { en: 'Al Mandaq', ar: 'المندق' },
    { en: 'Qilwah', ar: 'قلوة' },

    // Al Jawf
    { en: 'Sakaka', ar: 'سكاكا', major: true },
    { en: 'Dumat Al Jandal', ar: 'دومة الجندل' },
    { en: 'Al Qurayyat', ar: 'القريات' },
    { en: 'Tabarjal', ar: 'طبرجل' },
];

/**
 * Pre-normalised haystacks, built once at module load.
 *
 * 🔑 Both names go through the SAME `normalize()` the storefront product search
 * uses, which is the whole reason the Arabic matching works: it folds أ/إ/آ onto ا,
 * ة onto ه and ى onto ي, and strips harakat and kashida. Without that, «الاحساء»
 * would not find «الأحساء» — and that is a spelling difference, not a typo, so no
 * amount of fuzzy matching would rescue it.
 *
 * It also collapses punctuation to spaces, so "Al-Khobar" finds "Al Khobar".
 */
const INDEX = SAUDI_CITIES.map((city) => ({
    city,
    en: normalize(city.en),
    ar: normalize(city.ar),
}));

/**
 * Cities matching `query`, best first. An empty query returns the major ones, so
 * focusing the field is already useful before anything is typed.
 *
 * Ranking is prefix over substring, and a hit on either language counts the same:
 * someone typing "jed" and someone typing «جد» are equally sure what they want.
 */
export function searchCities(query: string, limit = 8): SaudiCity[] {
    const q = normalize(query);

    if (!q) {
        return SAUDI_CITIES.filter((c) => c.major).slice(0, limit);
    }

    return INDEX.map(({ city, en, ar }) => {
        // Exact, then "starts with", then "contains anywhere". A word-boundary
        // prefix counts too, so "khobar" ranks "Al Khobar" as a prefix match rather
        // than burying it below unrelated substring hits.
        const score = Math.max(scoreField(en, q), scoreField(ar, q));

        return { city, score };
    })
        .filter((r) => r.score > 0)
        .sort((a, b) => b.score - a.score || a.city.en.localeCompare(b.city.en))
        .slice(0, limit)
        .map((r) => r.city);
}

function scoreField(field: string, q: string): number {
    if (field === q) return 100;
    if (field.startsWith(q)) return 80;
    if (field.split(' ').some((word) => word.startsWith(q))) return 60;
    if (field.includes(q)) return 40;

    return 0;
}

/**
 * The city whose English or Arabic name is exactly what was typed, if any.
 *
 * Lets the input hand OTO the English spelling even when the admin typed the
 * Arabic one by hand and never opened the suggestions.
 */
export function exactCity(value: string): SaudiCity | undefined {
    const v = normalize(value);

    return v ? INDEX.find(({ en, ar }) => en === v || ar === v)?.city : undefined;
}
