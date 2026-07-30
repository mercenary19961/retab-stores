<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Jobs\SendWhatsappMessage;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductRequest;
use App\Models\User;
use App\Models\WhatsappMessage;
use App\Notifications\NewOrderNotification;
use App\Notifications\ProductRequestedNotification;
use App\Services\WhatsApp\WhatsAppGateway;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Delivery channels for staff alerts: the emailed copy alongside the bell row,
 * and the queued WhatsApp transport. (The bell's own read/scope behaviour lives
 * in NotificationBellTest.)
 */
class AdminAlertChannelsTest extends TestCase
{
    use RefreshDatabase;

    private function staff(array $overrides = []): User
    {
        return User::forceCreate(array_merge([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('secret'),
            'role' => 'admin',
        ], $overrides));
    }

    private function order(): Order
    {
        return Order::create([
            'order_number' => 'RTB-'.uniqid(),
            'customer_name' => 'Zaid',
            'customer_phone' => '+966500000000',
            'shipping_address' => ['country' => 'SA'],
            'status' => OrderStatus::AwaitingConfirmation,
            'payment_status' => PaymentStatus::Paid,
            'payment_method' => PaymentMethod::Card,
            'subtotal' => 100,
            'total' => 175.5,
        ]);
    }

    /**
     * Messages collected by the `array` mail transport the test env uses.
     *
     * The two annotations are the documented Intelephense workaround for a
     * framework docblock gap (see CLAUDE.md → Local Development): the Mail facade
     * advertises the Mailer *contract*, which has no getSymfonyTransport(), and
     * that in turn returns a TransportInterface rather than the ArrayTransport
     * that actually carries messages(). Runtime is unaffected.
     */
    private function sentMail(): Collection
    {
        /** @var \Illuminate\Mail\Mailer $mailer */
        $mailer = Mail::mailer();

        /** @var \Illuminate\Mail\Transport\ArrayTransport $transport */
        $transport = $mailer->getSymfonyTransport();

        return $transport->messages();
    }

    public function test_new_order_emails_staff_alongside_the_bell_row(): void
    {
        $admin = $this->staff();
        $order = $this->order();

        $admin->notify(new NewOrderNotification($order));

        // Bell row is still written (the database channel is pinned to `sync`).
        $this->assertSame(1, $admin->notifications()->count());

        $mail = $this->sentMail();
        $this->assertCount(1, $mail);

        $message = $mail->first()->getOriginalMessage();
        $this->assertStringContainsString($order->order_number, $message->getSubject());
        $this->assertSame('admin@test.com', $message->getTo()[0]->getAddress());
        // The body carries the total and a deep link into the admin panel.
        $this->assertStringContainsString('175.50', $message->toString());
        $this->assertStringContainsString("/admin/orders/{$order->order_number}", $message->toString());
    }

    public function test_staff_without_an_email_still_get_the_bell_row_and_no_mail(): void
    {
        // `users.email` is nullable under the OTP identity model — a phone-only
        // staff account must not blow up the mail transport.
        $admin = $this->staff(['name' => 'Phone Only', 'email' => null, 'phone' => '+966500000001']);

        $admin->notify(new NewOrderNotification($this->order()));

        $this->assertSame(1, $admin->notifications()->count());
        $this->assertCount(0, $this->sentMail());
    }

    public function test_product_request_alert_renders_its_email(): void
    {
        // Guards against a toMail() that only breaks in production.
        $admin = $this->staff();
        $product = Product::create([
            'category_id' => Category::firstOrCreate(['slug' => 'dates'], ['name_ar' => 'التمور', 'is_active' => true])->id,
            'name_ar' => 'سكري فاخر',
            'slug' => 'sukkari-premium-'.uniqid(),
            'sku' => 'CS-'.uniqid(),
            'price' => 50,
            'stock' => 0,
            'is_active' => false,
            'is_coming_soon' => true,
        ]);
        $request = ProductRequest::create([
            'product_id' => $product->id,
            'phone' => '+966500000002',
            'ip' => '127.0.0.1',
        ]);

        $admin->notify(new ProductRequestedNotification($request));

        $mail = $this->sentMail();
        $this->assertCount(1, $mail);
        $this->assertStringContainsString('coming-soon', $mail->first()->getOriginalMessage()->getSubject());
        // Decoded: the body is quoted-printable, so raw Arabic bytes aren't in toString().
        $this->assertStringContainsString('سكري فاخر', quoted_printable_decode($mail->first()->getOriginalMessage()->toString()));
    }

    public function test_transactional_whatsapp_is_queued_off_the_request(): void
    {
        Queue::fake();

        $message = app(WhatsAppService::class)->notifyOrderConfirmed($this->order());

        // Ledger row exists immediately, but the Meta call is deferred.
        $this->assertSame('queued', $message->status);
        $this->assertNull($message->wam_id);
        Queue::assertPushed(SendWhatsappMessage::class, fn ($job) => $job->messageId === $message->id);
    }

    public function test_otp_is_sent_inline_so_the_customer_is_not_left_waiting(): void
    {
        Queue::fake();

        $message = app(WhatsAppService::class)->sendOtp('+966500000003', '123456');

        $this->assertSame('sent', $message->status);
        Queue::assertNotPushed(SendWhatsappMessage::class);
        // The code reached WhatsApp but is redacted in the ledger, and never
        // enters the jobs table because this path bypasses the queue.
        $this->assertSame(['***'], $message->payload['params']);
    }

    public function test_the_job_marks_the_row_failed_when_the_transport_throws(): void
    {
        $this->app->bind(WhatsAppGateway::class, fn () => new class implements WhatsAppGateway
        {
            public function sendTemplate(string $to, string $template, string $language, array $params = []): string
            {
                throw new \RuntimeException('network down');
            }

            public function sendText(string $to, string $body): string
            {
                throw new \RuntimeException('network down');
            }
        });

        $row = WhatsappMessage::create([
            'recipient' => '966500000000',
            'template' => 'order_confirmed',
            'category' => 'utility',
            'purpose' => 'order_confirm',
            'status' => 'queued',
            'payload' => ['language' => 'ar', 'params' => ['Zaid']],
        ]);

        app()->call([new SendWhatsappMessage($row->id, ['Zaid']), 'handle']);

        $row->refresh();
        $this->assertSame('failed', $row->status);
        $this->assertStringContainsString('network down', (string) $row->error);
    }

    public function test_the_job_never_sends_the_same_message_twice(): void
    {
        $row = WhatsappMessage::create([
            'recipient' => '966500000000',
            'template' => 'order_confirmed',
            'category' => 'utility',
            'purpose' => 'order_confirm',
            'status' => 'sent',
            'wam_id' => 'wamid.original',
            'payload' => ['language' => 'ar', 'params' => []],
        ]);

        app()->call([new SendWhatsappMessage($row->id, []), 'handle']);

        // A retry after a send that actually landed is a no-op.
        $this->assertSame('wamid.original', $row->refresh()->wam_id);
    }
}
