<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('description_ar')->nullable();
            $table->string('description_en')->nullable();
            $table->string('type', 20);                 // percentage | fixed
            $table->decimal('value', 10, 2);            // 15 (percent) or amount in SAR
            $table->decimal('min_order_total', 10, 2)->nullable(); // min cart to apply
            $table->decimal('max_discount', 10, 2)->nullable();    // cap for percentage coupons
            $table->unsignedInteger('usage_limit')->nullable();    // total uses (null = unlimited)
            $table->unsignedInteger('used_count')->default(0);
            $table->unsignedInteger('per_user_limit')->nullable(); // uses per user (null = unlimited)
            $table->string('channel', 20)->default('online');      // online | in_store | both (in_store reserved for QR)
            $table->string('source', 20)->default('manual');       // manual | loyalty
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('is_active');
            $table->index(['channel', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
