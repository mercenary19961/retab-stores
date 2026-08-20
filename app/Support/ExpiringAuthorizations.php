<?php

namespace App\Support;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionType;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Tamara authorisations approaching expiry — the single definition, shared by
 * the dashboard counter and the scheduled alert.
 *
 * 🔑 Why this exists: the two used to compute it differently. The dashboard
 * dated the hold from `orders.created_at` at a hardcoded 24h, while
 * `payments:alert-expiring` dated it from the AUTHORIZATION ledger row at a
 * config-driven threshold. So the number an admin read on the dashboard was a
 * different set of orders from the one that actually got alerted — on the one
 * item where being wrong means a sale quietly stops being collectable.
 *
 * ⚠️ The hold is dated from the ledger row, NOT the order. They are usually
 * minutes apart, but an order paid through the resume-payment route is
 * authorised long after it was placed; dating that from `created_at` would
 * flag it far too early, or — the dangerous direction — only after the hold
 * had already lapsed.
 */
class ExpiringAuthorizations
{
    /**
     * Orders still holding a Tamara authorisation and still waiting on a human.
     *
     * Deliberately NOT filtered by `payment_expiry_alerted_at`: that stamp exists
     * so the hourly command does not re-notify, and an order that was alerted
     * about but still not confirmed very much still needs attention. The command
     * adds that constraint itself.
     */
    public static function query(): Builder
    {
        return Order::where('payment_method', PaymentMethod::Tamara)
            ->where('payment_status', PaymentStatus::Authorized)
            ->where('status', OrderStatus::AwaitingConfirmation)
            ->with(['payments' => fn ($q) => $q
                ->where('type', PaymentTransactionType::Authorization->value)
                ->where('status', 'authorized')
                ->latest(),
            ]);
    }

    /**
     * Those whose hold is close enough to expiry to warrant acting on.
     *
     * @return Collection<int, Order>
     */
    public static function approaching(?Builder $query = null): Collection
    {
        $cutoff = now()->subHours(self::alertAfterHours());

        return ($query ?? self::query())
            ->get()
            ->filter(fn (Order $order) => self::authorizedAt($order)->lte($cutoff))
            ->values();
    }

    /** How long after authorisation an order becomes worth flagging. */
    public static function alertAfterHours(): int
    {
        return max(self::authorizationHours() - self::warningHours(), 1);
    }

    public static function authorizationHours(): int
    {
        return (int) config('services.tamara.authorization_hours', 48);
    }

    public static function warningHours(): int
    {
        return (int) config('services.tamara.expiry_warning_hours', 12);
    }

    /** When the hold was actually taken. Falls back to the order only if no ledger row exists. */
    public static function authorizedAt(Order $order): Carbon
    {
        return $order->payments->first()?->created_at ?? $order->created_at;
    }

    /**
     * Whole hours left on the hold. Rounded DOWN, so the figure a human reads is
     * never more optimistic than reality.
     */
    public static function hoursLeft(Order $order): int
    {
        return max((int) floor(self::authorizationHours() - self::authorizedAt($order)->diffInHours(now())), 0);
    }
}
