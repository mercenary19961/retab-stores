<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The admin notification bell and its full history. Rows live in the standard
 * Laravel `notifications` table (one per staff recipient, each with its own
 * read_at). Every action is scoped to the current user so nobody can touch — or
 * even see — another admin's copy.
 */
class NotificationController extends Controller
{
    /** Types offered by the history filter; anything else is ignored as input. */
    private const TYPES = ['new_order', 'return_requested', 'product_requested'];

    /**
     * Full history — the bell only carries the latest 10, so without this the
     * 11th notification is unreachable.
     */
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $status = $request->query('status'); // unread | read | (all)
        $type = $request->query('type');
        $perPage = $this->perPage($request, 25);

        $entries = $user->notifications()
            ->when($status === 'unread', fn ($q) => $q->whereNull('read_at'))
            ->when($status === 'read', fn ($q) => $q->whereNotNull('read_at'))
            // JSON path filter on the structured payload (json_extract on both
            // MySQL/MariaDB and SQLite). Whitelisted, so it's never raw input.
            ->when(in_array($type, self::TYPES, true), fn ($q) => $q->where('data->type', $type))
            ->latest()
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (DatabaseNotification $n) => [
                'id' => $n->id,
                'read' => $n->read_at !== null,
                'created_at' => $n->created_at?->toIso8601String(),
                'data' => $n->data,
            ]);

        // ⚠️ Prop is `entries`, NOT `notifications`: that name is already taken by
        // the shared bell prop (`{unread, items}`) and a page prop of the same name
        // would override it, breaking the bell on this very page.
        return Inertia::render('admin/notifications/index', [
            'entries' => $entries,
            'filters' => [
                'status' => in_array($status, ['unread', 'read'], true) ? $status : null,
                'type' => in_array($type, self::TYPES, true) ? $type : null,
                'per_page' => $perPage,
            ],
        ]);
    }

    /** Mark one notification read, then redirect to its stored target. */
    public function open(Request $request, string $notification): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $note = $user->notifications()->findOrFail($notification);
        $note->markAsRead();

        // Only ever redirect to an in-app admin path we wrote ourselves.
        $url = $note->data['url'] ?? null;
        $target = is_string($url) && str_starts_with($url, '/') ? $url : '/admin/dashboard';

        return redirect($target);
    }

    /** Mark every unread notification for the current user as read. */
    public function readAll(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->unreadNotifications->markAsRead();

        return back();
    }
}
