<?php

namespace App\Mail;

use App\Models\Setting;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * "Confirm deleting the bank and registration details."
 *
 * 🔴 This email IS the security control, not a courtesy. Masking the IBAN in the
 * panel only stops shoulder-surfing; requiring a link from the owner's mailbox is
 * what makes a stolen admin session insufficient to destroy the account details
 * every bank-transfer customer pays into.
 *
 * ⚠️ Deliberately NOT queued. The owner is sitting on the page waiting, and a
 * queued send would report "email sent" whether or not the worker ever ran it —
 * the one failure mode you cannot have on a confirmation step.
 */
class SensitiveDataDeletionMail extends Mailable
{
    /** Must equal SendsStaffMail::STAFF_LOCALE; see the constructor. */
    public const LOCALE = 'ar';

    public function __construct(
        private readonly string $confirmUrl,
        private readonly int $minutes,
    ) {
        // Pinned Arabic like every other staff email: the recipient's panel
        // language lives in their browser and cannot be read from here.
        //
        // ⚠️ Deliberately a local constant rather than SendsStaffMail::STAFF_LOCALE.
        // PHP forbids reading a constant through a TRAIT's name ("Cannot access
        // trait constant ... directly"), so that reference throws at runtime. It
        // must match that trait's value; a test pins the two together.
        $this->locale(self::LOCALE);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('emails.sensitive.subject'));
    }

    public function content(): Content
    {
        // Reuses the shared staff-alert shell rather than a second template, so
        // this inherits the branded layout, the logo and the RTL handling.
        return new Content(
            view: 'emails.staff-alert',
            with: [
                'locale' => self::LOCALE,
                'storeName' => (string) (Setting::get('store_name_ar') ?: config('app.name')),
                'logoUrl' => asset('images/footer/logo.png'),
                'heading' => __('emails.sensitive.heading'),
                'lines' => [
                    __('emails.sensitive.line_1'),
                    __('emails.sensitive.line_2'),
                ],
                'details' => [],
                'items' => [],
                'actionUrl' => $this->confirmUrl,
                'actionLabel' => __('emails.sensitive.action'),
                // States the expiry and what to do if they did not ask, which is
                // the only useful thing to say to someone who did not expect this.
                'note' => __('emails.sensitive.note', ['minutes' => $this->minutes]),
            ],
        );
    }
}
