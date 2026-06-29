<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Multi-method identity: a user may sign up with only a phone (WhatsApp)
            // or only Google, so name/email/password are no longer required.
            $table->string('name')->nullable()->change();
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();

            $table->string('phone', 20)->nullable()->unique()->after('email');
            $table->timestamp('phone_verified_at')->nullable()->after('email_verified_at');

            $table->string('role')->default('customer')->after('password'); // customer | editor | admin
            $table->string('avatar')->nullable()->after('role');
            $table->string('city')->nullable()->after('avatar');             // profile (where they live)
            $table->string('locale', 5)->default('ar')->after('city');       // storefront language (AR-first)
            $table->string('admin_theme', 10)->default('light')->after('locale'); // admin UI: light | dark

            $table->boolean('whatsapp_opt_in')->default(false)->after('admin_theme');
            $table->timestamp('whatsapp_opt_in_at')->nullable()->after('whatsapp_opt_in');

            $table->unsignedInteger('confirmed_purchases_count')->default(0)->after('whatsapp_opt_in_at');

            $table->softDeletes();

            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone']);
            $table->dropIndex(['role']);
            $table->dropSoftDeletes();
            $table->dropColumn([
                'phone', 'phone_verified_at', 'role', 'avatar', 'city',
                'locale', 'admin_theme', 'whatsapp_opt_in', 'whatsapp_opt_in_at',
                'confirmed_purchases_count',
            ]);

            // Best-effort revert of nullability.
            $table->string('name')->nullable(false)->change();
            $table->string('email')->nullable(false)->change();
            $table->string('password')->nullable(false)->change();
        });
    }
};
