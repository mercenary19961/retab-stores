<?php

namespace App\Models;

use App\Enums\Fulfillment;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperOrder
 */
class Order extends Model
{
    protected $fillable = [
        'order_number',
        'user_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'locale',
        // Checkout options: gift flag, delivery vs collection, a different person
        // receiving the parcel, and the buyer's company details.
        'is_gift',
        'fulfillment',
        'recipient_name',
        'recipient_phone',
        'company_name',
        'company_cr',
        'company_vat',
        'shipping_address',
        'status',
        'payment_status',
        'payment_method',
        'subtotal',
        'discount_total',
        'shipping_fee',
        'shipping_cost',
        'total',
        'currency',
        'coupon_id',
        'payment_gateway',
        'gateway_reference',
        'payment_url',
        'paid_at',
        'shipping_provider',
        'oto_id',
        'tracking_number',
        'carrier',
        'shipping_label_url',
        'admin_notes',
        'confirmed_at',
        'confirmed_by',
        'cancelled_at',
        'delivered_at',
        'review_reminder_sent_at',
    ];

    protected $casts = [
        'shipping_address' => 'array',
        'status' => OrderStatus::class,
        'payment_status' => PaymentStatus::class,
        'payment_method' => PaymentMethod::class,
        'is_gift' => 'boolean',
        'fulfillment' => Fulfillment::class,
        'subtotal' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'shipping_fee' => 'decimal:2',
        // What the carrier charged the store. Nullable: unknown for orders
        // shipped before this was recorded, and cleared when a shipment is
        // recalled (the figure survives on the activity entry).
        'shipping_cost' => 'decimal:2',
        'total' => 'decimal:2',
        'paid_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'delivered_at' => 'datetime',
        'review_reminder_sent_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Product names for an email subject: the line with the highest total leads
     * (usually what the order is "about"), and any other lines collapse into
     * "and N more" so the subject stays short. The body lists every item.
     *
     * Null when the order has no lines, so callers can fall back to the number.
     * Names come from the order-line SNAPSHOT, so a later product rename never
     * changes what an old email said.
     */
    public function itemsSummary(string $locale): ?string
    {
        $items = $this->loadMissing('items')->items;

        if ($items->isEmpty()) {
            return null;
        }

        // Sort by id first so a tie on line total keeps the earliest line (PHP's
        // sort is stable), which makes the choice deterministic.
        $lead = $items->sortBy('id')->sortByDesc(fn (OrderItem $item) => (float) $item->line_total)->first();

        $name = $locale === 'en'
            ? ($lead->product_name_en ?: $lead->product_name_ar)
            : ($lead->product_name_ar ?: $lead->product_name_en);

        $others = $items->count() - 1;

        return $others > 0
            ? $name.' '.trans_choice('emails.common.and_more', $others, ['count' => $others], $locale)
            : $name;
    }

    public function activities(): HasMany
    {
        return $this->hasMany(OrderActivity::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(OrderReturn::class);
    }

    /**
     * Is there a gateway payment still outstanding on this order?
     *
     * 🔑 Lives on the MODEL because two surfaces ask it — the order confirmation
     * page and the account order list — and the pay route enforces it. Three
     * copies of a money rule is how the admin panel ended up offering a Cancel
     * button in the one state it could never work (2026-08-15).
     *
     * Bank transfer is excluded deliberately: there is no gateway to return to,
     * and its IBAN instructions are already on the order page.
     */
    public function isAwaitingGatewayPayment(): bool
    {
        return in_array($this->payment_method, [PaymentMethod::Card, PaymentMethod::Tamara], true)
            && in_array($this->payment_status, [PaymentStatus::Pending, PaymentStatus::Failed], true)
            && $this->status === OrderStatus::PendingPayment;
    }

    /**
     * Is this a bank-transfer order still waiting for the money to arrive?
     *
     * The counterpart of isAwaitingGatewayPayment(). A transfer has no gateway to
     * tell us it landed, so a member of staff checks the bank account and records
     * it by hand. Matches the dashboard's "bank transfers to verify" count.
     */
    public function isAwaitingBankTransfer(): bool
    {
        return $this->payment_method === PaymentMethod::BankTransfer
            && $this->payment_status === PaymentStatus::Pending
            && $this->status === OrderStatus::PendingPayment;
    }

    /**
     * Who actually receives the parcel: the person named on the order when the
     * buyer nominated someone else, otherwise the buyer.
     *
     * 🔑 One definition, because at least four places need it and they must
     * agree — the carrier booking, the packing slip, the shipping WhatsApp
     * message and the admin order page. A buyer sending dates to their mother
     * should not have the courier phoning the buyer.
     *
     * @return array{name: string, phone: string}
     */
    public function recipient(): array
    {
        return filled($this->recipient_name)
            ? ['name' => $this->recipient_name, 'phone' => $this->recipient_phone ?: $this->customer_phone]
            : ['name' => $this->customer_name, 'phone' => $this->customer_phone];
    }

    /** Is someone other than the buyer receiving this order? */
    public function hasAlternateRecipient(): bool
    {
        return filled($this->recipient_name);
    }

    /** Collection orders have no carrier, no shipping fee and no address. */
    public function needsShipping(): bool
    {
        return ($this->fulfillment ?? Fulfillment::Delivery)->needsShipping();
    }

    /**
     * The gateway transaction ledger (authorisations, captures, voids, refunds).
     * Append-only: every service writes rows here and none are updated in place,
     * so the timestamps are a reliable record of WHEN money moved — which is how
     * the expiry alert knows when a Tamara hold actually started.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    // Coupon model arrives in batch 4; relationship is safe to define now.
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * Customer may cancel only before the admin confirms the order.
     */
    public function canBeCancelledByCustomer(): bool
    {
        return $this->status->isCancellableByCustomer();
    }

    /**
     * Within the return window (default 3 days from delivery) per the return policy.
     */
    public function isWithinReturnWindow(int $days = 3): bool
    {
        return $this->delivered_at !== null
            && $this->delivered_at->gte(now()->subDays($days));
    }
}
