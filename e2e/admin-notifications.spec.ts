import { expect, test, type Page } from './fixtures';

// The admin notification bell: a real customer order has to reach staff in the
// panel, and the panel has to notice new ones WITHOUT a manual reload (the bell
// polls via an Inertia partial reload — see components/admin/notification-bell).

async function loginAsAdmin(page: Page): Promise<void> {
    await page.goto('/login');
    await page.locator('input[name="email"]').fill('admin@retab.com.sa');
    await page.locator('input[name="password"]').fill('password');
    await page.locator('button[type="submit"]').click();
    await expect(page).toHaveURL(/\/admin/);
}

/** Places a guest bank-transfer order and returns its order number. */
async function placeOrder(page: Page): Promise<string> {
    await page.goto('/products/sukkari-1kg');
    await Promise.all([
        page.waitForResponse((r) => r.url().includes('/cart') && r.request().method() === 'POST'),
        page.getByTestId('add-to-cart').click(),
    ]);

    await page.goto('/checkout');
    await page.getByTestId('customer_name').fill('Bell Buyer');
    await page.getByTestId('customer_phone').fill('0512345678');
    await page.getByTestId('city').fill('Riyadh');
    await page.locator('input[name="payment_method"][value="bank_transfer"]').check();

    await Promise.all([page.waitForURL(/\/orders\/.+/), page.getByTestId('place-order').click()]);

    return decodeURIComponent(new URL(page.url()).pathname.split('/').pop()!);
}

test('a new order reaches the admin bell and badges the tab title', async ({ page }) => {
    const orderNumber = await placeOrder(page);

    await loginAsAdmin(page);
    await page.goto('/admin/dashboard');

    // Unread badge on the bell. Other specs may also have placed orders against
    // this shared DB, so assert the shape rather than an exact count.
    const bell = page.getByRole('button', { name: /notifications/i });
    await expect(bell).toBeVisible();
    await expect(bell.locator('span').first()).toHaveText(/^\d+\+?$/);

    // The count is mirrored into the tab title for a minimised panel.
    await expect.poll(() => page.title()).toMatch(/^\(\d+\)\s/);

    // The dropdown lists THIS order — proving the alert is the one just placed.
    // Scoped to the dropdown: the dashboard's own widgets mention order numbers too.
    await bell.click();
    await expect(page.getByTestId('notifications-dropdown').getByText(orderNumber, { exact: false })).toBeVisible();
});

test('the bell refreshes itself without a manual reload', async ({ page }) => {
    // One poll interval is 45s, which is the whole default test budget.
    test.setTimeout(120_000);

    await loginAsAdmin(page);
    await page.goto('/admin/dashboard');

    // router.poll fires an Inertia partial reload asking for only this prop.
    const polled = await page.waitForRequest((req) => req.headers()['x-inertia-partial-data'] === 'notifications', { timeout: 90_000 });

    expect(polled.url()).toContain('/admin/dashboard');
});
