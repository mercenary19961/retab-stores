<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Checkout options the client asked for: gift orders, collection from the shop,
 * a different person receiving the parcel, and buying as a company.
 *
 * One migration for all four because they land on the same table and the same
 * checkout form — four separate ones would mean four passes over `orders` for a
 * single feature set.
 *
 * ⚠️ `company_vat` records the BUYER's VAT number, not the store's. The store's
 * own VAT number and commercial registration live in `settings`, are shown in
 * the footer, and are a different thing entirely.
 *
 * ⚠️ Deliberately NOT added here: any VAT rate or tax amount. The app has never
 * modelled tax, the live Zid store displays VAT at 0% while the business is
 * VAT-registered, and SMACC (the client's POS) is itself an e-invoicing
 * platform — so which system issues the fiscal document is an accounting
 * decision, not one to encode on a guess.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // A flag for staff, nothing more: it tells whoever packs the box to
            // treat it as a gift. No pricing, messaging or routing changes.
            $table->boolean('is_gift')->default(false)->after('locale');

            // How the customer gets the order. 'collection' means they come to the
            // shop, so there is no carrier and no shipping fee.
            //
            // Called collection rather than pickup on purpose: `PickupPoint` already
            // means one of OTO's PUDO counters, which is a different thing — a
            // courier still carries the parcel there.
            $table->string('fulfillment', 20)->default('delivery')->after('is_gift');

            // Someone other than the buyer receiving the parcel. Null means the
            // buyer receives it themselves, so nothing reads these unless set.
            $table->string('recipient_name')->nullable()->after('customer_phone');
            $table->string('recipient_phone', 20)->nullable()->after('recipient_name');

            // Buying as a company. Captured for the buyer's records; whether the
            // store issues them a tax invoice is the open question above.
            $table->string('company_name')->nullable()->after('recipient_phone');
            $table->string('company_cr', 32)->nullable()->after('company_name');
            $table->string('company_vat', 32)->nullable()->after('company_cr');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'is_gift',
                'fulfillment',
                'recipient_name',
                'recipient_phone',
                'company_name',
                'company_cr',
                'company_vat',
            ]);
        });
    }
};
