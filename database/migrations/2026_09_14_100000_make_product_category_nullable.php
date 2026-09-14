<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deleting a category no longer deletes its products.
 *
 * 🔴 The original foreign key was `cascadeOnDelete`, so removing a category took
 * every product filed under it (soft-deleted ones included) with it. Now a
 * product can simply have NO category: it stays on sale and is found by search
 * and in the unfiltered catalogue, and the admin can file it again later
 * ("No category" filter on the Products list).
 *
 * Admin\CategoryController moves or clears the products itself before deleting,
 * so the null-on-delete here is the safety net for any other delete path
 * (tinker, a seeder), not the primary mechanism.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id')->nullable()->change();
            $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Restores the cascade, but deliberately leaves the column nullable:
        // making it NOT NULL again would fail on any product already left
        // without a category, and silently picking one for them is worse.
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreign('category_id')->references('id')->on('categories')->cascadeOnDelete();
        });
    }
};
