<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Whether an email address can actually receive mail.
 *
 * 🔑 Exists because staff accounts are deliberately created on a non-routable
 * address (e.g. editor@retab.local) so that the account has no self-service way
 * back in: forgetting the password means asking an admin, by design. The address
 * is the mechanism, so the check is on the ADDRESS rather than on a flag someone
 * has to remember to set — a second mailbox-less account created later opts out
 * of email automatically just by using one of these suffixes.
 *
 * The suffixes are the ones reserved by standards precisely so they can never
 * resolve on the public internet: RFC 2606 (.test, .example, .invalid,
 * .localhost), RFC 8375 (home.arpa), RFC 6762 (.local, mDNS), plus .internal,
 * which ICANN designated for private use. None of them can be registered, so
 * treating them as undeliverable can never be wrong for a real customer.
 */
class MailAddress
{
    /** @var list<string> */
    public const NON_ROUTABLE_SUFFIXES = [
        '.local',
        '.internal',
        '.invalid',
        '.test',
        '.example',
        '.localhost',
        '.home.arpa',
    ];

    /**
     * True when mail sent to this address could plausibly arrive.
     *
     * Null / empty counts as undeliverable, which is the honest answer for the
     * phone-only accounts the OTP identity model allows (users.email is nullable).
     */
    public static function isDeliverable(?string $email): bool
    {
        $domain = Str::of((string) $email)->after('@')->lower()->trim()->value();

        if ($domain === '' || ! str_contains($domain, '.')) {
            // No domain at all, or a bare hostname like "localhost".
            return false;
        }

        foreach (self::NON_ROUTABLE_SUFFIXES as $suffix) {
            if (str_ends_with($domain, $suffix)) {
                return false;
            }
        }

        return true;
    }
}
