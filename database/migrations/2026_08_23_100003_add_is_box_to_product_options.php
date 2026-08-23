<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mark an option as the product's BOX (packaging), as opposed to a weight size.
 *
 * A box is priced by hand, its size is optional, and a product may have at most
 * ONE. Persisting the flag (rather than inferring it from a null amount) keeps
 * "only one box" correct after a save/reload and lets a box be told apart from a
 * custom weight even when both happen to have no grams.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_options', function (Blueprint $table) {
            $table->boolean('is_box')->default(false)->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('product_options', function (Blueprint $table) {
            $table->dropColumn('is_box');
        });
    }
};
