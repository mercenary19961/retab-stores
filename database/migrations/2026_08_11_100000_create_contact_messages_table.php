<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The Contact Us page's "أرسل رسالتك" form (contact-submit.tsx). Every
        // submitter — guest or signed-in — fills the same fields, so this deliberately
        // does NOT carry a user_id: a signed-in customer's message is not "theirs" in
        // the way an order or a review is, it is a one-off enquiry addressed to staff.
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('phone', 20);
            // Free string, not an enum: the option set is store copy (see
            // contact.form.inquiryTypes in i18n) and may grow without a migration.
            $table->string('inquiry_type');
            $table->text('message');
            $table->string('ip', 45)->nullable();           // abuse / rate context
            $table->timestamp('handled_at')->nullable();    // staff marked it dealt with
            $table->timestamps();

            $table->index('handled_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};
