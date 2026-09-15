<?php

namespace App\Notifications\Concerns;

use App\Models\Setting;
use App\Support\MailAddress;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Builds the email half of every staff alert on the branded `emails.staff-alert`
 * view (Retab logo, teal header, RTL) instead of Laravel's default notification
 * theme, which is English-only, left-to-right and shows the Laravel logo.
 *
 * 🔑 Staff emails are ARABIC (client decision, 2026-09-15). Each notification
 * pins that in its constructor with `$this->locale(self::STAFF_LOCALE)`, and it
 * has to be pinned: these are QUEUED, the worker runs in the app default locale
 * (`en`), and Laravel only switches language for a notification that names one.
 * The bell rows are unaffected — their payload is structured and the admin panel
 * renders it in the admin's own language.
 */
trait SendsStaffMail
{
    public const STAFF_LOCALE = 'ar';

    /**
     * @param  array<int, string>  $lines  opening paragraphs
     * @param  array<string, string>  $details  label => value rows
     * @param  array<int, array{quantity: int, name: string, total: string}>  $items
     */
    protected function staffMail(
        string $subject,
        string $heading,
        array $lines = [],
        array $details = [],
        array $items = [],
        ?string $actionUrl = null,
        ?string $actionLabel = null,
        ?string $note = null,
    ): MailMessage {
        return (new MailMessage)
            ->subject($subject)
            ->view('emails.staff-alert', [
                'locale' => self::STAFF_LOCALE,
                'storeName' => (string) (Setting::get('store_name_ar') ?: config('app.name')),
                // Absolute URL built from APP_URL, so production links the live file.
                'logoUrl' => asset('images/footer/logo.png'),
                'heading' => $heading,
                'lines' => $lines,
                // Drop empty rows (a contact message with no email, say) rather than
                // printing a label with nothing beside it.
                'details' => array_filter($details, fn ($value) => $value !== null && $value !== ''),
                'items' => $items,
                'actionUrl' => $actionUrl,
                'actionLabel' => $actionLabel,
                'note' => $note,
            ]);
    }

    /**
     * The bell always; the email only when the account has an address AND has
     * not been switched off on the Staff page (`users.staff_email_alerts`).
     *
     * `users.email` is nullable (a staff account can be phone-only under the OTP
     * identity model) and the mail transport throws on an empty address, so the
     * address check is not optional. `?? true` covers a freshly created model
     * whose DB default has not been read back yet.
     *
     * @return array<int, string>
     */
    protected function staffChannels(object $notifiable): array
    {
        $email = $notifiable->email ?? null;

        // Deliverable too: a staff login on a non-routable address (`…@retab.local`)
        // would only bounce, and bounces damage the sending domain's reputation.
        $wantsMail = filled($email)
            && MailAddress::isDeliverable($email)
            && ($notifiable->staff_email_alerts ?? true);

        return $wantsMail ? ['database', 'mail'] : ['database'];
    }

    /** "175.50 ريال" in the pinned staff language. */
    protected function money(float|string|null $amount): string
    {
        return number_format((float) $amount, 2).' '.__('emails.common.currency');
    }
}
