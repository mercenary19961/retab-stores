import { expect, test } from './fixtures';

/**
 * Regression guard for the navbar blank-strip bug, which has now been fixed three
 * separate times (2026-08-08, 08-09, 08-10) — hence a spec rather than another
 * round of manual measurement.
 *
 * The header is `sticky`, so it keeps a box in normal flow and a transform does not
 * remove it. Translating the header further up than the page has scrolled therefore
 * uncovers that box and paints the cream page wrapper across the top of the viewport.
 *
 * The invariant is simply `translate <= scrollY`, and asserting it is stable under a
 * slow CI runner because it is a correctness property rather than a timing threshold:
 * the fix corrects any violation in the frame it appears, so the only way these fail
 * is if the bug is genuinely back.
 *
 * ⚠️ The settle wait matters and is bounded on both sides. It must be long enough for
 * the scroll handler's rAF to have run (a few frames) and short enough to still be
 * inside the reveal's 750ms transition-delay, which is exactly where the third bug
 * lived: the header sat parked off-screen for the remainder of that delay.
 */

const SETTLE = 120;

/** Rendered translate, scroll position, and what is painted at the viewport top. */
async function probe(page: import('@playwright/test').Page) {
    return page.evaluate(() => {
        const header = document.querySelector('header')!;
        const el = document.elementFromPoint(Math.round(window.innerWidth / 2), 3);
        return {
            y: Math.round(window.scrollY),
            // The sticky box rests at viewport 0, so its rect.top IS the translate.
            translate: Math.round(-header.getBoundingClientRect().top),
            // The page wrapper showing through is the bug's signature.
            wrapperAtTop: !!el?.classList.contains('min-h-screen'),
        };
    });
}

async function scrollTo(page: import('@playwright/test').Page, y: number, wait = SETTLE) {
    await page.evaluate((v) => window.scrollTo(0, v), y);
    await page.waitForTimeout(wait);
}

test.describe('storefront navbar never uncovers its own box', () => {
    test('fast scrollbar drag from deep in the page', async ({ page }) => {
        await page.goto('/', { waitUntil: 'networkidle' });

        // Park it hidden, well past the travel band.
        await scrollTo(page, 3000, 800);
        expect((await probe(page)).wrapperAtTop).toBe(false);

        // A drag emits large jumps in quick succession. The first one is what earns
        // the reveal and starts the delay while still far down the page; the later
        // ones arrive inside the band with the element not yet moved.
        for (const y of [2200, 1400, 700, 250, 60, 20, 0]) {
            await scrollTo(page, y, 25);
            const s = await probe(page);
            expect(s.translate, `translate must not exceed scrollY at y=${s.y}`).toBeLessThanOrEqual(s.y);
        }

        await page.waitForTimeout(SETTLE);
        const settled = await probe(page);
        expect(settled.wrapperAtTop).toBe(false);
        expect(settled.translate).toBeLessThanOrEqual(settled.y);
    });

    test('single jump straight into the band (scrollbar track click)', async ({ page }) => {
        await page.goto('/', { waitUntil: 'networkidle' });

        await scrollTo(page, 3000, 800);
        await scrollTo(page, 10);

        const s = await probe(page);
        expect(s.wrapperAtTop).toBe(false);
        expect(s.translate).toBeLessThanOrEqual(s.y);
    });

    test('the header still hides on the way down', async ({ page }) => {
        // The guard above would also pass on a header that never hides at all, so
        // pin the behaviour it is protecting.
        await page.goto('/', { waitUntil: 'networkidle' });

        const band = await page.evaluate(() => document.querySelector('header')!.offsetHeight);
        await scrollTo(page, 1200, 600);

        const s = await probe(page);
        expect(s.translate).toBe(band);
    });
});
