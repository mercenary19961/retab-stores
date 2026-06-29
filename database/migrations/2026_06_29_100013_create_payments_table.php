<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-transaction payment ledger (authorize/capture/void/refund) behind the
        // PaymentGateway abstraction. The order keeps the overall payment_status.
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('gateway', 20);             // moyasar | tamara
            $table->string('type', 20);                // authorization | capture | void | refund
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('SAR');
            $table->string('status', 20)->default('pending'); // pending | succeeded | failed
            $table->string('gateway_transaction_id')->nullable();
            $table->json('raw')->nullable();           // raw gateway request/response
            $table->timestamps();

            $table->index('order_id');
            $table->index('gateway_transaction_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
