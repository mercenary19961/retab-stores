<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Back-office catalogue management. Quantity-based inventory, no variants — one
 * product = one SMACC SKU. Stock edited here is the website mirror; the daily
 * SMACC import re-baselines it (see CLAUDE.md → POS).
 */
class ProductController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->query('search');
        $categoryId = $request->query('category');

        $products = Product::query()
            ->with(['category:id,name_ar', 'images'])
            ->when($search, fn ($q) => $q->where(fn ($w) => $w
                ->where('name_ar', 'like', "%{$search}%")
                ->orWhere('name_en', 'like', "%{$search}%")
                ->orWhere('sku', 'like', "%{$search}%")))
            ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Product $p) => [
                'id' => $p->id,
                'name_ar' => $p->name_ar,
                'image' => Media::url($p->primaryImage()?->path),
                'sku' => $p->sku,
                'category' => $p->category?->name_ar,
                'price' => (float) $p->price,
                'sale_price' => $p->sale_price !== null ? (float) $p->sale_price : null,
                'stock' => $p->stock,
                'is_low_stock' => $p->isLowStock(),
                'is_active' => $p->is_active,
                'is_featured' => $p->is_featured,
            ]);

        return Inertia::render('admin/products/index', [
            'products' => $products,
            'filters' => ['search' => $search, 'category' => $categoryId ? (int) $categoryId : null],
            'categories' => $this->categoryOptions(),
        ]);
    }

    public function create()
    {
        return Inertia::render('admin/products/form', [
            'product' => null,
            'categories' => $this->categoryOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateProduct($request);

        Product::create($data);

        return redirect()->route('admin.products.index')->with('success', __('messages.admin.product_created'));
    }

    public function edit(Product $product)
    {
        $product->load('images');

        return Inertia::render('admin/products/form', [
            'product' => [
                'id' => $product->id,
                'category_id' => $product->category_id,
                'name_ar' => $product->name_ar,
                'name_en' => $product->name_en,
                'slug' => $product->slug,
                'description_ar' => $product->description_ar,
                'description_en' => $product->description_en,
                'price' => (float) $product->price,
                'sale_price' => $product->sale_price !== null ? (float) $product->sale_price : null,
                'sku' => $product->sku,
                'smacc_sku' => $product->smacc_sku,
                'barcode' => $product->barcode,
                'stock' => $product->stock,
                'low_stock_threshold' => $product->low_stock_threshold,
                'is_active' => $product->is_active,
                'is_featured' => $product->is_featured,
                'images' => $product->images->sortBy('sort_order')->values()->map(fn ($img) => [
                    'id' => $img->id,
                    'url' => Media::url($img->path),
                    'is_primary' => $img->is_primary,
                ]),
            ],
            'categories' => $this->categoryOptions(),
        ]);
    }

    public function update(Request $request, Product $product)
    {
        $data = $this->validateProduct($request, $product);

        $product->update($data);

        return redirect()->route('admin.products.index')->with('success', __('messages.admin.product_updated'));
    }

    public function destroy(Product $product)
    {
        $product->delete(); // soft delete — preserves order history references

        return redirect()->route('admin.products.index')->with('success', __('messages.admin.product_deleted'));
    }

    /**
     * Shared validation for create + update. On update, unique rules ignore the
     * current product. Slug auto-derives from name_en / sku when left blank.
     *
     * @return array<string, mixed>
     */
    private function validateProduct(Request $request, ?Product $product = null): array
    {
        $id = $product?->id;

        $data = $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:191', Rule::unique('products', 'slug')->ignore($id)],
            'description_ar' => ['nullable', 'string'],
            'description_en' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0', 'lt:price'],
            'sku' => ['required', 'string', 'max:100', Rule::unique('products', 'sku')->ignore($id)],
            'smacc_sku' => ['nullable', 'string', 'max:100', Rule::unique('products', 'smacc_sku')->ignore($id)],
            'barcode' => ['nullable', 'string', 'max:100'],
            'stock' => ['required', 'integer', 'min:0'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'is_featured' => ['boolean'],
        ]);

        if (empty($data['slug'])) {
            $data['slug'] = $this->uniqueSlug($data['name_en'] ?? $data['sku'], $id);
        }

        return $data;
    }

    /**
     * Slugify the source (or fall back to the SKU), then suffix to stay unique.
     */
    private function uniqueSlug(string $source, ?int $ignoreId): string
    {
        $base = Str::slug($source) ?: Str::slug('product-' . Str::random(6));
        $slug = $base;
        $i = 2;

        while (Product::where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    /**
     * @return array<int, array{id: int, name_ar: string}>
     */
    private function categoryOptions(): array
    {
        return Category::orderBy('sort_order')
            ->get(['id', 'name_ar'])
            ->map(fn (Category $c) => ['id' => $c->id, 'name_ar' => $c->name_ar])
            ->all();
    }
}
