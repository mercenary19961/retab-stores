<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "I want this" demand signals for Coming-Soon products: a customer taps the
        // request button so the store knows to prioritise stocking / follow up on
        // WhatsApp. A logged-in user's row carries user_id; a guest's carries phone.
        Schema::create('product_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phone', 20)->nullable();      // guest contact (WhatsApp follow-up)
            $table->string('ip', 45)->nullable();          // abuse / rate context
            $table->timestamp('handled_at')->nullable();   // admin marked it dealt with
            $table->timestamps();

            $table->index(['product_id', 'created_at']);
            $table->index('handled_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_requests');
    }
};
