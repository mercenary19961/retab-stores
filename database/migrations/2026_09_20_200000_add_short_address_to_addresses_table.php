<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Saudi National Address short code (العنوان الوطني المختصر), e.g. RRMD7708.
 *
 * 🔑 Chosen over a map pin because it is what Saudi couriers already work from —
 * OTO records our own shop as RRMD7708 — so it needs no maps provider, no API
 * key and no billing account, and it survives being read aloud over the phone.
 *
 * ⚠️ Nullable, and it must stay that way. Plenty of customers do not know theirs,
 * and an address that cannot be saved without one would be worse than a slightly
 * vaguer address: the typed district/street already gets the parcel delivered.
 *
 * 8 characters: four letters then four digits. Stored uppercase.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->string('short_address', 8)->nullable()->after('postal_code');
        });
    }

    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->dropColumn('short_address');
        });
    }
};
