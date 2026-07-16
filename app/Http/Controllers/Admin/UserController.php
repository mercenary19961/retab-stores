<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Permission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Staff & access control (admin only, gated by the `admin` middleware). Lists
 * the back-office staff, creates editor accounts, and manages each editor's
 * granular permissions. Admins are shown but not editable — they have implicit
 * full access and cannot be de-privileged here.
 */
class UserController extends Controller
{
    public function index(): Response
    {
        $staff = User::whereIn('role', ['admin', 'editor'])
            ->orderByRaw("role = 'admin' desc") // admins first
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'permissions', 'created_at']);

        return Inertia::render('admin/users/index', [
            'staff' => $staff->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->role,
                'created_at' => $u->created_at?->toDateString(),
                'permissions' => $u->resolvedPermissions(), // [] for admins (full access)
            ]),
            'schema' => Permission::SCHEMA,
            'defaults' => Permission::DEFAULTS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'], // 'hashed' cast
            'role' => 'editor',
            'email_verified_at' => now(),
            'permissions' => Permission::DEFAULTS,
        ]);

        return back()->with('success', __('messages.admin.editor_created', ['name' => $user->name]));
    }

    public function updatePermissions(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->isEditor(), 403); // admins keep implicit full access

        $request->validate(['permissions' => ['required', 'array']]);
        $input = $request->input('permissions', []);

        // Sanitize against the schema — never trust arbitrary keys.
        $clean = [];
        foreach (Permission::SCHEMA as $section => $actions) {
            foreach ($actions as $action) {
                $clean[$section][$action] = (bool) ($input[$section][$action] ?? false);
            }
        }

        $user->update(['permissions' => $clean]);

        return back()->with('success', __('messages.admin.permissions_updated', ['name' => $user->name]));
    }

    public function destroy(User $user): RedirectResponse
    {
        abort_if($user->id === Auth::id(), 403); // no self-removal
        abort_unless($user->isEditor(), 403);    // only editor accounts are removable here

        $user->forceDelete();

        return back()->with('success', __('messages.admin.editor_deleted'));
    }
}
