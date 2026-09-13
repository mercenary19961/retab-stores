/**
 * Event times are stored and edited in UTC (the app's timezone) while the business
 * runs on Riyadh time (UTC+3, no daylight saving). So every stored value is shown
 * with its Riyadh reading beside it — otherwise "30/09 21:00" reads as the wrong
 * day, and "fixing" it to midnight ends a campaign three hours late.
 */

/** Accepts `YYYY-MM-DD HH:mm(:ss)` and the `YYYY-MM-DDTHH:mm` a datetime-local gives. */
function parseUtc(value: string): Date {
    return new Date(`${value.replace(' ', 'T').slice(0, 16)}:00Z`);
}

// Latin digits and the Gregorian calendar in Arabic too: `ar-SA` alone defaults to
// the Hijri calendar, which would put a different month name on the same instant.
const localeTag = (locale: string) => (locale === 'ar' ? 'ar-SA-u-ca-gregory-nu-latn' : 'en-GB');

/** `1 Oct 2026, 00:00` in Riyadh time. Empty for an empty or unparsable value. */
export function riyadhLabel(value: string | null | undefined, locale: string): string {
    if (!value) return '';
    const date = parseUtc(value);
    if (Number.isNaN(date.getTime())) return '';

    return new Intl.DateTimeFormat(localeTag(locale), {
        timeZone: 'Asia/Riyadh',
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    }).format(date);
}

/** `1 Oct` in Riyadh time — for dense rows where the year and hour are noise. */
export function riyadhDay(value: string | null | undefined, locale: string): string {
    if (!value) return '';
    const date = parseUtc(value);
    if (Number.isNaN(date.getTime())) return '';

    return new Intl.DateTimeFormat(localeTag(locale), { timeZone: 'Asia/Riyadh', day: 'numeric', month: 'short' }).format(date);
}

export interface Timing {
    phase: 'upcoming' | 'running' | 'over';
    /** How much of the window has passed, 0–1. */
    fraction: number;
    /** Whole days to the next boundary (start when upcoming, end when running), rounded up. */
    days: number;
}

/** Where "now" sits in an event's window. */
export function timing(startsAt: string, endsAt: string, now: number = Date.now()): Timing {
    const start = parseUtc(startsAt).getTime();
    const end = parseUtc(endsAt).getTime();
    const day = 86_400_000;

    if (now < start) return { phase: 'upcoming', fraction: 0, days: Math.ceil((start - now) / day) };
    if (now >= end) return { phase: 'over', fraction: 1, days: 0 };

    return { phase: 'running', fraction: (now - start) / (end - start), days: Math.ceil((end - now) / day) };
}
