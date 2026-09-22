<?php

namespace App\Console\Commands;

use App\Models\HeroSlide;
use App\Support\Media;
use Illuminate\Console\Command;

/**
 * Bring the four original hero slides into `hero_slides`, so the client can manage
 * them at /admin/hero like any other banner.
 *
 * 🔑 WHY: they were never deleted, only suspended. While a campaign is running the
 * storefront shows campaign banners and nothing else, so the four have been off
 * screen since National Day started. As real rows they can run alongside a
 * campaign (hero mode "Campaign, then mine") and be cropped, reordered and hidden
 * from the panel.
 *
 * 🔴 THE TRADE-OFF, ACCEPTED BY THE CLIENT: the plates are BARE PHOTOGRAPHS. The
 * headline, subtext and "Shop now" button are live HTML overlaid by the storefront,
 * and an admin slide has no such overlay — so these arrive as pictures with no
 * words on them. The originals keep working untouched as the fallback for when
 * nothing else is live; this only adds manageable copies.
 *
 * ⚠️ Run it ON THE WEB SERVICE in production: it writes to the media disk (R2), so
 * it needs those credentials.
 */
class ImportOriginalHeroSlides extends Command
{
    protected $signature = 'hero:import-original-slides {--dry-run : List what would be created and change nothing}';

    protected $description = 'Add the four built-in hero slides to the homepage banner manager';

    /**
     * The shipped plates, in the order the storefront rotates them.
     *
     * ⚠️ `alt` doubles as the IDEMPOTENCY KEY, because a stored filename is a fresh
     * UUID on every upload and there is no column recording where a row came from.
     * Editing a slide's English description in the panel therefore lets a re-run
     * create a duplicate; the dry run is there to check before that matters.
     */
    private const PLATES = [
        ['file' => 'slide-1.webp', 'mobile' => 'slide-1-mobile.webp', 'ar' => 'رجل يقدّم علبة تمر سكري في الصحراء عند الغروب', 'en' => 'A man offering a box of Sukkari dates in the desert at sunset'],
        ['file' => 'slide-2.webp', 'mobile' => null, 'ar' => 'علب تمر فاخرة مهيّأة كهدية', 'en' => 'Premium date boxes arranged as a gift'],
        ['file' => 'slide-3.webp', 'mobile' => null, 'ar' => 'توصيل التمور في جميع أنحاء المملكة', 'en' => 'Dates delivered across the Kingdom'],
        ['file' => 'slide-4.webp', 'mobile' => null, 'ar' => 'تمور معدّة لمناسبة خاصة', 'en' => 'Dates prepared for a special occasion'],
    ];

    private const DIR = 'hero';

    public function handle(): int
    {
        $base = public_path('images/hero');
        $dry = (bool) $this->option('dry-run');

        // Fail before uploading anything rather than halfway through.
        foreach (self::PLATES as $plate) {
            foreach (array_filter([$plate['file'], $plate['mobile']]) as $name) {
                if (! is_file("{$base}/{$name}")) {
                    $this->error("Missing artwork: images/hero/{$name}");

                    return self::FAILURE;
                }
            }
        }

        $created = 0;
        $skipped = 0;
        // Append after whatever is already there, so an existing slide keeps its place.
        $order = (int) HeroSlide::max('sort_order');

        foreach (self::PLATES as $plate) {
            if (HeroSlide::where('alt_en', $plate['en'])->exists()) {
                $this->line("  skip    {$plate['file']} (already added)");
                $skipped++;

                continue;
            }

            $this->line(($dry ? '  would  ' : '  add    ')."{$plate['file']}".($plate['mobile'] ? ' (+ phone art)' : ''));

            if ($dry) {
                $created++;

                continue;
            }

            HeroSlide::create([
                'kind' => 'image',
                'image' => Media::storeImageFromFile("{$base}/{$plate['file']}", $plate['file'], self::DIR),
                'image_mobile' => $plate['mobile']
                    ? Media::storeImageFromFile("{$base}/{$plate['mobile']}", $plate['mobile'], self::DIR)
                    : null,
                'alt_ar' => $plate['ar'],
                'alt_en' => $plate['en'],
                'is_active' => true,
                'sort_order' => ++$order,
                /*
                 * 🔑 Centred on purpose. The plates are 1440x800 (1.8:1) against a
                 * 2:1 band, so ~10% of the height is trimmed and the client asked to
                 * choose that themselves with the crop box.
                 */
                'focal_x' => 50,
                'focal_y' => 50,
                'focal_mobile_x' => 50,
                'focal_mobile_y' => 50,
            ]);

            $created++;
        }

        $this->newLine();
        $this->info($dry
            ? "Dry run: {$created} would be added, {$skipped} already present."
            : "Added {$created} slide(s), skipped {$skipped}.");

        if ($created > 0 && ! $dry) {
            $this->newLine();
            $this->warn('They will not show while a campaign is running unless the homepage banner');
            $this->warn('mode is set to "Campaign, then mine" at /admin/hero.');
        }

        return self::SUCCESS;
    }
}
