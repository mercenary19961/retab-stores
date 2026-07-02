<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Hosted-checkout URL (reused if the customer abandons and retries).
            $table->string('payment_url')->nullable()->after('gateway_reference');
            // When payment was captured (card at checkout; Tamara at confirmation).
            $table->timestamp('paid_at')->nullable()->after('payment_status');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['payment_url', 'paid_at']);
        });
    }
};
