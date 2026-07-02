<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\ReturnService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * Customer-facing return filing (defect/damage only, in-account, with photos).
 * Eligibility (delivered + 3-day window + no open return) is enforced by
 * ReturnService; this controller only gates ownership and shapes the page.
 */
class ReturnController extends Controller
{
    public function __construct(
        protected ReturnService $returns,
    ) {}

    public function create(Request $request, Order $order)
    {
        abort_unless($order->user_id === Auth::id(), 403);
        abort_unless($this->returns->canRequest($order), 404);

        return Inertia::render('shop/return-request', [
            'order' => [
                'order_number' => $order->order_number,
                'delivered_at' => $order->delivered_at?->toDateString(),
            ],
            'items' => $order->items()->get()->map(fn ($item) => [
                'order_item_id' => $item->id,
                'name_ar' => $item->product_name_ar,
                'name_en' => $item->product_name_en,
                'quantity' => $item->quantity,
            ])->values(),
            'windowDays' => ReturnService::WINDOW_DAYS,
        ]);
    }

    public function store(Request $request, Order $order)
    {
        abort_unless($order->user_id === Auth::id(), 403);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_item_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:0'],
            'photos' => ['required', 'array', 'min:1', 'max:5'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        try {
            $this->returns->fileReturn($order, $request->user(), $data['items'], $data['reason'], $request->file('photos', []));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('orders.show', $order->order_number)
            ->with('success', __('messages.returns.filed'));
    }
}
