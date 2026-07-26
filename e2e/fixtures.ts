import { test as base, expect } from '@playwright/test';

// A consent value that satisfies needsConsent() (see resources/js/lib/consent.ts:
// { analytics, marketing, v: POLICY_VERSION }), so the cookie banner never renders
// and can't intercept clicks in tests.
const CONSENT = encodeURIComponent(JSON.stringify({ analytics: false, marketing: false, v: '1' }));

/**
 * All specs import `test`/`expect` from here. Every browser context is pre-seeded
 * with the consent cookie so the bottom-of-page banner stays out of the way.
 */
export const test = base.extend({
    // Second arg is Playwright's fixture callback (named `run` here, not `use`, so
    // the react-hooks lint rule doesn't mistake it for React's `use` hook).
    context: async ({ context, baseURL }, run) => {
        await context.addCookies([{ name: 'retab_consent', value: CONSENT, url: baseURL! }]);
        await run(context);
    },
});

export { expect };

/** Unique-ish email for register tests so re-runs never collide. */
export function uniqueEmail(prefix = 'e2e'): string {
    return `${prefix}+${Date.now()}${Math.floor(Math.random() * 1000)}@example.com`;
}
