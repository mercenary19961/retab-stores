<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Product;
use App\Services\Discount\DiscountService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * Bulk + CSV product discounts. Applies a scheduled sale_price (+ window) to a
 * scope of products; the same parked-file → preview → apply pattern as the SMACC
 * stock import (apply re-reads the file, never trusts client-submitted diffs).
 */
class DiscountController extends Controller
{
    private const DIR = 'discount-imports';

    public function __construct(
        protected DiscountService $service,
    ) {}

    public function index()
    {
        return Inertia::render('admin/discounts/index', [
            'discounted' => Product::whereNotNull('sale_price')->orderBy('name_ar')->get()
                ->map(fn (Product $p) => [
                    'id' => $p->id,
                    'name_ar' => $p->name_ar,
                    'name_en' => $p->name_en,
                    'sku' => $p->sku,
                    'price' => (float) $p->price,
                    'sale_price' => (float) $p->sale_price,
                    'percent' => (float) $p->price > 0 ? (int) round((1 - (float) $p->sale_price / (float) $p->price) * 100) : 0,
                    'starts_at' => $p->sale_starts_at?->toDateTimeString(),
                    'ends_at' => $p->sale_ends_at?->toDateTimeString(),
                    'status' => $p->saleStatus(),
                ])->values(),
            'categories' => Category::orderBy('name_ar')
                ->withCount(['products' => fn ($q) => $q->where('is_active', true)->where('price', '>', 0)])
                ->get()
                ->map(fn (Category $c) => ['id' => $c->id, 'name_ar' => $c->name_ar, 'name_en' => $c->name_en, 'count' => $c->products_count]),
            'activeCount' => Product::where('is_active', true)->where('price', '>', 0)->count(),
            'history' => $this->recentApplies(),
        ]);
    }

    public function apply(Request $request)
    {
        $data = $request->validate([
            'percent' => ['required', 'numeric', 'min:1', 'max:99'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        $log = $this->service->bulkApply(
            (float) $data['percent'],
            $data['category_id'] ?? null,
            filled($data['starts_at'] ?? null) ? Carbon::parse($data['starts_at']) : null,
            filled($data['ends_at'] ?? null) ? Carbon::parse($data['ends_at']) : null,
            Auth::id(),
        );

        return back()->with('success', __('messages.admin.discount_applied', ['count' => $log->changes['summary']['applied'] ?? 0]));
    }

    public function previewImport(Request $request)
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:5120']]);

        $token = Str::uuid() . '.csv';
        $request->file('file')->storeAs(self::DIR, $token, 'local');

        try {
            $rows = $this->service->parse(Storage::disk('local')->path(self::DIR . '/' . $token));
        } catch (\RuntimeException $e) {
            Storage::disk('local')->delete(self::DIR . '/' . $token);

            return back()->with('error', $e->getMessage());
        }

        return Inertia::render('admin/discounts/preview', [
            'token' => $token,
            'diff' => $this->service->diff($rows),
        ]);
    }

    public function applyImport(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        $relative = self::DIR . '/' . basename($data['token']); // basename guards path traversal
        if (! Storage::disk('local')->exists($relative)) {
            return redirect()->route('admin.discounts.index')->with('error', __('messages.admin.import_expired'));
        }

        try {
            $rows = $this->service->parse(Storage::disk('local')->path($relative));
            $log = $this->service->applyImport(
                $rows,
                filled($data['starts_at'] ?? null) ? Carbon::parse($data['starts_at']) : null,
                filled($data['ends_at'] ?? null) ? Carbon::parse($data['ends_at']) : null,
                Auth::id(),
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        } finally {
            Storage::disk('local')->delete($relative);
        }

        return redirect()->route('admin.discounts.index')
            ->with('success', __('messages.admin.discount_applied', ['count' => $log->changes['summary']['applied'] ?? 0]));
    }

    public function clear(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
        ]);

        $ids = Product::whereNotNull('sale_price')
            ->when($data['product_id'] ?? null, fn ($q, $id) => $q->whereKey($id))
            ->when($data['category_id'] ?? null, fn ($q, $id) => $q->where('category_id', $id))
            ->pluck('id')->all();

        if (empty($ids)) {
            return back()->with('error', __('messages.admin.discount_none_to_clear'));
        }

        $log = $this->service->clear($ids, Auth::id());

        return back()->with('success', __('messages.admin.discount_cleared', ['count' => $log->changes['summary']['applied'] ?? 0]));
    }

    public function undo(ActivityLog $activityLog)
    {
        try {
            $this->service->undo($activityLog, Auth::id());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('messages.admin.discount_undone'));
    }

    /** @return list<array<string, mixed>> */
    private function recentApplies(): array
    {
        return ActivityLog::where('action', DiscountService::ACTION)
            ->with('user:id,name')
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (ActivityLog $log) => [
                'id' => $log->id,
                'mode' => $log->changes['summary']['mode'] ?? 'bulk',
                'applied' => $log->changes['summary']['applied'] ?? 0,
                'percent' => $log->changes['summary']['percent'] ?? null,
                'user' => $log->user?->name,
                'created_at' => $log->created_at?->toDateTimeString(),
                'reverted_at' => $log->reverted_at?->toDateTimeString(),
            ])
            ->all();
    }
}
