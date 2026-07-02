<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Loyalty milestones reached by a customer (e.g. 5 confirmed purchases → 15%
        // coupon). One reward per (user, type, threshold) so it's issued only once.
        Schema::create('loyalty_rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30)->default('purchase_milestone');
            $table->unsignedInteger('threshold');               // e.g. 5 purchases
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete(); // issued reward coupon
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('notified_at')->nullable();       // WhatsApp sent
            $table->timestamps();

            $table->unique(['user_id', 'type', 'threshold']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_rewards');
    }
};
