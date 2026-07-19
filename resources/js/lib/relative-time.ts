/**
 * Friendly "N minutes/hours/days/weeks ago", localized and pluralized by the
 * browser via Intl.RelativeTimeFormat. Rendered client-side (not server-side
 * diffForHumans) so it follows the admin's language toggle, which is separate
 * from the server session locale.
 */
export function relativeTimeFromMinutes(minutes: number, locale: string): string {
    const rtf = new Intl.RelativeTimeFormat(locale, { numeric: 'auto' });
    if (minutes < 60) return rtf.format(-minutes, 'minute');
    const hours = Math.round(minutes / 60);
    if (hours < 24) return rtf.format(-hours, 'hour');
    const days = Math.round(hours / 24);
    if (days < 14) return rtf.format(-days, 'day');
    return rtf.format(-Math.round(days / 7), 'week');
}

/** Same, computed from an ISO timestamp (clamped so future skew reads as "now"). */
export function relativeTimeFromIso(iso: string, locale: string): string {
    const minutes = Math.max(0, Math.round((Date.now() - new Date(iso).getTime()) / 60000));
    return relativeTimeFromMinutes(minutes, locale);
}
