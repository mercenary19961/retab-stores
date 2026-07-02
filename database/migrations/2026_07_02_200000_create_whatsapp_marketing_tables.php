<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Local registry of Meta message templates. Templates are authored +
        // approved in Meta Business Manager; this table mirrors their name/
        // language/body so admins can pick them and we can validate params.
        // `status` is synced MANUALLY for now (no WABA management API creds).
        Schema::create('whatsapp_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);                       // Meta template name (snake_case)
            $table->string('language', 10)->default('ar');
            $table->string('category', 20)->default('marketing'); // marketing | utility
            $table->text('body');                              // preview with {{1}} placeholders
            $table->unsignedTinyInteger('param_count')->default(0);
            $table->string('status', 20)->default('draft')->index(); // draft|pending|approved|rejected
            $table->timestamps();

            $table->unique(['name', 'language']);
        });

        // One outbound blast to the opt-in segment. Delivery stats live on the
        // whatsapp_messages ledger rows (campaign_id below).
        Schema::create('whatsapp_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_template_id')->constrained()->restrictOnDelete();
            $table->json('params')->nullable();                // filled template variables
            $table->unsignedInteger('audience_count')->default(0);
            $table->string('status', 20)->default('draft')->index(); // draft|sending|sent
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->foreignId('campaign_id')->nullable()->after('order_id')
                ->constrained('whatsapp_campaigns')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('campaign_id');
        });
        Schema::dropIfExists('whatsapp_campaigns');
        Schema::dropIfExists('whatsapp_templates');
    }
};
