<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\MailAddress;
use App\Support\Permission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Staff & access control. Lists the back-office staff, creates admin and editor
 * accounts, changes a staff member's role, manages each editor's granular
 * permissions, and sets a colleague's password when they have lost theirs.
 *
 * 🔑 One invariant holds the whole page together: THERE IS ALWAYS AT LEAST ONE
 * ADMIN. Every write here is behind `admin` middleware, so zero admins means
 * nobody can reach them and the only way back is a shell on the production
 * container. Three rules make that provable rather than likely — store() only
 * ever adds, destroy() only ever removes editors, and updateRole() refuses to
 * demote the last admin.
 *
 * The one exception to "admin only" is the pair index() + resetPassword(),
 * gated on the `staff` permission section so a trusted editor can be given the
 * job of getting a colleague back into their account. That opens a real
 * escalation path, so read the guards on resetPassword() before touching it.
 */
class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $viewer = $request->user();
        $isAdmin = $viewer->isAdmin();

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
                // Whether this colleague could recover their own account by email,
                // which is what tells the reader why a reset is theirs to do.
                'can_self_recover' => MailAddress::isDeliverable($u->email),
                // Only an admin can edit the grid, so only an admin is sent it.
                'permissions' => $isAdmin ? $u->resolvedPermissions() : null, // [] for admins (full access)
            ]),
            // What the VIEWER may do, so the page never renders a control that
            // the route behind it would answer with a 403.
            'can' => [
                'manageStaff' => $isAdmin,
                'resetPasswords' => $viewer->hasPermission('staff.reset_password'),
            ],
            // Grid config is admin-only for the same reason as `permissions`.
            'schema' => $isAdmin ? Permission::SCHEMA : null,
            'defaults' => $isAdmin ? Permission::DEFAULTS : null,
            // Expanded server-side from the same SCHEMA, so every section is present
            // and explicitly denied where a preset doesn't name it (an absent section
            // would fall back to DEFAULTS and silently grant).
            'presets' => $isAdmin ? [
                'default' => Permission::DEFAULTS,
                'operations' => Permission::preset('operations'),
                'catalogue' => Permission::preset('catalogue'),
                'manager' => Permission::preset('manager'),
                'readonly' => Permission::preset('full', viewOnly: true),
                'full' => Permission::preset('full'),
            ] : null,
        ]);
    }

    /**
     * Set another staff member's password without knowing the old one.
     *
     * This is the recovery path for accounts created on a non-routable address
     * (see App\Support\MailAddress): they have no reset-by-email, by design, so
     * forgetting the password means asking someone here.
     *
     * 🔴 THE GUARDS ARE THE FEATURE. Route access is `permission:staff.reset_password`,
     * which an admin can grant to an editor — and an editor who can set someone
     * else's password can sign in as them. So:
     *
     *  - An editor can never reset an ADMIN. Without this, granting the
     *    permission would hand out the admin account itself: reset the admin's
     *    password, log in, done. Admins bypass it because they already hold
     *    everything the target does.
     *  - NOBODY resets their own password here, admins included. Own-password
     *    changes go through `password.update`, which demands the current one —
     *    otherwise a stolen session upgrades itself into permanent ownership.
     *  - The target must be staff. Customer passwords are not staff business,
     *    and customers have WhatsApp OTP to get themselves back in.
     *
     * The last-admin invariant is untouched: this changes a credential, never a
     * role, so the number of admins cannot move.
     */
    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->isStaff(), 403);

        $actor = $request->user();

        // Legitimate mistakes rather than attacks (the UI hides both), so they
        // answer with an explanation the reader can act on, not a bare 403.
        if ($user->id === $actor->id) {
            return back()->with('error', __('messages.admin.password_reset_self'));
        }

        if ($user->isAdmin() && ! $actor->isAdmin()) {
            return back()->with('error', __('messages.admin.password_reset_admin_only'));
        }

        $data = $request->validate([
            'password' => ['required', Password::defaults()],
        ]);

        $user->forceFill([
            'password' => $data['password'], // 'hashed' cast
            // A live "remember me" cookie would survive the reset and keep the
            // old holder signed in, which defeats resetting after a compromise.
            'remember_token' => Str::random(60),
        ])->save();

        $this->signOutEverywhere($user);

        return back()->with('success', __('messages.admin.password_reset_for', ['name' => $user->name]));
    }

    /**
     * Drop the target's live sessions, so the reset takes effect now rather than
     * whenever their current session happens to expire.
     *
     * Only meaningful on the database session driver (which production uses —
     * Railway's filesystem is ephemeral). On any other driver this is a no-op
     * rather than an error, because a reset that half-works is worse than one
     * that is honestly limited to the credential.
     */
    private function signOutEverywhere(User $user): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
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
