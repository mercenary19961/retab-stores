<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Audit + delivery tracking for every WhatsApp Cloud API message we send.
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('recipient', 20);                 // phone
            $table->string('template')->nullable();          // approved template name
            $table->string('category', 20)->nullable();      // utility | marketing | authentication
            $table->string('purpose', 30)->nullable();       // order_confirm | apology | loyalty | marketing | otp
            $table->string('status', 20)->default('queued'); // queued | sent | delivered | read | failed
            $table->string('wam_id')->nullable();            // WhatsApp message id (Cloud API)
            $table->json('payload')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index('recipient');
            $table->index('status');
            $table->index('wam_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
