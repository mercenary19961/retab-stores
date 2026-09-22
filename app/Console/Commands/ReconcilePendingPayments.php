<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Services\Payments\PaymentService;
use App\Services\Payments\Tamara\TamaraService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Ask the gateway about orders that are still waiting for money.
 *
 * 🔴 WHY THIS EXISTS, and it is the hole it closes rather than the code that
 * matters: a paid order reaches `awaiting_confirmation` by one of exactly two
 * routes — the gateway's webhook, or the customer's own return to
 * /checkout/result, which calls reconcile(). Both are things that can simply not
 * happen. A webhook can be lost, retried past its limit, or rejected while a
 * signing secret is briefly wrong; the customer can close the tab on the bank's
 * 3-D Secure screen and never come back. When BOTH miss, the money is sitting at
 * Moyasar and our order says unpaid, permanently, with nothing anywhere to
 * notice — no failed job, no error, no dashboard count. The customer is never
 * sent a receipt and staff never see the order in the queue they work from.
 *
 * So this is the third route, and unlike the other two it does not depend on
 * anyone doing anything.
 *
 * 🔑 It re-reads the PROVIDER and never decides anything itself. Both calls below
 * fetch the remote record and run the same amount/currency verification the
 * webhook path does, so this can confirm an order but can never invent a payment.
 * Both are idempotent (an already-settled order early-returns), which is what
 * makes running this every quarter of an hour harmless.
 */
class ReconcilePendingPayments extends Command
{
    protected $signature = 'payments:reconcile
        {--dry-run : List what would be checked without calling the gateway}
        {--limit=50 : Most orders to check in one run}';

    protected $description = 'Re-check unpaid card and Tamara orders against the gateway';

    /**
     * Leave a customer alone while they are still plausibly on the hosted page.
     *
     * Not politeness — a race. The webhook usually lands within seconds of the
     * payment, so checking immediately would mostly spend API calls racing a
     * mechanism that was about to work anyway.
     */
    private const MIN_AGE_MINUTES = 15;

    /**
     * Stop asking eventually. A Moyasar invoice expires and a Tamara session
     * lapses, so past this point the answer can no longer change and re-reading
     * every abandoned checkout forever would grow into real API traffic.
     *
     * ⚠️ Deliberately generous. Getting this wrong in the short direction means
     * abandoning a real payment, which is the exact failure the command exists
     * to prevent.
     */
    private const MAX_AGE_DAYS = 7;

    public function handle(PaymentService $card, TamaraService $tamara): int
    {
        $orders = Order::query()
            ->where('status', OrderStatus::PendingPayment)
            // Pending only. A `failed` order was told "no" by the gateway, and a
            // customer who retries gets a fresh invoice with a fresh reference —
            // so re-reading a failed one asks about a session that is already
            // superseded.
            ->where('payment_status', PaymentStatus::Pending)
            ->whereIn('payment_method', [PaymentMethod::Card, PaymentMethod::Tamara])
            // No reference means the customer never reached the gateway at all,
            // so there is nothing on the other side to ask about.
            ->whereNotNull('gateway_reference')
            ->where('created_at', '<=', now()->subMinutes(self::MIN_AGE_MINUTES))
            ->where('created_at', '>=', now()->subDays(self::MAX_AGE_DAYS))
            ->orderBy('created_at')
            ->limit(max(1, (int) $this->option('limit')))
            ->get();

        if ($orders->isEmpty()) {
            $this->info('Nothing to reconcile.');

            return self::SUCCESS;
        }

        $this->line("Checking {$orders->count()} unpaid order(s) against the gateway.");

        $recovered = 0;
        $failed = 0;

        foreach ($orders as $order) {
            $label = "{$order->order_number} ({$order->payment_method->value}, {$order->total} {$order->currency})";

            if ($this->option('dry-run')) {
                $this->line("  would check {$label}");

                continue;
            }

            try {
                // Each gateway's own re-read: fetch the remote record, verify the
                // amount and currency, and settle the order if it really was paid.
                if ($order->payment_method === PaymentMethod::Card) {
                    $card->reconcile($order);
                } else {
                    $tamara->confirm($order->gateway_reference);
                }

                // 🔑 Judge the outcome from OUR order, not from what the service
                // returned. The two gateways report success differently — reconcile()
                // answers a bool, confirm() answers a nullable Order whose null means
                // "could not be matched locally" — and reading a null as "settled"
                // would report an unmatched order as recovered. The order's own
                // payment_status is the one answer that means the same thing for both.
                $order->refresh();
                $settled = $order->payment_status !== PaymentStatus::Pending;
                $paid = $settled && $order->payment_status !== PaymentStatus::Failed;

                if ($settled && ! $paid) {
                    // The gateway says this one was declined or expired. Not money
                    // found, but no longer stuck either — worth saying, not warning.
                    $this->line("  resolved as {$order->payment_status->value}: {$label}");

                    continue;
                }

                if ($paid) {
                    $recovered++;
                    // 🔴 Loud on purpose. Every row here is an order the webhook AND
                    // the customer's return both failed to settle, which is a signal
                    // about those mechanisms, not just about this order.
                    $this->warn("  RECOVERED {$label} — the webhook and the return both missed this one");
                    Log::warning('Payment reconciled by the scheduled sweeper', [
                        'order' => $order->order_number,
                        'method' => $order->payment_method->value,
                    ]);

                    continue;
                }

                $this->line("  still unpaid: {$label}");
            } catch (\Throwable $e) {
                // Per order, so one unreachable invoice cannot abandon the rest of
                // the run — the next order may be the one holding real money.
                $failed++;
                $this->error("  could not check {$label}: {$e->getMessage()}");
                Log::error('Payment reconciliation failed', [
                    'order' => $order->order_number,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (! $this->option('dry-run')) {
            $this->info("Recovered {$recovered}, still unpaid ".($orders->count() - $recovered - $failed).", errors {$failed}.");
        }

        // ⚠️ Success even with per-order errors. A gateway being briefly unreachable
        // is the ordinary case for a scheduled job, and failing the run would turn
        // every blip into a scheduler alert nobody can act on. The log line above is
        // the durable record.
        return self::SUCCESS;
    }
}
