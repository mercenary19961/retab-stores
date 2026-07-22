<?php

namespace Tests\Feature;

use App\Support\ImageVariants;
use App\Support\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageVariantsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! (gd_info()['WebP Support'] ?? false)) {
            $this->markTestSkipped('GD lacks WebP support in this environment.');
        }

        Storage::fake(Media::disk());
    }

    private function store(): string
    {
        return Media::storeImage(UploadedFile::fake()->image('photo.jpg', 900, 700), 'products/1');
    }

    public function test_storing_an_image_generates_webp_variants(): void
    {
        $path = $this->store();
        $disk = Storage::disk(Media::disk());

        foreach (['thumb', 'card', 'detail'] as $variant) {
            $vp = ImageVariants::variantPath($path, $variant);
            $this->assertTrue($disk->exists($vp), "missing variant {$variant}");
            $this->assertStringEndsWith('.webp', $vp);
            // Valid WebP container: bytes 8-12 spell "WEBP" (RIFF....WEBP).
            $this->assertSame('WEBP', substr((string) $disk->get($vp), 8, 4));
        }
    }

    public function test_card_variant_is_downsized_to_the_configured_width(): void
    {
        $path = $this->store(); // source is 900px wide; card target is 500
        $bytes = (string) Storage::disk(Media::disk())->get(ImageVariants::variantPath($path, 'card'));
        [$width] = getimagesizefromstring($bytes);
        $this->assertLessThanOrEqual(500, $width);
    }

    public function test_url_serves_the_variant_when_requested(): void
    {
        $path = $this->store();

        $this->assertStringContainsString('-card.webp', (string) Media::url($path, 'card'));
        // No variant argument → the original path.
        $this->assertStringContainsString($path, (string) Media::url($path));
    }

    public function test_deleting_an_image_removes_its_variants(): void
    {
        $path = $this->store();
        $disk = Storage::disk(Media::disk());
        $this->assertTrue($disk->exists(ImageVariants::variantPath($path, 'card')));

        Media::delete($path);

        $this->assertFalse($disk->exists($path));
        $this->assertFalse($disk->exists(ImageVariants::variantPath($path, 'card')));
    }

    public function test_disabled_variants_fall_back_to_the_original(): void
    {
        config(['media.variants_enabled' => false]);
        $path = 'products/1/abc.jpg';

        $this->assertStringContainsString('abc.jpg', (string) Media::url($path, 'card'));
        $this->assertStringNotContainsString('-card.webp', (string) Media::url($path, 'card'));
    }
}
