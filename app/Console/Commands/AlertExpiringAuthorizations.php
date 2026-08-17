<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionType;
use App\Models\Order;
use App\Models\User;
use App\Notifications\PaymentExpiringNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

/**
 * Warn staff before a Tamara authorisation lapses.
 *
 * 🔴 Why this exists: Tamara holds the funds and we capture at admin
 * confirmation, so an order nobody confirms inside the window loses its hold
 * and can never be captured. The sale evaporates silently — no error, no
 * failed job, just an order that quietly stops being collectable. The dashboard
 * has counted these since it was built (`tamaraExpiring`), but a count only
 * helps somebody already looking at the dashboard.
 *
 * 🔑 This is the project's FIRST scheduled task, so it needs a `schedule:work`
 * service on Railway to run at all (see routes/console.php).
 */
class AlertExpiringAuthorizations extends Command
{
    protected $signature = 'payments:alert-expiring {--dry-run : List the orders without notifying anyone}';

    protected $description = 'Alert staff about Tamara authorisations approaching expiry';

    public function handle(): int
    {
        $authHours = (int) config('services.tamara.authorization_hours', 48);
        $warnHours = (int) config('services.tamara.expiry_warning_hours', 12);
        $alertAfter = max($authHours - $warnHours, 1);

        // Held, still waiting on the admin, and not already alerted. The stamp is
        // what stops an hourly command re-alerting for the whole window and
        // training staff to ignore the one notification that costs money.
        $orders = Order::where('payment_method', PaymentMethod::Tamara)
            ->where('payment_status', PaymentStatus::Authorized)
            ->where('status', OrderStatus::AwaitingConfirmation)
            ->whereNull('payment_expiry_alerted_at')
            ->with(['payments' => fn ($q) => $q
                ->where('type', PaymentTransactionType::Authorization->value)
                ->where('status', 'authorized')
                ->latest(),
            ])
            ->get()
            ->filter(fn (Order $order) => $this->authorizedAt($order)->lte(now()->subHours($alertAfter)));

        if ($orders->isEmpty()) {
            $this->info('No authorisations approaching expiry.');

            return self::SUCCESS;
        }

        $staff = User::staff()->get();

        foreach ($orders as $order) {
            // Round DOWN, so the figure a human reads is never more optimistic
            // than reality.
            $hoursLeft = max((int) floor($authHours - $this->authorizedAt($order)->diffInHours(now())), 0);

            $this->warn("{$order->order_number} — ~{$hoursLeft}h left — ".number_format((float) $order->total, 2).' SAR');

            if ($this->option('dry-run')) {
                continue;
            }

            Notification::send($staff, new PaymentExpiringNotification($order, $hoursLeft));
            $order->forceFill(['payment_expiry_alerted_at' => now()])->save();
        }

        $this->info($this->option('dry-run')
            ? $orders->count().' order(s) would be alerted.'
            : 'Alerted staff about '.$orders->count().' order(s).');

        return self::SUCCESS;
    }

    /**
     * When the hold was actually taken.
     *
     * ⚠️ NOT `orders.created_at`, which is what the dashboard counter uses. They
     * are usually minutes apart, but an order paid through the resume-payment
     * route can be authorised long after it was placed — and using the wrong one
     * there would either alert far too early or, worse, after the hold had
     * already lapsed. Falls back to `created_at` only when no authorization
     * ledger row exists.
     */
    private function authorizedAt(Order $order): Carbon
    {
        return $order->payments->first()?->created_at ?? $order->created_at;
    }
}
