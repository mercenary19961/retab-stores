<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Curated store-level testimonials (mostly imported from Google Maps
        // reviews). The admin builds the pool; the homepage "آراء العملاء" section
        // shows a random handful per request. Distinct from per-product `reviews`.
        Schema::create('client_reviews', function (Blueprint $table) {
            $table->id();
            $table->string('author_name');
            $table->text('body');
            $table->unsignedTinyInteger('rating')->default(5); // 1..5
            $table->string('language', 2)->nullable();          // ar | en, as written
            $table->string('source', 20)->default('google');    // google | manual
            $table->boolean('is_active')->default(true);         // in the display pool
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_reviews');
    }
};
