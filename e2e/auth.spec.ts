import { expect, test, uniqueEmail } from './fixtures';

// Authentication: email/password registration (new customer) and login (the
// seeded admin). Session-based auth, so success = being let into a gated page.

test('a new customer can register', async ({ page }) => {
    await page.goto('/register');

    await page.locator('input[name="name"]').fill('E2E Customer');
    await page.locator('input[name="email"]').fill(uniqueEmail());
    await page.locator('input[name="password"]').fill('password123');
    await page.locator('input[name="password_confirmation"]').fill('password123');

    await page.locator('button[type="submit"]').click();

    // Registration logs the user in and leaves the register page.
    await expect(page).not.toHaveURL(/\/register/);

    // Being let into the account area (not bounced to /login) proves the session.
    await page.goto('/account');
    await expect(page).toHaveURL(/\/account/);
});

test('the seeded admin can log in', async ({ page }) => {
    await page.goto('/login');

    await page.locator('input[name="email"]').fill('admin@retab.com.sa');
    await page.locator('input[name="password"]').fill('password');
    await page.locator('button[type="submit"]').click();

    // Staff land in the back-office.
    await expect(page).toHaveURL(/\/admin/);
});

test('gated pages bounce guests to login', async ({ page }) => {
    await page.goto('/account');
    await expect(page).toHaveURL(/\/login/);
});
