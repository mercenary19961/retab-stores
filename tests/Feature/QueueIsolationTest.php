<?php

namespace Tests\Feature;

use App\Jobs\SendWhatsappCampaign;
use App\Jobs\SendWhatsappMessage;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\WhatsappCampaign;
use App\Models\WhatsappTemplate;
use App\Services\WhatsApp\WhatsAppService;
use App\Support\Queues;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Marketing must never delay a transactional send.
 *
 * A campaign dispatches one SendWhatsappMessage per recipient, so on a single
 * shared queue a 5,000-person blast puts every order confirmation behind 5,000
 * marketing messages. These tests pin the split, which is otherwise completely
 * invisible: everything still "works" on one queue, just slowly and in the
 * wrong order, and nothing errors.
 */
class QueueIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.whatsapp.token' => 'test-token',
            'services.whatsapp.phone_number_id' => '123456',
        ]);
    }

    private function customer(): User
    {
        return User::forceCreate([
            'name' => 'عميل', 'email' => 'c'.uniqid().'@test.com',
            'phone' => '+9665'.random_int(10000000, 99999999),
            'password' => bcrypt('x'), 'role' => 'customer', 'whatsapp_opt_in' => true,
        ]);
    }

    private function order(User $user): Order
    {
        $category = Category::firstOrCreate(['slug' => 'dates'], ['name_ar' => 'التمور', 'is_active' => true]);
        Product::firstOrCreate(['slug' => 'q-test'], [
            'category_id' => $category->id, 'name_ar' => 'تمر', 'sku' => 'Q-1', 'price' => 10, 'stock' => 5, 'is_active' => true,
        ]);

        return Order::create([
            'order_number' => 'RTB-'.uniqid(),
            'user_id' => $user->id,
            'customer_name' => $user->name,
            'customer_phone' => $user->phone,
            'status' => 'awaiting_confirmation',
            'payment_status' => 'paid',
            'subtotal' => 10, 'shipping_fee' => 25, 'total' => 35,
            'shipping_address' => ['country' => 'SA', 'city' => 'Riyadh'],
            'locale' => 'ar',
        ]);
    }

    public function test_a_transactional_send_stays_on_the_default_queue(): void
    {
        Queue::fake();
        $user = $this->customer();

        app(WhatsAppService::class)->notifyOrderConfirmed($this->order($user));

        Queue::assertPushed(SendWhatsappMessage::class, function (SendWhatsappMessage $job) {
            // null means "the configured default" — deliberately not the literal
            // string 'default', so a changed DB_QUEUE cannot silently break this.
            return $job->queue === null;
        });
    }

    public function test_a_campaign_message_goes_to_the_bulk_queue(): void
    {
        Queue::fake();
        $user = $this->customer();

        $template = WhatsappTemplate::create([
            'name' => 'monthly_offer', 'language' => 'ar', 'category' => 'marketing',
            'body' => 'عرض الشهر: {{1}}', 'param_count' => 1, 'status' => 'approved',
        ]);
        $campaign = WhatsappCampaign::create([
            'whatsapp_template_id' => $template->id,
            'name' => 'August offers',
            'segment' => 'opted_in',
            'status' => 'sending',
            'params' => [],
        ]);

        app(WhatsAppService::class)->sendCampaignMessage($user, $campaign);

        Queue::assertPushed(SendWhatsappMessage::class, fn (SendWhatsappMessage $job) => $job->queue === Queues::BULK);
    }

    public function test_the_campaign_orchestrator_itself_is_on_the_bulk_queue(): void
    {
        Queue::fake();

        SendWhatsappCampaign::dispatch(1);

        // It walks the entire opt-in segment, so on the default queue it would
        // block transactional work for the whole run, not just its messages.
        Queue::assertPushed(SendWhatsappCampaign::class, fn (SendWhatsappCampaign $job) => $job->queue === Queues::BULK);
    }

    public function test_the_worker_list_serves_the_default_queue_before_bulk(): void
    {
        // 🔴 Order matters: Laravel re-checks the list after every job, so naming
        // the default first is what lets a transactional message jump ahead of a
        // running campaign. Reversed, every confirmation would wait for the blast.
        $this->assertSame('default,'.Queues::BULK, Queues::workerList());
        $this->assertStringStartsWith('default,', Queues::workerList());
    }

    public function test_an_otp_still_sends_inline_and_is_never_queued(): void
    {
        Queue::fake();

        // The customer is staring at the code field, and queueing it would also
        // put a plaintext code in the jobs table.
        app(WhatsAppService::class)->sendOtp('+966500000001', '123456');

        Queue::assertNothingPushed();
    }
}
