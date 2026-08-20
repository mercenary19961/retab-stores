<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\PaymentExpiringNotification;
use App\Support\ExpiringAuthorizations;
use Illuminate\Console\Command;
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
        // Held, still waiting on the admin, and not already alerted. The stamp is
        // what stops an hourly command re-alerting for the whole window and
        // training staff to ignore the one notification that costs money — it is
        // this command's own concern, which is why it is added here rather than
        // living in the shared definition the dashboard also reads.
        $orders = ExpiringAuthorizations::approaching(
            ExpiringAuthorizations::query()->whereNull('payment_expiry_alerted_at')
        );

        if ($orders->isEmpty()) {
            $this->info('No authorisations approaching expiry.');

            return self::SUCCESS;
        }

        $staff = User::staff()->get();

        foreach ($orders as $order) {
            $hoursLeft = ExpiringAuthorizations::hoursLeft($order);

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
}
