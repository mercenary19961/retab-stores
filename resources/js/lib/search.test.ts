import { describe, expect, it } from 'vitest';

import { buildIndex, correctQuery, normalize, searchProducts, serverWouldMatch, type SearchProduct } from './search';

/**
 * 🔑 THE TABLE BELOW IS DUPLICATED IN `tests/Feature/SearchTest.php`, deliberately
 * and identically. `normalize` exists on both sides — PHP builds each product's
 * `terms` and matches the `?q=` results page, JS normalises the query — and if the
 * two ever disagree the typeahead silently stops matching what the results page
 * finds, with nothing failing anywhere. Change a case here, change it there.
 */
export const NORMALIZE_CASES: [string, string][] = [
    // Alef with hamza/madda folds to bare alef: the commonest spelling variance.
    ['أشيقر', 'اشيقر'],
    ['إسم', 'اسم'],
    ['آية', 'ايه'],
    // Ta marbuta → ha, so «علبة» and «علبه» are one word.
    ['علبة', 'علبه'],
    // Alef maqsura → ya, so «سكرى» finds «سكري».
    ['سكرى', 'سكري'],
    // Harakat and kashida are decoration. The kashida matters here specifically:
    // the storefront's own designed copy uses it.
    ['سُكَّري', 'سكري'],
    ['تمــــور', 'تمور'],
    // Hamza carriers.
    ['مؤمن', 'مومن'],
    ['رئيس', 'رييس'],
    ['ماء', 'ما'],
    // Arabic-Indic digits, both ranges.
    ['٢٥٠ جرام', '250 جرام'],
    ['۲۵۰', '250'],
    // Latin is lowercased; punctuation and symbols become word boundaries.
    ['Khalas  Ushaiger', 'khalas ushaiger'],
    ['250g - Carton', '250g carton'],
    ['  spaced   out  ', 'spaced out'],
    ['RTB-0004', 'rtb 0004'],
    // Mixed scripts survive together.
    ['خلاص Khalas 250g', 'خلاص khalas 250g'],
    ['', ''],
];

const product = (over: Partial<SearchProduct> & { terms: string; name_ar: string }): SearchProduct => ({
    slug: over.name_ar,
    name_en: null,
    image: null,
    price: 10,
    effective_price: 10,
    on_sale: false,
    coming_soon: false,
    ...over,
});

// A miniature catalogue shaped like the real one: `terms` is what the server
// builds, already normalised, including synonym expansions.
const INDEX = buildIndex([
    product({ name_ar: 'خلاص أشيقر درجة أولى 250 جرام', terms: 'خلاص اشيقر درجه اولي 250 جرام khalas ushaiger grade 1 250g جم g gram grams' }),
    product({ name_ar: 'بوكس سكري محشي لوز', terms: 'بوكس سكري محشي لوز sukkari box stuffed almonds علبه صندوق محشو almond' }),
    product({ name_ar: 'قهوة نجدية', terms: 'قهوه نجديه najdi coffee' }),
    product({ name_ar: 'تمر عجوة', terms: 'تمر عجوه ajwa dates تمور date', coming_soon: true }),
]);

describe('normalize', () => {
    it.each(NORMALIZE_CASES)('folds %j to %j', (input, expected) => {
        expect(normalize(input)).toBe(expected);
    });

    it('is idempotent', () => {
        for (const [input] of NORMALIZE_CASES) {
            expect(normalize(normalize(input))).toBe(normalize(input));
        }
    });
});

describe('searchProducts', () => {
    it('finds a product spelled with a different alef or ya', () => {
        // Neither of these is a typo — they are how people actually spell it.
        expect(searchProducts(INDEX, 'اشيقر').map((r) => r.name_ar)).toEqual(['خلاص أشيقر درجة أولى 250 جرام']);
        expect(searchProducts(INDEX, 'سكرى').map((r) => r.name_ar)).toEqual(['بوكس سكري محشي لوز']);
    });

    it('crosses languages through the indexed English name', () => {
        expect(searchProducts(INDEX, 'coffee').map((r) => r.name_ar)).toEqual(['قهوة نجدية']);
        expect(searchProducts(INDEX, 'قهوة').map((r) => r.name_ar)).toEqual(['قهوة نجدية']);
    });

    it('crosses languages through a synonym the product text never contains', () => {
        // The product says «بوكس»; the shopper typed «علبة».
        expect(searchProducts(INDEX, 'علبة').map((r) => r.name_ar)).toEqual(['بوكس سكري محشي لوز']);
    });

    it('tolerates a typo', () => {
        expect(searchProducts(INDEX, 'sukari').map((r) => r.name_ar)).toEqual(['بوكس سكري محشي لوز']);
        expect(searchProducts(INDEX, 'almnods').map((r) => r.name_ar)).toEqual(['بوكس سكري محشي لوز']);
    });

    it('tolerates a transposition', () => {
        expect(searchProducts(INDEX, 'coffe').map((r) => r.name_ar)).toEqual(['قهوة نجدية']);
        expect(searchProducts(INDEX, 'cofefe').map((r) => r.name_ar)).toEqual(['قهوة نجدية']);
    });

    it('refuses to fuzz a very short token', () => {
        // 🔑 With one edit allowed on three characters, «لوز» would start matching
        // «جوز», «لون», «روز» — the results stop resembling the query.
        expect(searchProducts(INDEX, 'جوز')).toEqual([]);
        expect(searchProducts(INDEX, 'لوز').map((r) => r.name_ar)).toEqual(['بوكس سكري محشي لوز']);
    });

    it('ANDs the tokens rather than ORing them', () => {
        // Both words: only the box of almonds. ORing would also return every
        // product that is merely a box.
        expect(searchProducts(INDEX, 'بوكس لوز').map((r) => r.name_ar)).toEqual(['بوكس سكري محشي لوز']);
        expect(searchProducts(INDEX, 'بوكس قهوة')).toEqual([]);
    });

    it('ranks an exact word above a fuzzy one', () => {
        const results = searchProducts(INDEX, 'قهوة');
        expect(results[0].name_ar).toBe('قهوة نجدية');
    });

    it('ranks a buyable product above a coming-soon one at the same score', () => {
        const soon = buildIndex([
            product({ name_ar: 'قريباً', terms: 'تمر dates', coming_soon: true }),
            product({ name_ar: 'متاح', terms: 'تمر dates' }),
        ]);
        expect(searchProducts(soon, 'تمر').map((r) => r.name_ar)).toEqual(['متاح', 'قريباً']);
    });

    it('returns nothing for an empty query', () => {
        expect(searchProducts(INDEX, '   ')).toEqual([]);
    });

    it('honours the limit', () => {
        expect(searchProducts(INDEX, 'ا', 1).length).toBeLessThanOrEqual(1);
    });
});

describe('serverWouldMatch / correctQuery', () => {
    it('agrees with the server on a plain substring query', () => {
        expect(serverWouldMatch(INDEX, 'سكري')).toBe(true);
        // Normalisation is the server's too, so a folded spelling still agrees.
        expect(serverWouldMatch(INDEX, 'سكرى')).toBe(true);
    });

    it('reports that the server cannot satisfy a typo', () => {
        expect(serverWouldMatch(INDEX, 'sukari')).toBe(false);
    });

    it('rewrites a typo into something the results page can find', () => {
        const best = searchProducts(INDEX, 'sukari')[0];
        const corrected = correctQuery(INDEX, 'sukari', best);
        expect(corrected).toBe('sukkari');
        // 🔑 The point of the correction: the link must not lead to an empty page.
        expect(serverWouldMatch(INDEX, corrected!)).toBe(true);
    });

    it('offers no correction when there is nothing to correct to', () => {
        expect(correctQuery(INDEX, 'zzzzzz', undefined)).toBeNull();
    });
});
