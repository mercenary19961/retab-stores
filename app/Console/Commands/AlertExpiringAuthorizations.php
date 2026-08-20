<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\User;
use App\Notifications\PaymentExpiringNotification;
use App\Services\Payments\Tamara\TamaraService;
use App\Services\WhatsApp\WhatsAppService;
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

    public function __construct(
        protected WhatsAppService $whatsapp,
        protected TamaraService $tamara,
    ) {
        parent::__construct();
    }

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

        // Before warning about holds that are still alive, record any that have
        // already died. Otherwise an expired order keeps sitting in the dashboard
        // queue looking actionable, and we only find out when a human clicks
        // Confirm and the capture throws.
        $lapsed = $this->reconcileLapsed();

        if ($orders->isEmpty()) {
            $this->info($lapsed > 0
                ? "No authorisations approaching expiry. ({$lapsed} already lapsed and were reopened for payment.)"
                : 'No authorisations approaching expiry.');

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
            // 🔑 And to a PHONE. The bell needs someone already looking at the panel
            // and email needs someone reading it; this is the one staff alert where
            // not seeing it in time loses the sale, so it gets the channel people
            // actually notice. Queued and best-effort — a Meta outage must never
            // stop the bell alert or the stamp below.
            $this->whatsapp->notifyAdminsPaymentExpiring($order, $hoursLeft);
            $order->forceFill(['payment_expiry_alerted_at' => now()])->save();
        }

        $this->info($this->option('dry-run')
            ? $orders->count().' order(s) would be alerted.'
            : 'Alerted staff about '.$orders->count().' order(s).');

        return self::SUCCESS;
    }

    /**
     * Ask Tamara about holds that are past their window and record the ones that
     * have actually lapsed.
     *
     * ⚠️ Only orders PAST the full window are checked, not merely approaching one:
     * that keeps this to a handful of API calls an hour, and a hold inside its
     * window has nothing to reconcile. Our own 48h figure is an assumption
     * (TAMARA_AUTHORIZATION_HOURS), so it is used to decide who to ASK — Tamara's
     * answer, never our clock, is what marks an order dead.
     */
    private function reconcileLapsed(): int
    {
        $past = ExpiringAuthorizations::query()
            ->get()
            ->filter(fn (Order $o) => ExpiringAuthorizations::authorizedAt($o)
                ->lte(now()->subHours(ExpiringAuthorizations::authorizationHours())));

        $count = 0;
        foreach ($past as $order) {
            if ($this->option('dry-run')) {
                $this->line("would check {$order->order_number} with Tamara");

                continue;
            }

            $status = $this->tamara->reconcileLapsed($order);
            if ($status !== null) {
                $this->error("{$order->order_number} — hold {$status}, reopened for payment");
                $count++;
            }
        }

        return $count;
    }
}
