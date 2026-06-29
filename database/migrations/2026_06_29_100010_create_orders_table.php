<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Customer snapshot — immutable record of who ordered (also covers checkout
            // before profile completion).
            $table->string('customer_name');
            $table->string('customer_email')->nullable();
            $table->string('customer_phone');
            $table->json('shipping_address'); // GCC-format snapshot at order time

            // Fulfillment + payment state (cast to enums on the model).
            $table->string('status')->default('pending_payment')->index();
            $table->string('payment_status')->default('pending')->index();
            $table->string('payment_method')->nullable(); // card | tamara

            // Money (SAR). shipping_fee = the single flat GCC rate.
            $table->decimal('subtotal', 10, 2);
            $table->decimal('discount_total', 10, 2)->default(0);
            $table->decimal('shipping_fee', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->string('currency', 3)->default('SAR');

            // FK to coupons added in the coupons migration (batch 4) — column only for now.
            $table->foreignId('coupon_id')->nullable();

            // Payment gateway refs.
            $table->string('payment_gateway')->nullable();   // moyasar | tamara
            $table->string('gateway_reference')->nullable();  // gateway txn / order id

            // Shipping / OTO.
            $table->string('shipping_provider')->nullable();  // oto
            $table->string('oto_id')->nullable();
            $table->string('tracking_number')->nullable();
            $table->string('carrier')->nullable();
            $table->string('shipping_label_url')->nullable();

            $table->text('admin_notes')->nullable();

            // Lifecycle timestamps.
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('delivered_at')->nullable(); // starts the 3-day return window

            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
