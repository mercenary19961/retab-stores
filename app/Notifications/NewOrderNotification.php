<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\OrderItem;
use App\Notifications\Concerns\SendsStaffMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * Staff alert that a new order needs attention: an in-panel bell row plus an
 * email. WhatsApp is dispatched separately (WhatsAppService), because it goes to
 * a configured phone list rather than to the staff user records.
 *
 * The database payload is STRUCTURED (order number + total + type), not
 * pre-rendered prose: the admin bell renders the title/message client-side
 * through i18n so it follows the admin's language toggle (the panel is EN-first
 * with an AR switch, independent of the server session locale).
 *
 * ⚠️ The email, by contrast, IS pre-rendered, and in ARABIC on the branded
 * staff layout (client decision 2026-09-15, see SendsStaffMail). The admin's
 * panel language lives in their browser, so a customer-triggered alert cannot
 * follow it; the store's primary language is the next best answer.
 */
class NewOrderNotification extends Notification implements ShouldQueue
{
    use Queueable, SendsStaffMail, SerializesModels;

    public function __construct(private Order $order)
    {
        $this->locale(self::STAFF_LOCALE);
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        // `users.email` is NULLABLE here (a staff account can be phone-only under
        // the OTP identity model) and the mail transport throws on an empty
        // address — so email is opt-in per recipient, not assumed.
        return $notifiable->email ? ['database', 'mail'] : ['database'];
    }

    /**
     * Keep the bell row on the `sync` connection so it lands the instant the
     * order does (no worker in the loop); only the email is queued, off the
     * customer's checkout request.
     *
     * @return array<string, string>
     */
    public function viaConnections(): array
    {
        return ['database' => 'sync'];
    }

    /**
     * The subject names what was bought (lead product + "and N more"), and the
     * body lists every line, so staff can judge stock before opening the panel.
     * Names come from the order-line snapshot.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $number = $this->order->order_number;
        $summary = $this->order->itemsSummary(self::STAFF_LOCALE);

        return $this->staffMail(
            subject: $summary
                ? __('emails.staff.new_order.subject', ['items' => $summary])." ({$number})"
                : __('emails.staff.new_order.subject_plain', ['number' => $number]),
            heading: __('emails.staff.new_order.heading'),
            lines: [__('emails.staff.new_order.intro')],
            details: [
                __('emails.staff.order_number') => $number,
                __('emails.staff.customer') => $this->order->customer_name,
                __('emails.staff.total') => $this->money($this->order->total),
            ],
            items: $this->order->items->map(fn (OrderItem $item) => [
                'quantity' => $item->quantity,
                'name' => $this->itemName($item),
                'total' => $this->money($item->line_total),
            ])->all(),
            actionUrl: url("/admin/orders/{$number}"),
            actionLabel: __('emails.staff.open_order'),
            note: __('emails.staff.new_order.note'),
        );
    }

    /** Arabic snapshot name (English fallback), plus the chosen size if any. */
    private function itemName(OrderItem $item): string
    {
        $name = $item->product_name_ar ?: $item->product_name_en;
        $option = $item->option_label_ar ?: $item->option_label_en;

        return $option ? "{$name} ({$option})" : (string) $name;
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'new_order',
            'order_number' => $this->order->order_number,
            'total' => number_format((float) $this->order->total, 2),
            'currency' => 'SAR',
            'url' => "/admin/orders/{$this->order->order_number}",
        ];
    }
}
