<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The admin notification bell. Rows live in the standard Laravel `notifications`
 * table (one per staff recipient, each with its own read_at). Every action is
 * scoped to the current user so nobody can touch another admin's copy.
 */
class NotificationController extends Controller
{
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
