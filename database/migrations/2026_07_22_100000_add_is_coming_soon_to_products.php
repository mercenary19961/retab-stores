<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // A hidden (is_active=false) product may still be surfaced on the store
            // as "Coming Soon": visible + describable, NOT buyable — customers can
            // register interest via the "I want this" request instead of buying.
            // Only meaningful while is_active is false; ignored when the product is
            // live. See Product::isComingSoon() / scopeVisibleOnStore().
            $table->boolean('is_coming_soon')->default(false)->after('is_featured');
            $table->index('is_coming_soon');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['is_coming_soon']);
            $table->dropColumn('is_coming_soon');
        });
    }
};
