<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;

/**
 * Generates responsive WebP variants (thumb / card / detail — see config/media.php)
 * for every image stored through Media, so listings load small files instead of
 * full-resolution originals. Variants live next to the original on the media disk
 * with a "-<variant>.webp" suffix and are derived by convention (never stored in
 * the DB), so a URL is computed, not looked up. The same libwebp/GD engine as
 * Squoosh, just automated. All generation is best-effort: a failure logs and moves
 * on, never breaking an upload.
 */
class ImageVariants
{
    /** @return list<string> the configured variant names (thumb/card/detail). */
    public static function names(): array
    {
        return array_keys((array) config('media.variants', []));
    }

    public static function enabled(): bool
    {
        return (bool) config('media.variants_enabled', true);
    }

    /**
     * The conventional stored path of a variant, derived from the original:
     * products/1/uuid.jpg → products/1/uuid-card.webp
     */
    public static function variantPath(string $originalPath, string $variant): string
    {
        $dir = pathinfo($originalPath, PATHINFO_DIRNAME);
        $name = pathinfo($originalPath, PATHINFO_FILENAME);
        $prefix = ($dir && $dir !== '.') ? $dir . '/' : '';

        return "{$prefix}{$name}-{$variant}.webp";
    }

    /**
     * Generate every configured WebP variant next to the original on the media
     * disk. Best-effort and idempotent (overwrites). No-ops when disabled or the
     * source is missing/unreadable — the resize must never break the upload.
     */
    public static function generate(string $originalPath): void
    {
        if (! self::enabled()) {
            return;
        }

        $disk = Storage::disk(Media::disk());
        $variants = (array) config('media.variants', []);

        if ($variants === [] || ! $disk->exists($originalPath)) {
            return;
        }

        try {
            $manager = self::manager();
            $source = $disk->get($originalPath);

            foreach ($variants as $name => $spec) {
                $image = $manager->read($source);
                // scaleDown only shrinks: an image narrower than the target is left as-is.
                $image->scaleDown(width: (int) ($spec['width'] ?? 500));
                $encoded = (string) $image->toWebp(quality: (int) ($spec['quality'] ?? 80));
                $disk->put(self::variantPath($originalPath, (string) $name), $encoded, 'public');
            }
        } catch (\Throwable $e) {
            Log::warning('Image variant generation failed', ['path' => $originalPath, 'error' => $e->getMessage()]);
        }
    }

    /** Remove every variant of an original (called by Media::delete). */
    public static function delete(string $originalPath): void
    {
        $disk = Storage::disk(Media::disk());

        foreach (self::names() as $name) {
            $path = self::variantPath($originalPath, $name);
            if ($disk->exists($path)) {
                $disk->delete($path);
            }
        }
    }

    private static function manager(): ImageManager
    {
        return config('media.image_driver', 'gd') === 'imagick'
            ? ImageManager::imagick()
            : ImageManager::gd();
    }
}
