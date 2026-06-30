<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Single, centralized path for runtime uploads (product images, return photos).
 * NEVER write public files directly — always go through here so validation,
 * filename randomization, and the configured disk stay consistent. Backed by the
 * `media` disk (local "public" in dev, Cloudflare R2 in production).
 */
class Media
{
    /** @var list<string> */
    public const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    /** @var list<string> */
    public const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    public static function disk(): string
    {
        return (string) config('filesystems.media', 'public');
    }

    /**
     * Validate (extension AND MIME) and store an image under $dir with a random
     * filename. Returns the stored path (relative to the disk root).
     *
     * @throws RuntimeException on a disallowed file
     */
    public static function storeImage(UploadedFile $file, string $dir): string
    {
        self::assertImage($file);

        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'jpg');
        $name = Str::uuid() . '.' . $extension;

        return $file->storeAs(trim($dir, '/'), $name, ['disk' => self::disk(), 'visibility' => 'public']);
    }

    /** Public URL for a stored path (null-safe). */
    public static function url(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return Storage::disk(self::disk())->url($path);
    }

    /** Delete a stored file if it exists (null-safe, idempotent). */
    public static function delete(?string $path): void
    {
        if (! $path) {
            return;
        }

        $disk = Storage::disk(self::disk());
        if ($disk->exists($path)) {
            $disk->delete($path);
        }
    }

    /**
     * @throws RuntimeException
     */
    private static function assertImage(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw new RuntimeException('Upload failed.');
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $mime = (string) $file->getMimeType();

        if (! in_array($extension, self::IMAGE_EXTENSIONS, true) || ! in_array($mime, self::IMAGE_MIMES, true)) {
            throw new RuntimeException('Only JPG, PNG, WEBP, or GIF images are allowed.');
        }
    }
}
