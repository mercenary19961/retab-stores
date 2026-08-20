/**
 * Catalogue search: normalisation, typo tolerance and ranking.
 *
 * The catalogue is ~90 products, so the whole index is fetched once and every
 * keystroke is matched in memory. That is what makes fuzzy matching affordable
 * here at all — scoring 90 rows per key is nothing, where a server round-trip per
 * key would not be.
 *
 * ⚠️ `normalize` MIRRORS `App\Support\SearchText::normalize`. The server ships each
 * product's `terms` already normalised and matches the `?q=` results page against
 * the same string, so if these two drift the typeahead silently stops matching what
 * the results page finds. Both are pinned by the same table of cases — see
 * `search.test.ts` and `tests/Feature/SearchTest.php`; change one, change both.
 */

export interface SearchProduct {
    slug: string;
    name_ar: string;
    name_en: string | null;
    image: string | null;
    price: number;
    effective_price: number;
    on_sale: boolean;
    coming_soon: boolean;
    /** Pre-normalised haystack built server-side (names + SKU + category + synonyms). */
    terms: string;
}

/** A product with its haystack split once, so scoring never re-splits per keystroke. */
export interface IndexedProduct extends SearchProduct {
    words: string[];
}

// Escapes, not literals, and the reason is not only readability: harakat are
// COMBINING marks, so written as glyphs they visually attach to whatever character
// precedes them in the source — eslint's `no-misleading-character-class` rightly
// refuses the literal form. These ranges mirror the PHP side character for
// character (`SearchText::normalize`); read them side by side when changing either.
// \u26A0\uFE0F Kashida is split out from the harakat rather than sharing their class, and not
// for tidiness: inside one class, \u0640 followed by \u064B is a base character plus
// a combining mark \u2014 one combined glyph as far as `no-misleading-character-class` is
// concerned \u2014 and eslint refuses the class outright. Two deletions produce exactly
// the same string as PHP's single pass.
const KASHIDA = /\u0640/gu;
const HARAKAT = /[\u064B-\u0652\u0670]/gu; // harakat + superscript alef
const ALEF = /[\u0623\u0625\u0622\u0671]/gu; // hamza/madda alef + ornate alef
const ALEF_MAQSURA = /\u0649/gu;
const TA_MARBUTA = /\u0629/gu;
const WAW_HAMZA = /\u0624/gu;
const YA_HAMZA = /\u0626/gu;
const HAMZA = /\u0621/gu;
const AR_DIGITS = /[\u0660-\u0669\u06F0-\u06F9]/gu; // Arabic-Indic + Extended
const NON_WORD = /[^\p{Script=Arabic}a-z0-9]+/gu;

/**
 * Fold a string to its searchable form.
 *
 * 🔑 The letter folding is the part that matters most, and none of it is about
 * typos: أ/إ/آ, ة/ه and ى/ي are the same word spelled the way different people
 * spell it. Latin diacritics are deliberately NOT stripped, because PHP cannot do
 * it without ext-intl and doing it on one side only is precisely the drift this
 * comment warns about.
 */
export function normalize(text: string): string {
    return text
        .trim()
        .toLowerCase()
        .replace(KASHIDA, '')
        .replace(HARAKAT, '')
        .replace(ALEF, 'ا')
        .replace(ALEF_MAQSURA, 'ي')
        .replace(TA_MARBUTA, 'ه')
        .replace(WAW_HAMZA, 'و')
        .replace(YA_HAMZA, 'ي')
        .replace(HAMZA, '')
        .replace(AR_DIGITS, (d) => String(d.charCodeAt(0) & 0xf))
        .replace(NON_WORD, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}

/** Split each product's haystack once, when the index arrives. */
export function buildIndex(products: SearchProduct[]): IndexedProduct[] {
    return products.map((p) => ({ ...p, words: p.terms ? p.terms.split(' ') : [] }));
}

/**
 * How many edits a token of this length is allowed.
 *
 * ⚠️ Nothing under 4 characters gets any tolerance. At 3 characters a single edit
 * reaches a large share of the whole vocabulary, so "لوز" would start matching
 * "روز", "لون", "جوز" — the results stop looking related to what was typed, which
 * reads as a broken search rather than a forgiving one.
 */
function maxEdits(len: number): number {
    if (len < 4) return 0;
    if (len < 7) return 1;

    return 2;
}

/**
 * Damerau-Levenshtein distance, bounded: returns `limit + 1` as soon as the whole
 * row exceeds the limit, so a hopeless pair costs a fraction of the full matrix.
 * Transpositions count as one edit because adjacent-key swaps ("سرك" for "سكر")
 * are among the commonest real typos.
 */
function editDistance(a: string, b: string, limit: number): number {
    if (Math.abs(a.length - b.length) > limit) return limit + 1;

    let prev2: number[] = [];
    let prev = Array.from({ length: b.length + 1 }, (_, j) => j);
    let cur: number[] = [];

    for (let i = 1; i <= a.length; i++) {
        cur = [i];
        let best = i;
        for (let j = 1; j <= b.length; j++) {
            const cost = a[i - 1] === b[j - 1] ? 0 : 1;
            let v = Math.min(cur[j - 1] + 1, prev[j] + 1, prev[j - 1] + cost);
            if (i > 1 && j > 1 && a[i - 1] === b[j - 2] && a[i - 2] === b[j - 1]) {
                v = Math.min(v, prev2[j - 2] + 1);
            }
            cur[j] = v;
            best = Math.min(best, v);
        }
        if (best > limit) return limit + 1;
        prev2 = prev;
        prev = cur;
    }

    return prev[b.length];
}

/**
 * Best score for one query token against one product, or 0 for no match.
 *
 * The tiers are ordered by how confident each is that the shopper meant this word,
 * which is what makes the ranking read as sensible rather than arbitrary.
 */
function tokenScore(token: string, product: IndexedProduct): number {
    // Whole-haystack substring first: it is one cheap check and it also catches a
    // token that spans a word boundary the tokeniser split.
    let best = product.terms.includes(token) ? 55 : 0;

    for (const word of product.words) {
        if (word === token) return 100;
        if (best < 80 && word.startsWith(token)) best = 80;
    }
    if (best >= 55) return best;

    const limit = maxEdits(token.length);
    if (limit === 0) return 0;

    let bestDistance = limit + 1;
    for (const word of product.words) {
        // A much longer word is a different word, not a misspelling of this one.
        if (Math.abs(word.length - token.length) > limit) continue;
        const d = editDistance(token, word, limit);
        if (d < bestDistance) bestDistance = d;
        if (bestDistance === 1) break;
    }

    return bestDistance <= limit ? 34 - bestDistance * 6 : 0;
}

export interface SearchResult extends IndexedProduct {
    score: number;
}

/**
 * Would the SERVER find anything for this query?
 *
 * Mirrors `SearchText::matches` exactly — normalised substring, every token, no
 * typo tolerance. The typeahead is a strict superset of it, so this is how the
 * overlay knows whether pressing Enter would land the shopper on an empty results
 * page after they were just shown five products.
 */
export function serverWouldMatch(index: IndexedProduct[], query: string): boolean {
    const tokens = normalize(query).split(' ').filter(Boolean);
    if (tokens.length === 0) return true;

    return index.some((p) => tokens.every((t) => p.terms.includes(t)));
}

/**
 * Rewrite a query the server cannot satisfy into the closest one it can, by
 * swapping each token for the nearest word in the best fuzzy match.
 *
 * 🔑 This is what stops "see all results" being a trap. A shopper who mistypes gets
 * suggestions from the fuzzy layer, but the results page only does substrings — so
 * without a correction, clicking through would show nothing. Returns null when
 * there is nothing better to offer, and the caller then hides the link rather than
 * sending someone to an empty page.
 */
export function correctQuery(index: IndexedProduct[], query: string, best: SearchResult | undefined): string | null {
    if (!best) return null;
    const tokens = normalize(query).split(' ').filter(Boolean);
    if (tokens.length === 0) return null;

    const corrected = tokens.map((token) => {
        if (best.terms.includes(token)) return token;
        const limit = maxEdits(token.length);
        let pick = token;
        let bestDistance = limit + 1;
        for (const word of best.words) {
            const d = editDistance(token, word, limit);
            if (d < bestDistance) {
                bestDistance = d;
                pick = word;
            }
        }

        return pick;
    });

    const result = corrected.join(' ');

    return result === normalize(query) ? null : result;
}

/**
 * Rank the index against a query.
 *
 * 🔑 Tokens are ANDed: every word the shopper typed has to match something, or
 * "بوكس لوز" returns every box and every product with almonds instead of the boxes
 * of almonds. The score is the sum of per-token scores, so a product matching two
 * words exactly outranks one matching two words fuzzily.
 */
export function searchProducts(index: IndexedProduct[], query: string, limit = 8): SearchResult[] {
    const tokens = normalize(query).split(' ').filter(Boolean);
    if (tokens.length === 0) return [];

    const results: SearchResult[] = [];
    for (const product of index) {
        let score = 0;
        let matchedAll = true;
        for (const token of tokens) {
            const s = tokenScore(token, product);
            if (s === 0) {
                matchedAll = false;
                break;
            }
            score += s;
        }
        if (matchedAll) results.push({ ...product, score });
    }

    return results
        .sort(
            (a, b) =>
                b.score - a.score ||
                // A buyable product beats a coming-soon one at the same score: the
                // shopper can act on it.
                Number(a.coming_soon) - Number(b.coming_soon) ||
                // Then the shorter name, which is the more specific match — "تمر"
                // should surface «تمر سكري» above «بوكس تمر محشي مشكل فاخر».
                a.name_ar.length - b.name_ar.length,
        )
        .slice(0, limit);
}
