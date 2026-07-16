<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReturnStatus;
use App\Models\DemandEvent;
use App\Models\Order;
use App\Models\OrderActivity;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\Product;
use App\Models\ReturnItem;
use App\Models\Setting;
use App\Models\User;
use App\Models\WhatsappCampaign;
use App\Models\WhatsappMessage;
use App\Models\WhatsappTemplate;
use App\Services\Smacc\SmaccImportService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * DEMO data for previewing the admin (dashboard + all pages). NOT wired into
 * DatabaseSeeder — run explicitly:  php artisan db:seed --class=DemoSeeder
 *
 * Destructive to transactional + demo tables (orders, returns, demand, campaigns,
 * and demo customers @demo.test) so it is safely re-runnable. Leaves the real
 * catalogue, settings, admin users, content pages and client reviews intact
 * (it seeds the base catalogue first if the store is empty).
 */
class DemoSeeder extends Seeder
{
    private const DEMO_DOMAIN = '@demo.test';

    public function run(): void
    {
        if (Product::count() === 0) {
            $this->call([SettingsSeeder::class, AdminUserSeeder::class, CatalogSeeder::class, ClientReviewSeeder::class, ContentPageSeeder::class]);
        }

        $this->clearDemoData();

        $admin = User::where('role', 'admin')->first() ?? User::where('role', '!=', 'customer')->first();
        $products = Product::where('is_active', true)->get();

        // A couple of products pushed low / out of stock so Inventory health lights up.
        $this->setStock($products, 'medjool-500g', 3);
        $this->setStock($products, 'stuffed-choc-500g', 4);
        $this->setStock($products, 'ramadan-gift', 0);

        $customers = $this->makeCustomers();
        $orders = $this->makeOrders($customers, $products, $admin?->id);
        $this->makeReturns($orders, $admin?->id);
        $this->makeDemand($products, $orders);
        $this->makeMarketing($customers, $admin?->id);

        // Stock last synced 4h ago → dashboard shows a fresh (non-stale) indicator.
        Setting::set(SmaccImportService::LAST_SYNCED_KEY, now()->subHours(4)->toIso8601String());

        $this->command?->info(sprintf(
            'Demo data seeded: %d customers, %d orders, %d products (2 low / 1 out).',
            $customers->count(),
            count($orders),
            $products->count(),
        ));
    }

    private function clearDemoData(): void
    {
        ReturnItem::query()->delete();
        OrderReturn::query()->delete();
        OrderActivity::query()->delete();
        OrderItem::query()->delete();
        DemandEvent::query()->delete();
        WhatsappMessage::query()->whereNotNull('campaign_id')->delete();
        WhatsappCampaign::query()->delete();
        WhatsappTemplate::query()->delete();
        Order::query()->delete();
        User::where('role', 'customer')->where('email', 'like', '%' . self::DEMO_DOMAIN)->forceDelete();
    }

    private function setStock($products, string $slug, int $stock): void
    {
        $products->firstWhere('slug', $slug)?->update(['stock' => $stock]);
    }

    /** @return \Illuminate\Support\Collection<int, User> */
    private function makeCustomers()
    {
        $names = [
            'محمد العتيبي', 'سارة القحطاني', 'عبدالله الشهري', 'نورة الدوسري', 'خالد الغامدي',
            'ريم الحربي', 'فيصل المطيري', 'لطيفة الزهراني', 'سلطان البقمي', 'هند العنزي',
            'ياسر السبيعي', 'أمل الشمري', 'ماجد الرشيدي', 'دانة الخالدي', 'تركي الحارثي',
            'جواهر الأحمدي', 'بندر الشيخ', 'شهد المالكي',
        ];
        $cities = ['الرياض', 'جدة', 'الدمام', 'مكة', 'المدينة', 'أبها', 'تبوك'];

        return collect($names)->values()->map(function (string $name, int $i) use ($cities) {
            // Distribution: a few near the 5-purchase reward (=4), a few past it.
            $confirmed = [0, 1, 2, 3, 4, 4, 4, 5, 6, 8][$i % 10];
            $optIn = $i % 2 === 0;
            $joined = now()->subDays(random_int(2, 120));

            $user = new User([
                'name' => $name,
                'email' => 'demo' . ($i + 1) . self::DEMO_DOMAIN,
                'password' => 'password', // 'hashed' cast hashes it
                'phone' => '+96650' . str_pad((string) (1000000 + $i), 7, '0', STR_PAD_LEFT),
                'role' => 'customer',
                'city' => $cities[$i % count($cities)],
                'locale' => 'ar',
                'whatsapp_opt_in' => $optIn,
                'whatsapp_opt_in_at' => $optIn ? $joined : null,
                'confirmed_purchases_count' => $confirmed,
                'phone_verified_at' => $optIn ? $joined : null,
            ]);
            $user->timestamps = false;
            $user->created_at = $joined;
            $user->updated_at = $joined;
            $user->save();

            return $user;
        });
    }

    /**
     * Orders spread across the last ~55 days so the KPI deltas + 30-day trend have
     * data, with statuses aged realistically. Also forces the action-queue cases.
     *
     * @return list<Order>
     */
    private function makeOrders($customers, $products, ?int $adminId): array
    {
        $orders = [];
        $seq = 1000;

        for ($day = 55; $day >= 0; $day--) {
            // Slightly heavier volume in the recent half.
            $count = $day <= 27 ? random_int(1, 4) : random_int(0, 3);

            for ($n = 0; $n < $count; $n++) {
                $placed = now()->subDays($day)->setTime(random_int(9, 21), random_int(0, 59));

                [$status, $payStatus] = $this->ageToStatus($day);
                $method = [PaymentMethod::Card, PaymentMethod::Card, PaymentMethod::Card, PaymentMethod::Tamara][random_int(0, 3)];

                $orders[] = $this->makeOrder($customers->random(), $products, ++$seq, $placed, $status, $payStatus, $method, $adminId);
            }
        }

        // Guarantee the "needs attention" queue is populated regardless of randomness.
        $orders[] = $this->makeOrder($customers->random(), $products, ++$seq, now()->subHours(6), OrderStatus::PendingPayment, PaymentStatus::Pending, PaymentMethod::BankTransfer, $adminId);
        $orders[] = $this->makeOrder($customers->random(), $products, ++$seq, now()->subHours(20), OrderStatus::PendingPayment, PaymentStatus::Pending, PaymentMethod::BankTransfer, $adminId);
        $orders[] = $this->makeOrder($customers->random(), $products, ++$seq, now()->subDays(2), OrderStatus::AwaitingConfirmation, PaymentStatus::Authorized, PaymentMethod::Tamara, $adminId);
        $orders[] = $this->makeOrder($customers->random(), $products, ++$seq, now()->subHours(30), OrderStatus::AwaitingConfirmation, PaymentStatus::Authorized, PaymentMethod::Tamara, $adminId);
        $orders[] = $this->makeOrder($customers->random(), $products, ++$seq, now()->subDay(), OrderStatus::Confirmed, PaymentStatus::Paid, PaymentMethod::Card, $adminId);
        $orders[] = $this->makeOrder($customers->random(), $products, ++$seq, now()->subHours(3), OrderStatus::AwaitingConfirmation, PaymentStatus::Paid, PaymentMethod::Card, $adminId);

        return $orders;
    }

    /** @return array{0: OrderStatus, 1: PaymentStatus} */
    private function ageToStatus(int $day): array
    {
        return match (true) {
            $day > 12 => [OrderStatus::Delivered, PaymentStatus::Paid],
            $day > 6 => [OrderStatus::Shipped, PaymentStatus::Paid],
            $day > 2 => [OrderStatus::Confirmed, PaymentStatus::Paid],
            default => random_int(0, 4) === 0
                ? [OrderStatus::PendingPayment, PaymentStatus::Pending]
                : [OrderStatus::AwaitingConfirmation, PaymentStatus::Paid],
        };
    }

    private function makeOrder(User $customer, $products, int $seq, Carbon $placed, OrderStatus $status, PaymentStatus $payStatus, PaymentMethod $method, ?int $adminId): Order
    {
        $lines = $products->random(random_int(1, 3));
        $shipping = 25.0;
        $subtotal = 0.0;

        $order = new Order([
            'order_number' => 'RTB-2026-' . $seq,
            'user_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_phone' => $customer->phone,
            'shipping_address' => ['country' => 'SA', 'city' => $customer->city, 'district' => 'حي النخيل', 'street' => 'شارع الأمير سلطان'],
            'status' => $status,
            'payment_status' => $payStatus,
            'payment_method' => $method,
            'subtotal' => 0,
            'shipping_fee' => $shipping,
            'total' => 0,
            'currency' => 'SAR',
            'payment_gateway' => $method === PaymentMethod::Tamara ? 'tamara' : ($method === PaymentMethod::Card ? 'moyasar' : null),
            'paid_at' => $payStatus === PaymentStatus::Paid ? $placed : null,
            'confirmed_at' => in_array($status, [OrderStatus::Confirmed, OrderStatus::Shipped, OrderStatus::Delivered], true) ? $placed->copy()->addHours(random_int(2, 20)) : null,
            'confirmed_by' => in_array($status, [OrderStatus::Confirmed, OrderStatus::Shipped, OrderStatus::Delivered], true) ? $adminId : null,
            'delivered_at' => $status === OrderStatus::Delivered ? $placed->copy()->addDays(random_int(1, 4)) : null,
            'tracking_number' => in_array($status, [OrderStatus::Shipped, OrderStatus::Delivered], true) ? 'OTO' . random_int(100000, 999999) : null,
            'carrier' => in_array($status, [OrderStatus::Shipped, OrderStatus::Delivered], true) ? 'SMSA' : null,
        ]);
        $order->timestamps = false;
        $order->created_at = $placed;
        $order->updated_at = $placed;
        $order->save();

        foreach ($lines as $p) {
            $qty = random_int(1, 4);
            $price = $p->effectivePrice();
            $subtotal += $price * $qty;
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $p->id,
                'product_name_ar' => $p->name_ar,
                'product_name_en' => $p->name_en,
                'sku' => $p->sku,
                'smacc_sku' => $p->smacc_sku,
                'unit_price' => $price,
                'quantity' => $qty,
                'line_total' => $price * $qty,
            ]);
        }

        $order->timestamps = false;
        $order->subtotal = $subtotal;
        $order->total = $subtotal + $shipping;
        $order->save();

        $this->logActivity($order, 'status_change', null, OrderStatus::PendingPayment->value, $placed, null);
        if ($payStatus === PaymentStatus::Paid || $payStatus === PaymentStatus::Authorized) {
            $this->logActivity($order, 'payment', null, null, $placed->copy()->addMinutes(2), null, 'Payment ' . $payStatus->value);
        }
        foreach ([OrderStatus::AwaitingConfirmation, OrderStatus::Confirmed, OrderStatus::Shipped, OrderStatus::Delivered] as $step) {
            if ($this->reached($status, $step)) {
                $this->logActivity($order, 'status_change', null, $step->value, $placed->copy()->addHours(random_int(3, 40)), $adminId);
            }
        }

        return $order;
    }

    private function reached(OrderStatus $current, OrderStatus $step): bool
    {
        $order = [
            OrderStatus::PendingPayment->value => 0,
            OrderStatus::AwaitingConfirmation->value => 1,
            OrderStatus::Confirmed->value => 2,
            OrderStatus::Shipped->value => 3,
            OrderStatus::Delivered->value => 4,
        ];

        return ($order[$current->value] ?? 0) >= ($order[$step->value] ?? 99);
    }

    private function logActivity(Order $order, string $type, ?string $from, ?string $to, Carbon $when, ?int $userId, ?string $note = null): void
    {
        $activity = new OrderActivity([
            'order_id' => $order->id,
            'type' => $type,
            'from_status' => $from,
            'to_status' => $to,
            'user_id' => $userId,
            'note' => $note,
        ]);
        $activity->timestamps = false;
        $activity->created_at = $when;
        $activity->save();
    }

    /** @param list<Order> $orders */
    private function makeReturns(array $orders, ?int $adminId): void
    {
        $delivered = collect($orders)->filter(fn (Order $o) => $o->status === OrderStatus::Delivered)->take(4)->values();
        $plan = [
            [ReturnStatus::Requested, null, null],
            [ReturnStatus::Requested, null, null],
            [ReturnStatus::Approved, null, null],
            [ReturnStatus::Refunded, 'refund', true],
        ];
        $reasons = [
            'وصلت بعض حبات التمر متكسرة والعبوة تالفة.',
            'المنتج مختلف عن الوصف، الجودة أقل من المتوقع.',
            'العبوة كانت مفتوحة عند الاستلام.',
            'تمور تالفة، رائحة غير طبيعية.',
        ];

        foreach ($delivered as $i => $order) {
            [$status, $resolution, $refundShipping] = $plan[$i];
            $item = $order->items()->first();

            $return = new OrderReturn([
                'order_id' => $order->id,
                'user_id' => $order->user_id,
                'status' => $status,
                'reason' => $reasons[$i],
                'resolution' => $resolution,
                'refund_amount' => $resolution === 'refund' ? (float) $order->subtotal : null,
                'refund_shipping' => (bool) $refundShipping,
                'resolved_at' => $status === ReturnStatus::Refunded ? now()->subDays(1) : null,
                'resolved_by' => $status === ReturnStatus::Refunded ? $adminId : null,
            ]);
            $created = $order->delivered_at?->copy()->addDay() ?? now()->subDays(2);
            $return->timestamps = false;
            $return->created_at = $created;
            $return->updated_at = $created;
            $return->save();

            if ($item) {
                ReturnItem::create([
                    'order_return_id' => $return->id,
                    'order_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'quantity' => 1,
                ]);
            }
        }
    }

    /**
     * "Wanted but unavailable" analytics — clustered on the out-of-stock product
     * plus a couple of others, spread over the last two months.
     *
     * @param list<Order> $orders
     */
    private function makeDemand($products, array $orders): void
    {
        $out = $products->firstWhere('slug', 'ramadan-gift');
        $others = $products->whereIn('slug', ['ajwa-1kg', 'luxury-box'])->values();
        $targets = collect([$out, $out, $out, $out, $out, $others->get(0), $others->get(0), $others->get(1)])->filter();

        foreach ($targets as $product) {
            $when = now()->subDays(random_int(1, 55));
            $event = new DemandEvent([
                'product_id' => $product->id,
                'customer_phone' => '+96650' . random_int(1000000, 9999999),
                'action' => ['apologized', 'suggested_alternatives'][random_int(0, 1)],
                'occurred_at' => $when,
            ]);
            $event->timestamps = false;
            $event->created_at = $when;
            $event->updated_at = $when;
            $event->save();
        }
    }

    private function makeMarketing($customers, ?int $adminId): void
    {
        $offer = WhatsappTemplate::create([
            'name' => 'monthly_offer', 'language' => 'ar', 'category' => 'marketing',
            'body' => 'عروض رطاب هذا الشهر 🌴 خصم {{1}}٪ على {{2}}. اطلب الآن!',
            'param_count' => 2, 'status' => 'approved',
        ]);
        WhatsappTemplate::create([
            'name' => 'order_confirmation', 'language' => 'ar', 'category' => 'utility',
            'body' => 'تم تأكيد طلبك رقم {{1}} ✅ المندوب في الطريق.',
            'param_count' => 1, 'status' => 'approved',
        ]);
        WhatsappTemplate::create([
            'name' => 'welcome_back', 'language' => 'ar', 'category' => 'marketing',
            'body' => 'اشتقنا لك! خصم خاص بانتظارك 🎁',
            'param_count' => 0, 'status' => 'draft',
        ]);

        $audience = $customers->where('whatsapp_opt_in', true)->count();
        $campaign = new WhatsappCampaign([
            'whatsapp_template_id' => $offer->id,
            'params' => ['15', 'تمور السكري'],
            'audience_count' => $audience,
            'status' => 'sent',
            'sent_by' => $adminId,
            'sent_at' => now()->subDays(6),
        ]);
        $sentAt = now()->subDays(6);
        $campaign->timestamps = false;
        $campaign->created_at = $sentAt;
        $campaign->updated_at = $sentAt;
        $campaign->save();
    }
}
