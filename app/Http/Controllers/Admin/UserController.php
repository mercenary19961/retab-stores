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
 * the back-office staff, creates admin and editor accounts, changes a staff
 * member's role, and manages each editor's granular permissions.
 *
 * 🔑 One invariant holds the whole page together: THERE IS ALWAYS AT LEAST ONE
 * ADMIN. This page is behind `admin` middleware, so zero admins means nobody
 * can reach it and the only way back is a shell on the production container.
 * Three rules make that provable rather than likely — store() only ever adds,
 * destroy() only ever removes editors, and updateRole() refuses to demote the
 * last admin.
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
            // Expanded server-side from the same SCHEMA, so every section is present
            // and explicitly denied where a preset doesn't name it (an absent section
            // would fall back to DEFAULTS and silently grant).
            'presets' => [
                'default' => Permission::DEFAULTS,
                'operations' => Permission::preset('operations'),
                'catalogue' => Permission::preset('catalogue'),
                'manager' => Permission::preset('manager'),
                'readonly' => Permission::preset('full', viewOnly: true),
                'full' => Permission::preset('full'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
            'role' => ['required', 'in:admin,editor'],
        ]);

        // forceCreate: `role`/`permissions` are guarded privilege fields.
        $user = User::forceCreate([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'], // 'hashed' cast
            'role' => $data['role'],
            'email_verified_at' => now(),
            // An admin bypasses every check, so a stored map would be dead weight
            // that later reads as meaningful. Editors start from the defaults.
            'permissions' => $data['role'] === 'editor' ? Permission::DEFAULTS : null,
        ]);

        $message = $user->isAdmin() ? 'messages.admin.admin_created' : 'messages.admin.editor_created';

        return back()->with('success', __($message, ['name' => $user->name]));
    }

    /**
     * Promote an editor to admin, or demote an admin back to editor.
     *
     * Two guards, both there to keep the page un-lockable-out-of:
     *  - The last remaining admin cannot be demoted (see the class docblock).
     *  - You cannot change your OWN role. Demoting yourself would drop you out
     *    of the `admin` middleware guarding this very page, mid-flow.
     *
     * ⚠️ That ORDER is deliberate. The self-guard subsumes the count one — you
     * must be an admin to be here, so any admin you are allowed to demote is by
     * definition not the last — which would leave the count branch unreachable
     * and untestable. Checking the count first means the only admin demoting
     * themselves gets the message that actually tells them what to do ("promote
     * someone else first") rather than a bare "not your own role".
     *
     * Stored permissions are deliberately left ALONE on promotion. They are
     * inert while the user is an admin (`resolvedPermissions()` returns [] and
     * `hasPermission()` short-circuits), so keeping them means a later demotion
     * restores exactly the grants that editor had before, instead of silently
     * resetting them to the defaults.
     */
    public function updateRole(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->isStaff(), 403);

        $role = $request->validate(['role' => ['required', 'in:admin,editor']])['role'];

        if ($user->role === $role) {
            return back(); // nothing to do
        }

        // Both refusals are legitimate states rather than attacks (the UI blocks
        // them too), so they answer with an explanation, not a 403.
        if ($role === 'editor' && User::where('role', 'admin')->count() <= 1) {
            return back()->with('error', __('messages.admin.role_last_admin'));
        }

        if ($user->id === Auth::id()) {
            return back()->with('error', __('messages.admin.role_self'));
        }

        $user->forceFill(['role' => $role])->save(); // guarded privilege field

        $message = $role === 'admin' ? 'messages.admin.role_promoted' : 'messages.admin.role_demoted';

        return back()->with('success', __($message, ['name' => $user->name]));
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

        $user->forceFill(['permissions' => $clean])->save(); // guarded privilege field

        return back()->with('success', __('messages.admin.permissions_updated', ['name' => $user->name]));
    }

    /**
     * Remove an editor account.
     *
     * Editors only, deliberately — but a departed admin is no longer a shell
     * job: demote them to editor first (which the last-admin guard protects),
     * then remove them here.
     */
    public function destroy(User $user): RedirectResponse
    {
        abort_if($user->id === Auth::id(), 403); // no self-removal
        abort_unless($user->isEditor(), 403);    // only editor accounts are removable here

        $user->forceDelete();

        return back()->with('success', __('messages.admin.editor_deleted'));
    }
}
