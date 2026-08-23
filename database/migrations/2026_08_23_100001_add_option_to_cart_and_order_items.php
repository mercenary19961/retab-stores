<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wire the chosen product option into carts and orders.
 *
 * cart_items: a nullable option FK. The old unique(cart_id, product_id) is
 * dropped so the same product can sit in the cart under different options;
 * CartService enforces one line per (cart, product, option) instead — a DB
 * unique can't, because MySQL treats each NULL (a no-option product) as distinct.
 *
 * order_items: the option FK plus a LABEL SNAPSHOT, so a receipt still reads
 * "500 جرام" even after the option is renamed or deleted (order_items already
 * snapshots the product name + price for exactly this reason).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            // The cart_id foreign key relies on the composite unique's leading
            // column, so give it its own index before the unique is dropped
            // (MariaDB refuses to drop an index a FK still needs).
            $table->index('cart_id', 'cart_items_cart_id_index');
            $table->dropUnique(['cart_id', 'product_id']);
            $table->foreignId('product_option_id')->nullable()->after('product_id')
                ->constrained()->nullOnDelete();
            $table->index(['cart_id', 'product_id', 'product_option_id']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('product_option_id')->nullable()->after('product_id')
                ->constrained()->nullOnDelete();
            $table->string('option_label_ar')->nullable()->after('product_name_en');
            $table->string('option_label_en')->nullable()->after('option_label_ar');
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropIndex(['cart_id', 'product_id', 'product_option_id']);
            $table->dropConstrainedForeignId('product_option_id');
            $table->unique(['cart_id', 'product_id']);
            $table->dropIndex('cart_items_cart_id_index');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_option_id');
            $table->dropColumn(['option_label_ar', 'option_label_en']);
        });
    }
};
