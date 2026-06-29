<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label')->nullable();      // e.g. "Home", "Work"
            $table->string('country', 2);             // GCC ISO code: SA, AE, KW, QA, BH, OM
            $table->string('city');
            $table->string('district')->nullable();
            $table->string('street')->nullable();
            $table->string('building')->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('phone', 20)->nullable();  // contact phone for this address
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
