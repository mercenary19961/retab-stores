/**
 * First Strong Isolate / Pop Directional Isolate: invisible direction markers.
 * Built from code points rather than typed as escapes, so no editor or tool can
 * silently turn them into raw invisible characters (or strip them).
 */
export const FSI = String.fromCharCode(0x2068);
export const PDI = String.fromCharCode(0x2069);

/**
 * Wrap text in Unicode direction isolates (FSI U+2068 … PDI U+2069).
 *
 * 🔑 Use it for any NAME dropped into a sentence — a product, category or event
 * name inside "You are about to delete “{{name}}”." The admin panel is bilingual
 * but the data is mostly Arabic, so an English sentence regularly carries an
 * Arabic name (and the reverse). Without isolation the name's direction leaks
 * into the sentence around it: quotes and punctuation land on the wrong side,
 * and with `dir="auto"` on the paragraph an Arabic name at the start flipped a
 * whole English sentence right-to-left.
 *
 * Isolated, the name keeps its own direction and the sentence keeps the panel's.
 * The marks are invisible, and the paragraph must NOT use `dir="auto"`: its
 * direction should come from the panel's language, not from whatever name happens
 * to come first.
 */
export function isolate(text: string): string {
    return `${FSI}${text}${PDI}`;
}
