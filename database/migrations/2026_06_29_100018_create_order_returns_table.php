<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Defect/damage-only returns per the policy: request within 3 days of delivery
        // (enforced via orders.delivered_at), with photos; resolved by exchange or refund.
        Schema::create('order_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('requested')->index();
            $table->text('reason');                          // customer's description of the defect
            $table->json('photos')->nullable();              // paths to photos showing the problem
            $table->string('resolution', 20)->nullable();    // exchange | refund
            $table->decimal('refund_amount', 10, 2)->nullable();
            $table->boolean('refund_shipping')->default(false); // only true when dates arrived damaged
            $table->text('admin_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_returns');
    }
};
