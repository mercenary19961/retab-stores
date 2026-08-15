<?php

namespace Tests\Feature\Seo;

use App\Models\Category;
use App\Models\ContentPage;
use App\Models\Product;
use App\Providers\AppServiceProvider;
use App\Ssr\TimeoutHttpGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Ssr\Gateway;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(array $overrides = []): Product
    {
        $category = Category::firstOrCreate(['slug' => 'dates'], ['name_ar' => 'تمور', 'is_active' => true]);

        return Product::create(array_merge([
            'category_id' => $category->id,
            'name_ar' => 'تمر سكري',
            'slug' => 'sukkari',
            'sku' => 'SKU-'.fake()->unique()->numerify('####'),
            'price' => 50,
            'stock' => 10,
            'is_active' => true,
        ], $overrides));
    }

    public function test_sitemap_lists_active_products_and_published_pages_only(): void
    {
        $this->makeProduct();
        $this->makeProduct(['slug' => 'hidden', 'is_active' => false]);
        ContentPage::create(['slug' => 'about', 'title_ar' => 'من نحن', 'body_ar' => 'نص', 'is_published' => true]);
        ContentPage::create(['slug' => 'draft', 'title_ar' => 'مسودة', 'body_ar' => 'نص', 'is_published' => false]);

        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $xml = $response->getContent();
        $this->assertStringContainsString('/products/sukkari', $xml);
        $this->assertStringContainsString('/pages/about', $xml);
        $this->assertStringNotContainsString('/products/hidden', $xml);
        $this->assertStringNotContainsString('/pages/draft', $xml);
    }

    public function test_robots_disallows_private_areas_and_points_to_sitemap(): void
    {
        $response = $this->get('/robots.txt');

        $response->assertOk();
        $body = $response->getContent();
        $this->assertStringContainsString('Disallow: /admin', $body);
        $this->assertStringContainsString('Sitemap: '.route('seo.sitemap'), $body);
    }

    public function test_ssr_gateway_binding_resolves_to_timeout_gateway(): void
    {
        $this->assertInstanceOf(TimeoutHttpGateway::class, app(Gateway::class));
    }

    public function test_an_indexable_site_sends_no_robots_header(): void
    {
        config(['retab.indexable' => true]);

        $this->get('/robots.txt')->assertOk()->assertHeaderMissing('X-Robots-Tag');
    }

    public function test_a_non_indexable_site_sends_noindex_on_every_response(): void
    {
        config(['retab.indexable' => false]);

        $this->get('/robots.txt')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/sitemap.xml')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    /**
     * The noindex mode must NOT block crawling. robots.txt governs FETCHING and
     * noindex governs INDEXING, so a blanket `Disallow: /` would stop Google
     * ever reading the noindex header and a linked URL could still be listed.
     */
    public function test_non_indexable_robots_still_allows_crawling_but_drops_the_sitemap(): void
    {
        config(['retab.indexable' => false]);

        $body = $this->get('/robots.txt')->assertOk()->getContent();

        $this->assertStringNotContainsString('Disallow: /'."\n", $body);
        $this->assertStringNotContainsString('Sitemap:', $body);
        $this->assertStringContainsString('Disallow: /admin', $body);
    }

    /**
     * Pins AppServiceProvider's production branch. Without forceRootUrl, Laravel
     * builds every URL from the REQUEST host, so the site would keep serving
     * canonical tags, og:url and a full sitemap on the Railway hostname — a
     * self-canonicalising duplicate of the store competing with the real domain.
     */
    public function test_production_pins_generated_urls_to_the_canonical_host(): void
    {
        config(['app.url' => 'https://www.retab.com.sa']);
        $this->app['env'] = 'production';

        (new AppServiceProvider($this->app))->boot();

        $this->assertStringStartsWith('https://www.retab.com.sa/', route('seo.sitemap'));
        $this->assertStringStartsWith('https://www.retab.com.sa', route('home'));
    }

    public function test_product_payload_carries_absolute_url_for_json_ld(): void
    {
        $product = $this->makeProduct();

        $url = $this->get("/products/{$product->slug}")->inertiaPage()['props']['product']['url'];

        $this->assertSame(route('shop.product', $product->slug), $url);
    }

    /** The `<link rel="canonical">` href on a page, or null when there is no tag. */
    private function canonicalOf(string $uri): ?string
    {
        $html = $this->get($uri)->getContent();

        if (! preg_match('~<link rel="canonical" href="([^"]+)">~', $html, $m)) {
            return null;
        }

        // Blade escapes the `&` between query params; compare against real URLs.
        return html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
    }

    public function test_public_pages_carry_a_self_referencing_canonical(): void
    {
        $product = $this->makeProduct();

        $this->assertSame(route('home'), $this->canonicalOf('/'));
        $this->assertSame(route('shop.catalogue'), $this->canonicalOf('/shop'));
        $this->assertSame(route('shop.product', $product->slug), $this->canonicalOf("/products/{$product->slug}"));
    }

    /**
     * The catalogue is one path behind many query strings. Params that change
     * WHICH products are listed stay; params that only reorder them, search
     * them, or track the click are dropped, so those variants all consolidate
     * onto one URL instead of competing as duplicates.
     */
    public function test_canonical_keeps_filtering_params_and_drops_the_rest(): void
    {
        $base = route('shop.catalogue');

        $this->assertSame(
            $base.'?category=dates&page=2',
            $this->canonicalOf('/shop?category=dates&page=2&sort=price_asc&utm_source=whatsapp&fbclid=abc'),
        );

        $this->assertSame($base, $this->canonicalOf('/shop?q=%D8%AA%D9%85%D8%B1'));
        $this->assertSame($base.'?on_sale=1', $this->canonicalOf('/shop?on_sale=1'));
    }

    /** page=1 and on_sale=0 render the base page, so they must not mint a second URL. */
    public function test_canonical_collapses_no_op_params_onto_the_bare_url(): void
    {
        $base = route('shop.catalogue');

        $this->assertSame($base, $this->canonicalOf('/shop?page=1'));
        $this->assertSame($base, $this->canonicalOf('/shop?on_sale=0'));
    }

    /** Reaching the same page two ways must not produce two canonical URLs. */
    public function test_canonical_is_stable_regardless_of_query_param_order(): void
    {
        $this->assertSame(
            $this->canonicalOf('/shop?category=dates&page=2'),
            $this->canonicalOf('/shop?page=2&category=dates'),
        );
    }

    /** Private areas are robots-disallowed; a canonical tag there is noise. */
    public function test_no_canonical_on_private_paths(): void
    {
        $this->assertNull($this->canonicalOf('/login'));
        $this->assertNull($this->canonicalOf('/cart'));
    }

    /**
     * The tag exists to name ONE host. Built through url()->current() rather
     * than the request, so forceRootUrl wins even when the site is reached on
     * the Railway hostname — otherwise every duplicate would canonicalise to
     * itself, which is the failure the tag is supposed to prevent.
     */
    public function test_canonical_names_the_configured_host_not_the_requested_one(): void
    {
        config(['app.url' => 'https://retab.com.sa']);
        $this->app['env'] = 'production';

        (new AppServiceProvider($this->app))->boot();

        $html = $this->get('http://retab-website-production.up.railway.app/shop')->getContent();

        $this->assertStringContainsString('<link rel="canonical" href="https://retab.com.sa/shop">', $html);
    }
}
