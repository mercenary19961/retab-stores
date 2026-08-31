<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The store's own register of shipping carriers, backing /admin/shipping.
     *
     * 🔑 Why a local table at all, when OTO already knows every carrier: OTO's API
     * can LIST carriers and ACTIVATE one (dcList / dcConfig / dcActivation), but it
     * exposes no deactivation endpoint and no "is this on?" read — and activation
     * there means "attach my own carrier contract to OTO", which is a credentials
     * job for their dashboard, not something to drive from this panel.
     *
     * So the two halves of the portal come from different places and this table is
     * the half that is ours: whether Retab will QUOTE and SHIP with a carrier, plus
     * the contact details a human needs when a parcel goes wrong. Live availability,
     * prices and delivery times are read from OTO at request time and never stored,
     * because a cached price is a wrong price.
     *
     * The enable flag is load-bearing, not decorative: OtoGateway::getDeliveryOptions
     * filters against it, so switching a carrier off here actually removes it from
     * the Ship picker and from the automatic cheapest-carrier choice.
     */
    public function up(): void
    {
        Schema::create('shipping_carriers', function (Blueprint $table) {
            $table->id();

            // Normalised match key (see ShippingCarrier::normalizeKey), NOT a slug of
            // the display name. OTO reports the same courier under slightly different
            // names on different endpoints ("SMSA" vs "SMSA Express"), and a key that
            // drifted with the name would silently re-enable a carrier the client had
            // switched off.
            $table->string('key', 64)->unique();

            $table->string('name');
            $table->string('name_ar')->nullable();

            // The one field that changes behaviour. Defaults to true so a carrier
            // discovered on a later refresh is usable immediately — an unexpected
            // courier on a label is a far smaller problem than an order that cannot
            // be shipped at all.
            $table->boolean('is_enabled')->default(true);

            // Contact card. All nullable and all admin-editable: none of it comes
            // from OTO, and a wrong support number is worse than a blank one.
            $table->string('website_url')->nullable();
            $table->string('support_phone', 32)->nullable();
            $table->string('support_email')->nullable();
            $table->string('support_url')->nullable();
            // Public parcel-tracking page. `{tracking}` is substituted with the
            // order's tracking number to build a one-click link.
            $table->string('tracking_url')->nullable();
            // Deep link to this carrier inside the OTO dashboard. Blank by default:
            // OTO's per-carrier URLs are account-specific, so the admin pastes the
            // real one once rather than us guessing a path that 404s.
            $table->string('oto_url')->nullable();

            $table->text('notes')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);

            // Last time OTO listed this carrier as available. Lets the portal show
            // "OTO is no longer offering this" without deleting a row that still
            // carries the store's own contact details.
            $table->timestamp('last_seen_at')->nullable();

            $table->timestamps();

            $table->index(['is_enabled', 'sort_order']);
        });

        // 🔴 Seed the two exclusions the business already decided on, or this
        // migration would REGRESS a live rule. OtoGateway used to drop Aramex with a
        // hardcoded name check; that check is now this table, and an empty table
        // fails open — so without these rows Aramex would start being quoted again
        // the moment this ships.
        //
        // DHL is here because the same decision named it ("GCC only. No Aramex, no
        // DHL") and the old hardcoded check never actually implemented that half, so
        // DHL could win the automatic cheapest-carrier pick. This is the point where
        // the recorded decision and the running code finally agree.
        //
        // Written with the query builder, not the model: ShippingCarrier::saved
        // clears a cache entry, and the cache table may not exist yet at this point
        // in a fresh migration run.
        $now = now();
        DB::table('shipping_carriers')->insert([
            [
                'key' => 'aramex',
                'name' => 'Aramex',
                'name_ar' => 'أرامكس',
                'is_enabled' => false,
                'website_url' => 'https://www.aramex.com',
                'tracking_url' => 'https://www.aramex.com/track/shipments?ShipmentNumber={tracking}',
                'notes' => 'Excluded by a business decision, not by a fault. Switch on here if that changes.',
                'sort_order' => 90,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'dhl',
                'name' => 'DHL',
                'name_ar' => 'دي إتش إل',
                'is_enabled' => false,
                'website_url' => 'https://www.dhl.com/sa-en/home.html',
                'tracking_url' => 'https://www.dhl.com/sa-en/home/tracking.html?tracking-id={tracking}',
                'notes' => 'Excluded by a business decision, not by a fault. Switch on here if that changes.',
                'sort_order' => 91,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_carriers');
    }
};
