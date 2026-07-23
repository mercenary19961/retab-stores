<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * "I want this" demand signals for Coming-Soon products. Read + resolve: staff
 * see who asked for what (account or guest phone), follow up on WhatsApp, then
 * mark the request handled.
 */
class ProductRequestController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status'); // open | handled | (all)

        $requests = ProductRequest::with(['product:id,name_ar,name_en,slug', 'user:id,name,phone'])
            ->when($status === 'open', fn ($q) => $q->whereNull('handled_at'))
            ->when($status === 'handled', fn ($q) => $q->whereNotNull('handled_at'))
            ->latest()
            ->paginate(30)
            ->withQueryString()
            ->through(fn (ProductRequest $r) => [
                'id' => $r->id,
                'product' => $r->product ? [
                    'name_ar' => $r->product->name_ar,
                    'name_en' => $r->product->name_en,
                    'slug' => $r->product->slug,
                ] : null,
                'customer' => $r->user?->name,
                'phone' => $r->user?->phone ?? $r->phone,
                'is_guest' => $r->user_id === null,
                'handled' => $r->handled_at !== null,
                'created_at' => $r->created_at?->toDateTimeString(),
            ]);

        return Inertia::render('admin/product-requests/index', [
            'requests' => $requests,
            'filters' => ['status' => in_array($status, ['open', 'handled'], true) ? $status : null],
            'openCount' => ProductRequest::whereNull('handled_at')->count(),
        ]);
    }

    public function markHandled(ProductRequest $productRequest)
    {
        $productRequest->update(['handled_at' => now()]);

        return back()->with('success', __('messages.admin.request_handled'));
    }
}
