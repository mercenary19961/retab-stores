<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 301 redirect map for launch: old (Zid) product slugs that Google has indexed
     * point here so they don't 404 after the slug cleanup. The `/products/{slug}`
     * route's ->missing() handler consults this table (see RedirectController).
     *
     * Targets a product (so the redirect always follows the product's CURRENT slug,
     * even if it changes again) OR a static `to_url` for non-product legacy URLs the
     * admin may add later. `from_slug` is the bare slug matched under /products/.
     */
    public function up(): void
    {
        Schema::create('redirects', function (Blueprint $table) {
            $table->id();
            $table->string('from_slug', 191)->unique();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('to_url', 500)->nullable();
            $table->unsignedSmallInteger('status')->default(301);
            $table->unsignedInteger('hits')->default(0); // times served — cheap usage signal
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redirects');
    }
};
