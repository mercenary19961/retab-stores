import { expect, test } from './fixtures';

// Visual-QA sweep: full-page screenshots of the key storefront pages in both
// locales (AR default / EN via the plaintext `locale` cookie), logging any console
// errors. Not assertions — a capture pass. Screenshots go to QA_OUT. Works against
// any dataset (the product page opens the first catalogue card, not a fixed slug).
const OUT = process.env.QA_OUT || 'qa-shots';

const staticTargets = [
    { name: 'home', path: '/' },
    { name: 'shop', path: '/shop' },
    { name: 'login', path: '/login' },
    { name: 'cart', path: '/cart' },
];

for (const locale of ['ar', 'en'] as const) {
    test(`${locale} · storefront capture`, async ({ page, context, baseURL }) => {
        if (locale === 'en') {
            await context.addCookies([{ name: 'locale', value: 'en', url: baseURL! }]);
        }

        const errors: string[] = [];
        page.on('console', (m) => {
            if (m.type() === 'error') errors.push(m.text());
        });
        page.on('pageerror', (e) => errors.push(String(e)));

        for (const t of staticTargets) {
            await page.goto(t.path, { waitUntil: 'networkidle' });
            await page.waitForTimeout(1000); // let carousel/nav animations settle
            await page.screenshot({ path: `${OUT}/${locale}-${t.name}.png`, fullPage: true });
        }

        // Product page — open the first catalogue card (slug-agnostic).
        await page.goto('/shop', { waitUntil: 'networkidle' });
        const firstProduct = page.locator('a[href^="/products/"]').first();
        if (await firstProduct.count()) {
            await firstProduct.click();
            await page.waitForLoadState('networkidle');
            await page.screenshot({ path: `${OUT}/${locale}-product.png`, fullPage: true });
        }

        if (errors.length) {
            console.log(`\n[${locale}] ${errors.length} console error(s):\n  ${errors.slice(0, 6).join('\n  ')}`);
        }
        // Surfacing is enough for a capture pass; don't fail the run on console noise.
        expect(true).toBeTruthy();
    });
}
