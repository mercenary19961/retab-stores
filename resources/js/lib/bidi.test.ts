import { describe, expect, it } from 'vitest';

import { FSI, PDI, isolate } from './bidi';

describe('isolate', () => {
    it('uses the real isolate code points, not look-alike text', () => {
        expect(FSI.codePointAt(0)).toBe(0x2068);
        expect(PDI.codePointAt(0)).toBe(0x2069);
        expect(FSI).toHaveLength(1);
        expect(PDI).toHaveLength(1);
    });

    it('wraps the text, and only the text', () => {
        const wrapped = isolate('تمور فاخرة');

        expect(wrapped.codePointAt(0)).toBe(0x2068);
        expect(wrapped.codePointAt(wrapped.length - 1)).toBe(0x2069);
        expect(wrapped.slice(1, -1)).toBe('تمور فاخرة');
    });
});
