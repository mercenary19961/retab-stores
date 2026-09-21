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

    /**
     * Hero video. Two containers only, because these are the two every current
     * browser plays natively - anything else would need a transcode step we do
     * not have, and would fail silently in the player rather than at upload.
     *
     * @var list<string>
     */
    public const VIDEO_EXTENSIONS = ['mp4', 'webm'];

    /** @var list<string> */
    public const VIDEO_MIMES = ['video/mp4', 'video/webm'];

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
        $name = Str::uuid().'.'.$extension;

        $path = $file->storeAs(trim($dir, '/'), $name, ['disk' => self::disk(), 'visibility' => 'public']);

        // Produce the responsive WebP variants (best-effort; never breaks the upload).
        ImageVariants::generate($path);

        return $path;
    }

    /**
     * Store an image already downloaded to a local temp path (e.g. a product photo
     * migrated from an external CDN). Wraps it as a test-mode UploadedFile so it
     * runs through the SAME extension/MIME validation and filename randomization as
     * a real upload. The caller owns the temp file's lifecycle.
     *
     * @throws RuntimeException on a disallowed file
     */
    public static function storeImageFromFile(string $tmpPath, string $originalName, string $dir): string
    {
        // $test = true → bypasses is_uploaded_file() so a non-HTTP file is accepted.
        $file = new UploadedFile($tmpPath, $originalName, null, null, true);

        return self::storeImage($file, $dir);
    }

    /**
     * Validate and store a hero video under $dir with a random filename.
     *
     * 🔴 Deliberately NOT routed through storeImage(): that calls
     * ImageVariants::generate(), which would hand a video file to GD. It is
     * best-effort and would not throw, so the failure would be a log line nobody
     * reads plus three 404ing variant URLs.
     *
     * ⚠️ The size cap is enforced HERE as well as in the controller's validation.
     * A hero video is downloaded by every first-time visitor before they see a
     * product, so an unbounded one is a performance regression the client cannot
     * see from their own office wifi.
     *
     * @throws RuntimeException on a disallowed or oversized file
     */
    public static function storeVideo(UploadedFile $file, string $dir): string
    {
        self::assertVideo($file);

        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'mp4');
        $name = Str::uuid().'.'.$extension;

        return $file->storeAs(trim($dir, '/'), $name, ['disk' => self::disk(), 'visibility' => 'public']);
    }

    /** The cap, in megabytes, shared by the uploader and the form validation. */
    public static function videoMaxMb(): int
    {
        return (int) config('media.video_max_mb', 12);
    }

    /**
     * Public URL for a stored path (null-safe). Pass a $variant (thumb/card/detail)
     * to get the smaller WebP version; falls back to the original when variants are
     * disabled or the name is unknown, so callers can always request a variant.
     */
    public static function url(?string $path, ?string $variant = null): ?string
    {
        if (! $path) {
            return null;
        }

        if ($variant && ImageVariants::enabled() && in_array($variant, ImageVariants::names(), true)) {
            $path = ImageVariants::variantPath($path, $variant);
        }

        return Storage::disk(self::disk())->url($path);
    }

    /** Delete a stored file and its variants if they exist (null-safe, idempotent). */
    public static function delete(?string $path): void
    {
        if (! $path) {
            return;
        }

        $disk = Storage::disk(self::disk());
        if ($disk->exists($path)) {
            $disk->delete($path);
        }

        ImageVariants::delete($path);
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

    /**
     * @throws RuntimeException
     */
    private static function assertVideo(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw new RuntimeException('Upload failed.');
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $mime = (string) $file->getMimeType();

        // Extension AND sniffed MIME, the same pair as images: an .mp4 rename of
        // something else passes the first check and fails the second.
        if (! in_array($extension, self::VIDEO_EXTENSIONS, true) || ! in_array($mime, self::VIDEO_MIMES, true)) {
            throw new RuntimeException('Only MP4 or WEBM videos are allowed.');
        }

        $max = self::videoMaxMb();
        if ($file->getSize() > $max * 1024 * 1024) {
            throw new RuntimeException("The video must be {$max} MB or smaller.");
        }
    }
}
