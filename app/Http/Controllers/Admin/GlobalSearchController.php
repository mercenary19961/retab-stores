<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentPage;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin global search. One box that queries products, orders, customers and
 * content pages at once and returns ranked, grouped results.
 *
 * "Smart" without a search engine: candidates are fetched with portable LIKE
 * (works on MariaDB/MySQL/SQLite — no FULLTEXT), then scored in PHP by match
 * quality (exact > prefix > contains) weighted per field (a SKU/order-number
 * hit outranks a name substring) and summed across query tokens, so multi-word
 * queries favour rows that match more of what was typed. Groups are ordered by
 * their best hit so the most relevant kind of thing floats to the top.
 */
class GlobalSearchController extends Controller
{
    private const PER_GROUP = 6;   // results shown per entity
    private const CANDIDATES = 30; // rows pulled from the DB before scoring

    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['groups' => []]);
        }

        $tokens = array_values(array_filter(preg_split('/\s+/', mb_strtolower($q))));

        $groups = array_values(array_filter([
            $this->products($q, $tokens),
            $this->orders($q, $tokens),
            $this->customers($q, $tokens),
            $this->pages($q, $tokens),
        ]));

        // Most relevant group first.
        usort($groups, fn ($a, $b) => $b['score'] <=> $a['score']);

        return response()->json([
            'groups' => array_map(fn ($g) => ['type' => $g['type'], 'items' => $g['items']], $groups),
        ]);
    }

    private function products(string $q, array $tokens): ?array
    {
        $rows = $this->candidates(Product::query(), ['name_ar', 'name_en', 'sku', 'smacc_sku', 'barcode'], $q, $tokens)->get();

        return $this->group('products', $rows, fn (Product $p) => [
            'score' => $this->relevance($tokens, [
                [$p->sku, 4], [$p->smacc_sku, 4], [$p->barcode, 3], [$p->name_ar, 3], [$p->name_en, 2],
            ]),
            'label' => $p->name_ar,
            'sublabel' => implode(' · ', array_filter([$p->sku, $p->stock.' in stock'])),
            'url' => "/admin/products/{$p->id}/edit",
        ]);
    }

    private function orders(string $q, array $tokens): ?array
    {
        $rows = $this->candidates(Order::query(), ['order_number', 'customer_name', 'customer_email', 'customer_phone'], $q, $tokens)->get();

        return $this->group('orders', $rows, fn (Order $o) => [
            'score' => $this->relevance($tokens, [
                [$o->order_number, 5], [$o->customer_phone, 3], [$o->customer_name, 3], [$o->customer_email, 2],
            ]),
            'label' => $o->order_number,
            'sublabel' => implode(' · ', array_filter([$o->customer_name, $o->status->value, number_format((float) $o->total, 2).' SAR'])),
            'url' => "/admin/orders/{$o->order_number}",
        ]);
    }

    private function customers(string $q, array $tokens): ?array
    {
        $base = User::where(fn ($x) => $x->whereNull('role')->orWhereNotIn('role', ['admin', 'editor']));
        $rows = $this->candidates($base, ['name', 'email', 'phone'], $q, $tokens)->get();

        return $this->group('customers', $rows, fn (User $u) => [
            'score' => $this->relevance($tokens, [[$u->phone, 4], [$u->name, 3], [$u->email, 3]]),
            'label' => $u->name ?? $u->email ?? $u->phone ?? "#{$u->id}",
            'sublabel' => implode(' · ', array_filter([$u->phone, $u->email])),
            'url' => "/admin/customers/{$u->id}",
        ]);
    }

    private function pages(string $q, array $tokens): ?array
    {
        $rows = $this->candidates(ContentPage::query(), ['title_ar', 'title_en', 'slug'], $q, $tokens)->get();

        return $this->group('pages', $rows, fn (ContentPage $p) => [
            'score' => $this->relevance($tokens, [[$p->slug, 3], [$p->title_ar, 3], [$p->title_en, 2]]),
            'label' => $p->title_ar ?? $p->slug,
            'sublabel' => $p->slug,
            'url' => "/admin/content-pages/{$p->id}/edit",
        ]);
    }

    /**
     * Candidate rows: any field contains the whole query, OR (multi-word only)
     * every token appears in some field. Capped before PHP scoring.
     */
    private function candidates(Builder $query, array $fields, string $q, array $tokens): Builder
    {
        return $query->where(function ($w) use ($fields, $q, $tokens) {
            $w->where(function ($x) use ($fields, $q) {
                foreach ($fields as $f) {
                    $x->orWhere($f, 'like', "%{$q}%");
                }
            });

            if (count($tokens) > 1) {
                $w->orWhere(function ($x) use ($fields, $tokens) {
                    foreach ($tokens as $t) {
                        $x->where(function ($y) use ($fields, $t) {
                            foreach ($fields as $f) {
                                $y->orWhere($f, 'like', "%{$t}%");
                            }
                        });
                    }
                });
            }
        })->limit(self::CANDIDATES);
    }

    /** Score, sort, cap; returns a group payload (with its top score) or null. */
    private function group(string $type, $rows, callable $shape): ?array
    {
        $scored = $rows
            ->map($shape)
            ->filter(fn ($r) => $r['score'] > 0)
            ->sortByDesc('score')
            ->take(self::PER_GROUP)
            ->values();

        if ($scored->isEmpty()) {
            return null;
        }

        return [
            'type' => $type,
            'score' => $scored->first()['score'],
            'items' => $scored->map(fn ($r) => [
                'label' => $r['label'],
                'sublabel' => $r['sublabel'],
                'url' => $r['url'],
            ])->all(),
        ];
    }

    /**
     * Sum, over query tokens, the best per-field match: exact (100) > prefix
     * (60) > contains (30), each scaled by that field's weight.
     *
     * @param  array<int, array{0: mixed, 1: int}>  $weightedFields  [value, weight] pairs
     */
    private function relevance(array $tokens, array $weightedFields): int
    {
        $total = 0;

        foreach ($tokens as $token) {
            $best = 0;
            foreach ($weightedFields as [$value, $weight]) {
                $v = mb_strtolower(trim((string) $value));
                if ($v === '') {
                    continue;
                }
                if ($v === $token) {
                    $best = max($best, 100 * $weight);
                } elseif (str_starts_with($v, $token)) {
                    $best = max($best, 60 * $weight);
                } elseif (str_contains($v, $token)) {
                    $best = max($best, 30 * $weight);
                }
            }
            $total += $best;
        }

        return $total;
    }
}
