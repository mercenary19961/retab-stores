<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Services\ChangeLog\ChangeLogService;
use App\Support\Media;
use App\Support\TableExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
    /** Columns the table may be sorted by (whitelist — never trust the raw param).
     *  'category' is virtual (sorts by the joined category name). */
    private const SORTABLE = ['name_ar', 'sku', 'smacc_sku', 'category', 'price', 'stock', 'is_active'];

    /** Full field set for the export (CSV / XLSX / JSON), in column order. */
    private const EXPORT_COLUMNS = [
        'id', 'name_ar', 'name_en', 'sku', 'smacc_sku', 'barcode', 'category',
        'price', 'sale_price', 'stock', 'low_stock_threshold', 'is_active',
        'is_featured', 'short_description_ar', 'short_description_en',
        'created_at', 'updated_at',
    ];

    public function index(Request $request)
    {
        $products = $this->filteredQuery($request)
            ->with(['category:id,name_ar', 'images'])
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Product $p) => [
                'id' => $p->id,
                'name_ar' => $p->name_ar,
                'image' => Media::url($p->primaryImage()?->path),
                'sku' => $p->sku,
                'smacc_sku' => $p->smacc_sku,
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
            'filters' => [
                'search' => $request->query('search'),
                'category' => $request->query('category') ? (int) $request->query('category') : null,
                'sort' => in_array($request->query('sort'), self::SORTABLE, true) ? $request->query('sort') : null,
                'direction' => $request->query('direction') === 'asc' ? 'asc' : 'desc',
            ],
            'categories' => $this->categoryOptions(),
        ]);
    }

    /**
     * Shared list query for the table and the export: search (name/sku/smacc),
     * category filter, and a whitelisted sort (falls back to newest-first).
     */
    private function filteredQuery(Request $request)
    {
        $search = $request->query('search');
        $categoryId = $request->query('category');
        $sort = in_array($request->query('sort'), self::SORTABLE, true) ? $request->query('sort') : null;
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';

        // Columns qualified with products.* so they stay unambiguous once the
        // category sort adds a join (categories also has name_ar/name_en/slug).
        $query = Product::query()
            ->when($search, fn ($q) => $q->where(fn ($w) => $w
                ->where('products.name_ar', 'like', "%{$search}%")
                ->orWhere('products.name_en', 'like', "%{$search}%")
                ->orWhere('products.sku', 'like', "%{$search}%")
                ->orWhere('products.smacc_sku', 'like', "%{$search}%")))
            ->when($categoryId, fn ($q) => $q->where('products.category_id', $categoryId));

        if ($sort === 'category') {
            $query->leftJoin('categories', 'categories.id', '=', 'products.category_id')
                ->orderBy('categories.name_ar', $direction)
                ->select('products.*');
        } elseif ($sort) {
            $query->orderBy($sort, $direction);
        } else {
            $query->latest();
        }

        return $query;
    }

    /**
     * Download the (filtered) catalogue as CSV, XLSX or JSON. Same filters/sort
     * as the table, so you export exactly what you're looking at.
     */
    public function export(Request $request)
    {
        $format = in_array($request->query('format'), ['csv', 'xlsx', 'json'], true)
            ? $request->query('format')
            : 'csv';

        $rows = $this->filteredQuery($request)
            ->with('category:id,name_ar')
            ->get()
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'name_ar' => $p->name_ar,
                'name_en' => $p->name_en,
                'sku' => $p->sku,
                'smacc_sku' => $p->smacc_sku,
                'barcode' => $p->barcode,
                'category' => $p->category?->name_ar,
                'price' => (float) $p->price,
                'sale_price' => $p->sale_price !== null ? (float) $p->sale_price : null,
                'stock' => $p->stock,
                'low_stock_threshold' => $p->low_stock_threshold,
                'is_active' => (int) $p->is_active,
                'is_featured' => (int) $p->is_featured,
                'short_description_ar' => $p->short_description_ar,
                'short_description_en' => $p->short_description_en,
                'created_at' => $p->created_at?->toDateTimeString(),
                'updated_at' => $p->updated_at?->toDateTimeString(),
            ]);

        return TableExport::download($format, 'products', self::EXPORT_COLUMNS, $rows);
    }

    public function create()
    {
        return Inertia::render('admin/products/form', [
            'product' => null,
            'categories' => $this->categoryOptions(),
        ]);
    }

    public function store(Request $request, ChangeLogService $changeLog)
    {
        $data = $this->validateProduct($request);

        DB::transaction(function () use ($data, $changeLog) {
            $product = Product::create($data);
            $changeLog->logCreated($product, $product->name_ar);
        });

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

    public function update(Request $request, Product $product, ChangeLogService $changeLog)
    {
        $data = $this->validateProduct($request, $product);

        DB::transaction(function () use ($product, $data, $changeLog) {
            $before = $product->attributesToArray();
            $product->update($data);
            $changeLog->logUpdated($product, $before, $product->name_ar);
        });

        return redirect()->route('admin.products.index')->with('success', __('messages.admin.product_updated'));
    }

    public function destroy(Product $product, ChangeLogService $changeLog)
    {
        DB::transaction(function () use ($product, $changeLog) {
            $product->delete(); // soft delete — preserves order history references
            $changeLog->logDeleted($product, $product->name_ar);
        });

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
