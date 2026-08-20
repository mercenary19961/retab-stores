import { expect, test } from './fixtures';

/**
 * Site-wide product search.
 *
 * 🔑 This is the only gate that can catch this feature breaking. The matcher itself
 * is unit-tested on both sides (`resources/js/lib/search.test.ts`,
 * `tests/Feature/SearchTest.php`), but nothing there proves the overlay opens, that
 * it escapes the header's transformed containing block, or that the results page it
 * links to actually agrees with the suggestions it just showed.
 *
 * Fixture data is `CatalogSeeder` — «تمر سكري فاخر» / "Premium Sukkari Dates",
 * «قهوة عربية فاخرة» / "Premium Arabic Coffee", «بوكس تمور فاخر مشكّل» etc.
 */

const overlay = '[role=dialog] input[type=search]';

/** Open the overlay and wait for the index to arrive, so nothing races the fetch. */
async function openSearch(page: import('@playwright/test').Page) {
    await page.getByTestId('nav-search').click();
    await page.locator(overlay).waitFor();
    await page.locator(overlay).fill('a');
    await expect(page.locator('[role=dialog] .animate-spin')).toHaveCount(0);
    await page.locator(overlay).fill('');
}

async function names(page: import('@playwright/test').Page): Promise<string[]> {
    return page.locator('[role=dialog] ul li button span span:first-child').allTextContents();
}

test('the navbar search opens from any page, not just the catalogue', async ({ page }) => {
    // The whole point of moving it into the navbar: before this, the only search
    // box lived on /shop, so searching from a product page meant leaving it.
    await page.goto('/products/sukkari-1kg');
    await openSearch(page);
    await expect(page.locator(overlay)).toBeFocused();
});

test('finds a product by its English name and by its Arabic name', async ({ page }) => {
    await page.goto('/');
    await openSearch(page);

    await page.locator(overlay).fill('coffee');
    await expect(page.locator('[role=dialog] ul li button span span').first()).toBeVisible();
    const english = await names(page);
    expect(english.length).toBeGreaterThan(0);

    await page.locator(overlay).fill('قهوة');
    const arabic = await names(page);

    // The claim is that the two languages return the SAME products, which is what
    // `terms` indexing both names buys. A fixed count would be asserting the
    // seeder's contents instead.
    expect(arabic).toEqual(english);
});

test('folds Arabic spelling variants', async ({ page }) => {
    await page.goto('/');
    await openSearch(page);

    // «سكرى» with alef maqsura is not a typo, it is how people spell it.
    await page.locator(overlay).fill('سكري');
    const spelled = await names(page);
    expect(spelled.length).toBeGreaterThan(0);

    await page.locator(overlay).fill('سكرى');
    expect(await names(page)).toEqual(spelled);
});

test('tolerates a misspelling and still lands the shopper on results', async ({ page }) => {
    await page.goto('/');
    await openSearch(page);

    await page.locator(overlay).fill('sukari'); // one k
    const suggestions = await names(page);
    expect(suggestions.length).toBeGreaterThan(0);

    // 🔑 The results page matches by substring, so the footer must offer the
    // CORRECTED term — otherwise clicking it goes from suggestions to an empty page.
    const footer = page.locator('[role=dialog] > div > button').last();
    await expect(footer).toContainText('sukkari');

    await footer.click();
    await page.waitForURL(/\/shop\?q=/);
    await expect(page).toHaveURL(/q=sukkari/);
    await expect(page.locator('a[href^="/products/"]')).toHaveCount(suggestions.length);
});

test('the results page returns what the suggestions promised', async ({ page }) => {
    await page.goto('/');
    await openSearch(page);

    await page.locator(overlay).fill('تمور');
    const suggested = (await names(page)).length;
    expect(suggested).toBeGreaterThan(0);

    await page.locator('[role=dialog] > div > button').last().click();
    await page.waitForURL(/\/shop\?q=/);
    await expect(page.locator('a[href^="/products/"]')).toHaveCount(suggested);
});

test('a result opens the product', async ({ page }) => {
    await page.goto('/');
    await openSearch(page);

    await page.locator(overlay).fill('sukkari');
    await page.locator('[role=dialog] ul li button').first().click();
    await page.waitForURL(/\/products\//);
    await expect(page.locator('[role=dialog]')).toHaveCount(0);
});

test('arrow keys move the highlight and Enter opens it', async ({ page }) => {
    await page.goto('/');
    await openSearch(page);

    await page.locator(overlay).fill('تمر');
    await page.locator(overlay).press('ArrowDown');
    await page.locator(overlay).press('Enter');
    await page.waitForURL(/\/products\//);
});

test('Escape closes it even when focus has left the field', async ({ page }) => {
    // ⚠️ Regression guard. Escape was handled only on the input's own onKeyDown, so
    // a press that arrived before focus landed (it is set on a timer) did nothing —
    // and the page behind stayed scroll-locked with the overlay still up.
    await page.goto('/shop');
    await page.getByTestId('nav-search').click();
    await page.locator('[role=dialog]').waitFor();
    await page.evaluate(() => (document.activeElement as HTMLElement | null)?.blur());

    await page.keyboard.press('Escape');

    await expect(page.locator('[role=dialog]')).toHaveCount(0);
    expect(await page.evaluate(() => document.body.style.overflow)).toBe('');
});

test('covers the viewport when opened after scrolling', async ({ page }) => {
    // 🔴 The header writes `transform` on every scroll tick, and a transformed
    // ancestor becomes the containing block for `position: fixed` children. The
    // mobile drawer shipped broken this way and it looked intermittent, because an
    // unscrolled page is fine. This asserts the overlay really is portalled out.
    await page.goto('/shop');
    await page.mouse.wheel(0, 1500);
    await page.waitForTimeout(300);
    await page.evaluate(() => window.scrollTo(0, 0));
    await page.waitForTimeout(1000);

    await page.getByTestId('nav-search').click();
    const box = await page.locator('[role=dialog]').boundingBox();
    const viewport = page.viewportSize()!;

    expect(Math.round(box!.height)).toBe(viewport.height);
    expect(await page.evaluate(() => document.querySelector('[role=dialog]')?.parentElement?.tagName)).toBe('BODY');
});

test('is reachable on a phone through the drawer', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/');

    // Row 1 has no room for a fifth control at 320px, so the phone entry point is a
    // search FIELD at the top of the drawer rather than another icon.
    await page.locator('header button').first().click();
    await page.getByTestId('nav-search-mobile').click();

    await page.locator(overlay).fill('dates');
    await expect(page.locator('[role=dialog] ul li').first()).toBeVisible();
});
