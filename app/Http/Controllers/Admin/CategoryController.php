<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Services\ChangeLog\ChangeLogService;
use App\Support\ArabicSlug;
use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use RuntimeException;

/**
 * Categories: the storefront's navbar groups and the categories products are
 * filed under. See the Category model for the two-level shape this enforces.
 *
 * One page: the tree, with create/edit in a dialog. Changes feed the storefront
 * directly — the navbar, the catalogue filter chips and the homepage tiles all
 * read this table — so every write is logged to the change log, and edits are
 * revertable from there.
 */
class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::withCount(['products', 'children'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        // Soft-deleted products still block a delete (see deletionBlocker), but
        // withCount leaves them out, so they are counted once for the whole list.
        $allProducts = Product::withTrashed()
            ->selectRaw('category_id, count(*) as aggregate')
            ->groupBy('category_id')
            ->pluck('aggregate', 'category_id');

        return Inertia::render('admin/categories/index', [
            'categories' => $categories->map(fn (Category $c) => [
                'id' => $c->id,
                'name_ar' => $c->name_ar,
                'name_en' => $c->name_en,
                'slug' => $c->slug,
                'parent_id' => $c->parent_id,
                'sort_order' => $c->sort_order,
                'is_active' => $c->is_active,
                'image' => $c->imageUrl(),
                'products_count' => $c->products_count,
                'children_count' => $c->children_count,
                'is_offers_bucket' => $c->isOffersBucket(),
                'delete_blocker' => $c->deletionBlocker((int) ($allProducts[$c->id] ?? 0), $c->children_count),
            ])->values(),
        ]);
    }

    public function store(Request $request, ChangeLogService $changeLog)
    {
        $data = $this->validated($request);
        // New categories go last, so they never jump ahead of the existing tiles.
        $data['sort_order'] = (int) Category::max('sort_order') + 1;

        DB::transaction(function () use ($data, $changeLog) {
            $category = Category::create($data);
            $changeLog->logCreated($category, $category->name_ar);
        });

        return back()->with('success', __('messages.admin.category_saved'));
    }

    public function update(Request $request, Category $category, ChangeLogService $changeLog)
    {
        $data = $this->validated($request, $category);

        DB::transaction(function () use ($category, $data, $changeLog) {
            $before = $category->attributesToArray();
            $category->fill($data)->save();
            $changeLog->logUpdated($category, $before, $category->name_ar);
        });

        return back()->with('success', __('messages.admin.category_saved'));
    }

    /** One-click show/hide from the list. Reversible, so no confirm step. */
    public function toggle(Category $category, ChangeLogService $changeLog)
    {
        DB::transaction(function () use ($category, $changeLog) {
            $before = $category->attributesToArray();
            $category->update(['is_active' => ! $category->is_active]);
            $changeLog->logUpdated($category, $before, $category->name_ar);
        });

        return back()->with('success', __($category->is_active ? 'messages.admin.category_shown' : 'messages.admin.category_hidden'));
    }

    /**
     * Move a category one step up or down among its siblings.
     *
     * 🔑 SWAPS the two sort_order values rather than renumbering the siblings.
     * The homepage tiles are ordered by sort_order across ALL categories, not per
     * group, so renumbering one group from 0 would collide with the other group's
     * values and reshuffle tiles nobody touched.
     */
    public function move(Request $request, Category $category)
    {
        $direction = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]])['direction'];

        $siblings = Category::where('parent_id', $category->parent_id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $index = $siblings->search(fn (Category $c) => $c->id === $category->id);
        $neighbour = $siblings->get($direction === 'up' ? $index - 1 : $index + 1);

        if ($neighbour === null) {
            return back();
        }

        DB::transaction(function () use ($category, $neighbour, $direction) {
            $mine = $category->sort_order;
            $theirs = $neighbour->sort_order;

            if ($mine === $theirs) {
                // A tie is broken by id, which is exactly what we are trying to
                // override, so separate the pair by one instead of swapping equals.
                $direction === 'up'
                    ? $neighbour->update(['sort_order' => $theirs + 1])
                    : $category->update(['sort_order' => $mine + 1]);

                return;
            }

            $category->update(['sort_order' => $theirs]);
            $neighbour->update(['sort_order' => $mine]);
        });

        return back();
    }

    public function destroy(Category $category, ChangeLogService $changeLog)
    {
        if ($blocker = $category->deletionBlocker()) {
            return back()->with('error', __('messages.admin.'.$blocker));
        }

        DB::transaction(function () use ($category, $changeLog) {
            $changeLog->logDeleted($category, $category->name_ar);
            $category->delete();
        });

        // Only now is the file unreferenced: a deleted category has nothing to be
        // restored into, whereas an edit's old image stays on disk so reverting
        // that edit from the change log still finds it.
        if (Category::isUploadedImage($category->image)) {
            Media::delete($category->image);
        }

        return back()->with('success', __('messages.admin.category_deleted'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Category $category = null): array
    {
        $request->validate([
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            // Lowercase Latin or Arabic words joined by single hyphens — the same
            // alphabet ArabicSlug produces. It is the `?category=` value in public URLs.
            'slug' => [
                'nullable', 'string', 'max:191',
                'regex:/^[\p{Arabic}a-z0-9]+(?:-[\p{Arabic}a-z0-9]+)*$/u',
                Rule::unique('categories', 'slug')->ignore($category?->id),
            ],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'is_active' => ['required', 'boolean'],
            'image' => ['nullable', 'file', 'max:4096', 'mimes:jpg,jpeg,png,webp,gif', 'mimetypes:image/jpeg,image/png,image/webp,image/gif'],
            'remove_image' => ['nullable', 'boolean'],
        ]);

        $parentId = $request->filled('parent_id') ? (int) $request->input('parent_id') : null;
        $slug = $request->filled('slug')
            ? (string) $request->input('slug')
            : $this->uniqueSlug((string) $request->input('name_en'), (string) $request->input('name_ar'), $category);

        $this->assertShape($category, $parentId, $slug);

        $data = [
            'name_ar' => $request->input('name_ar'),
            'name_en' => $request->input('name_en') ?: null,
            'slug' => $slug,
            'parent_id' => $parentId,
            'is_active' => $request->boolean('is_active'),
        ];

        if ($request->hasFile('image')) {
            try {
                $data['image'] = Media::storeImage($request->file('image'), Category::IMAGE_DIR);
            } catch (RuntimeException) {
                throw ValidationException::withMessages(['image' => __('messages.admin.category_image_invalid')]);
            }
        } elseif ($request->boolean('remove_image')) {
            $data['image'] = null;
        }

        return $data;
    }

    /**
     * Enforce the two-level shape (see the Category model). Each rule names the
     * field the admin has to change, so the error lands next to it in the dialog.
     *
     * @throws ValidationException
     */
    private function assertShape(?Category $category, ?int $parentId, string $slug): void
    {
        if ($category?->isOffersBucket()) {
            if ($slug !== $category->slug) {
                throw ValidationException::withMessages(['slug' => __('messages.admin.category_slug_locked')]);
            }
            if ($parentId !== null) {
                throw ValidationException::withMessages(['parent_id' => __('messages.admin.category_offers_top_level')]);
            }
        }

        if ($parentId === null) {
            return;
        }

        if ($category && $parentId === $category->id) {
            throw ValidationException::withMessages(['parent_id' => __('messages.admin.category_parent_self')]);
        }

        if ($category && $category->children()->exists()) {
            throw ValidationException::withMessages(['parent_id' => __('messages.admin.category_group_stays_top')]);
        }

        $parent = Category::find($parentId);

        if ($parent->parent_id !== null) {
            throw ValidationException::withMessages(['parent_id' => __('messages.admin.category_parent_depth')]);
        }

        // withTrashed for the same reason as the delete guard: a trashed product
        // restored later would land in a dropdown heading.
        if (Product::withTrashed()->where('category_id', $parent->id)->exists()) {
            throw ValidationException::withMessages(['parent_id' => __('messages.admin.category_parent_has_products')]);
        }
    }

    /**
     * A slug from the English name when there is one (matching the existing
     * `stuffed-dates` style), else from the Arabic, made unique with -2, -3…
     */
    private function uniqueSlug(string $nameEn, string $nameAr, ?Category $category): string
    {
        // Editing without touching the slug keeps the current one: it is a public
        // URL, and it should only change when somebody means it to.
        if ($category) {
            return $category->slug;
        }

        $base = Str::slug($nameEn) ?: ArabicSlug::make($nameAr) ?: 'category';
        $slug = $base;

        for ($n = 2; Category::where('slug', $slug)->exists(); $n++) {
            $slug = "{$base}-{$n}";
        }

        return $slug;
    }
}
