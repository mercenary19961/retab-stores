<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();

            // Bilingual, AR-first (EN optional, falls back to AR). Weight/size live
            // inside these descriptive fields per product — NOT a structured column.
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->string('slug', 191)->unique();
            $table->text('description_ar')->nullable();
            $table->text('description_en')->nullable();
            $table->string('short_description_ar', 500)->nullable();
            $table->string('short_description_en', 500)->nullable();

            $table->decimal('price', 10, 2);
            $table->decimal('sale_price', 10, 2)->nullable(); // active discounted price when set

            $table->string('sku', 100)->unique();
            $table->string('smacc_sku', 100)->nullable()->unique(); // SMACC Excel-import mapping key
            $table->string('barcode', 100)->nullable();

            // Quantity-based inventory (no variants — 1 product = 1 SMACC SKU).
            $table->integer('stock')->default(0);
            $table->unsignedInteger('low_stock_threshold')->nullable(); // null = use global setting

            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
            $table->index('is_featured');
            $table->index(['category_id', 'is_active']);
            $table->index(['category_id', 'price']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
