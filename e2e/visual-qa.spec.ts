import { test } from '@playwright/test';

// Visual-QA sweep: full-page screenshots of the key storefront pages in both
// locales (AR default / EN via the plaintext `locale` cookie), plus any console
// errors. Screenshots go to QA_OUT; not assertions, this is a capture pass that
// also seeds the future E2E suite.
const OUT = process.env.QA_OUT || 'qa-shots';
const BASE = process.env.QA_BASE_URL || 'https://retab-website-production.up.railway.app';

const targets = [
    { name: 'home', path: '/' },
    { name: 'shop', path: '/shop' },
    { name: 'product', path: '/products/Najdi-coffee' },
    { name: 'login', path: '/login' },
    { name: 'cart', path: '/cart' },
];

for (const locale of ['ar', 'en'] as const) {
    for (const t of targets) {
        test(`${locale} · ${t.name}`, async ({ page, context }) => {
            if (locale === 'en') {
                await context.addCookies([{ name: 'locale', value: 'en', url: BASE }]);
            }

            const errors: string[] = [];
            page.on('console', (m) => {
                if (m.type() === 'error') errors.push(m.text());
            });
            page.on('pageerror', (e) => errors.push(String(e)));

            await page.goto(t.path, { waitUntil: 'networkidle' });
            await page.waitForTimeout(1200); // let carousel/nav animations settle
            await page.screenshot({ path: `${OUT}/${locale}-${t.name}.png`, fullPage: true });

            if (errors.length) {
                console.log(`\n[${locale} ${t.name}] ${errors.length} console error(s):\n  ${errors.slice(0, 6).join('\n  ')}`);
            }
        });
    }
}
