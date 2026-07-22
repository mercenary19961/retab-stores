import { describe, expect, it } from 'vitest';
import {
    CONSENT_COOKIE,
    POLICY_VERSION,
    choiceForAction,
    consentModePayload,
    needsConsent,
    parseConsent,
    readCookie,
} from './consent';

const cookie = (v: unknown) => `${CONSENT_COOKIE}=${encodeURIComponent(JSON.stringify(v))}`;

describe('parseConsent', () => {
    it('reads a valid current-version choice', () => {
        expect(parseConsent(encodeURIComponent(JSON.stringify({ analytics: true, marketing: false, v: POLICY_VERSION }))))
            .toEqual({ analytics: true, marketing: false, v: POLICY_VERSION });
    });

    it.each([
        ['absent', null],
        ['empty', ''],
        ['not json', 'nonsense'],
        ['wrong types', encodeURIComponent(JSON.stringify({ analytics: 'yes', marketing: false, v: POLICY_VERSION }))],
        ['missing keys', encodeURIComponent(JSON.stringify({ v: POLICY_VERSION }))],
    ])('returns null for %s', (_label, raw) => {
        expect(parseConsent(raw)).toBeNull();
    });

    it('rejects a choice made under an older policy version', () => {
        // A wording change must re-prompt: consent to old text isn't consent to new.
        const stale = encodeURIComponent(JSON.stringify({ analytics: true, marketing: true, v: '0' }));

        expect(parseConsent(stale)).toBeNull();
    });
});

describe('readCookie', () => {
    it('finds a cookie among others', () => {
        const jar = `XSRF-TOKEN=abc; ${cookie({ analytics: true, marketing: true, v: POLICY_VERSION })}; other=1`;

        expect(readCookie(jar, CONSENT_COOKIE)).not.toBeNull();
    });

    it('does not match a cookie whose name is only a suffix', () => {
        expect(readCookie(`other_${CONSENT_COOKIE}=x`, CONSENT_COOKIE)).toBeNull();
    });

    it('returns null when absent', () => {
        expect(readCookie('a=1; b=2', CONSENT_COOKIE)).toBeNull();
    });
});

describe('needsConsent', () => {
    it('is true on a first visit', () => {
        expect(needsConsent('')).toBe(true);
    });

    it('is false once a current decision exists', () => {
        expect(needsConsent(cookie({ analytics: false, marketing: false, v: POLICY_VERSION }))).toBe(false);
    });

    it('is true again after a policy version bump', () => {
        expect(needsConsent(cookie({ analytics: true, marketing: true, v: 'old' }))).toBe(true);
    });
});

describe('choiceForAction', () => {
    it('accept_all grants everything', () => {
        expect(choiceForAction('accept_all')).toEqual({ analytics: true, marketing: true });
    });

    it('reject_all denies everything', () => {
        expect(choiceForAction('reject_all')).toEqual({ analytics: false, marketing: false });
    });

    it('accept/reject ignore the panel state', () => {
        // Guards against a stale Customise panel leaking into a one-click choice.
        const panel = { analytics: true, marketing: true };

        expect(choiceForAction('reject_all', panel)).toEqual({ analytics: false, marketing: false });
    });

    it('custom uses the panel toggles', () => {
        expect(choiceForAction('custom', { analytics: true, marketing: false }))
            .toEqual({ analytics: true, marketing: false });
    });

    it('custom defaults to denied when the panel is missing', () => {
        expect(choiceForAction('custom')).toEqual({ analytics: false, marketing: false });
    });
});

describe('consentModePayload', () => {
    it('maps marketing onto every ad signal', () => {
        expect(consentModePayload({ analytics: false, marketing: true })).toEqual({
            ad_storage: 'granted',
            ad_user_data: 'granted',
            ad_personalization: 'granted',
            analytics_storage: 'denied',
            personalization_storage: 'granted',
        });
    });

    it('keeps analytics independent of advertising', () => {
        const payload = consentModePayload({ analytics: true, marketing: false });

        expect(payload.analytics_storage).toBe('granted');
        expect(payload.ad_storage).toBe('denied');
    });

    it('denies everything on a full rejection', () => {
        const payload = consentModePayload({ analytics: false, marketing: false });

        expect(Object.values(payload).every((v) => v === 'denied')).toBe(true);
    });
});
