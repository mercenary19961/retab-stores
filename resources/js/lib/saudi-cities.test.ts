import { describe, expect, it } from 'vitest';

import { exactCity, SAUDI_CITIES, searchCities } from './saudi-cities';

const names = (query: string) => searchCities(query).map((c) => c.en);

describe('searchCities', () => {
    it('offers the major cities before anything is typed', () => {
        const suggested = searchCities('');

        expect(suggested.length).toBeGreaterThan(0);
        expect(suggested.every((c) => c.major)).toBe(true);
        expect(suggested.map((c) => c.en)).toContain('Riyadh');
    });

    it('matches English, case-insensitively', () => {
        expect(names('jed')).toContain('Jeddah');
        expect(names('JEDDAH')).toContain('Jeddah');
    });

    it('matches Arabic', () => {
        expect(names('جدة')).toContain('Jeddah');
        expect(names('الرياض')).toContain('Riyadh');
        expect(names('خميس')).toContain('Khamis Mushait');
    });

    /**
     * 🔑 The reason this reuses the storefront search's normalizer. None of these
     * are typos: they are the same city written the way different people write it,
     * so no amount of fuzzy matching would rescue them. Letter folding does.
     */
    it('folds Arabic letter variants, so a different spelling still finds the city', () => {
        // أ / ا  — hamza on the alef, or not.
        expect(names('الاحساء')).toContain('Al Ahsa');
        expect(names('الأحساء')).toContain('Al Ahsa');
        // ة / ه  — ta marbuta written as ha.
        expect(names('جده')).toContain('Jeddah');
        // Harakat.
        expect(names('الرِياض')).toContain('Riyadh');
        // Arabic-Indic digits and punctuation are folded by the same normalizer,
        // which is what makes the hyphen case below work.
        expect(names('Al-Khobar')).toContain('Al Khobar');
    });

    it('ranks a prefix above a mid-word substring', () => {
        const results = names('ta');

        // "Taif" and "Tabuk" start with it; "Ras Tanura" only contains it.
        expect(results.indexOf('Taif')).toBeLessThan(results.indexOf('Ras Tanura'));
    });

    it('matches a word inside a multi-word name', () => {
        expect(names('khobar')).toContain('Al Khobar');
        expect(names('mushait')).toContain('Khamis Mushait');
    });

    it('returns nothing for a city that is not Saudi', () => {
        expect(names('Dubai')).toHaveLength(0);
        expect(names('القاهرة')).toHaveLength(0);
    });

    it('respects the limit', () => {
        expect(searchCities('al', 3)).toHaveLength(3);
    });
});

describe('exactCity', () => {
    /**
     * The input sends the ENGLISH name to OTO whichever language was typed, so an
     * Arabic name typed by hand still has to resolve.
     */
    it('resolves an Arabic name typed by hand to its English spelling', () => {
        expect(exactCity('الرياض')?.en).toBe('Riyadh');
        expect(exactCity('جدة')?.en).toBe('Jeddah');
    });

    it('resolves an English name regardless of case', () => {
        expect(exactCity('riyadh')?.en).toBe('Riyadh');
    });

    it('is undefined for a partial or unknown name', () => {
        expect(exactCity('riy')).toBeUndefined();
        expect(exactCity('Dubai')).toBeUndefined();
        expect(exactCity('')).toBeUndefined();
    });
});

describe('the catalogue itself', () => {
    it('has no duplicate English names', () => {
        const seen = SAUDI_CITIES.map((c) => c.en);

        expect(new Set(seen).size).toBe(seen.length);
    });

    it('has no duplicate Arabic names', () => {
        const seen = SAUDI_CITIES.map((c) => c.ar);

        expect(new Set(seen).size).toBe(seen.length);
    });

    it('gives every city both names', () => {
        for (const city of SAUDI_CITIES) {
            expect(city.en.trim()).not.toBe('');
            expect(city.ar.trim()).not.toBe('');
        }
    });
});
