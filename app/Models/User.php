<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\Permission;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @mixin IdeHelperUser
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'phone_verified_at',
        'password',
        'avatar',
        'city',
        'locale',
        'admin_theme',
        'ui_preferences',
        'whatsapp_opt_in',
        'whatsapp_opt_in_at',
        'confirmed_purchases_count',
    ];

    // NOTE: `role` and `permissions` are deliberately NOT mass-assignable — they
    // are privilege fields. Set them only with trusted, server-controlled values
    // via forceCreate/forceFill (see Admin\UserController, AdminUserSeeder). This
    // prevents any future `->update($request->...)` from escalating a user.

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
            'whatsapp_opt_in' => 'boolean',
            'whatsapp_opt_in_at' => 'datetime',
            'confirmed_purchases_count' => 'integer',
            'ui_preferences' => 'array',
            'permissions' => 'array',
        ];
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function wishlists(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isEditor(): bool
    {
        return $this->role === 'editor';
    }

    /**
     * Admin or editor — i.e. has back-office (admin panel) access.
     */
    public function isStaff(): bool
    {
        return in_array($this->role, ['admin', 'editor'], true);
    }

    /**
     * Back-office users (admins + editors) — the recipients of admin-panel
     * notifications (new orders, return requests, …).
     *
     * @param  Builder<User>  $query
     */
    public function scopeStaff(Builder $query): void
    {
        $query->whereIn('role', ['admin', 'editor']);
    }

    /**
     * Check a "section.action" permission. Admins always pass; editors check
     * their resolved set.
     *
     * 🔑 Goes through resolvedPermissions() rather than reading users.permissions
     * directly, and that is load-bearing. The stored array is a snapshot of the
     * SCHEMA as it stood when an admin last saved the grid, so a section added
     * afterwards is simply missing from it. Reading the raw array turned that
     * absence into a denial, while the sidebar (which renders from the resolved
     * set) showed the entry — so every existing editor got a visible menu item
     * that 403'd, and needed a manual re-grant on each new admin page. Sharing
     * one resolver means the two can no longer disagree.
     */
    public function hasPermission(string $permission): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if (! $this->isEditor()) {
            return false;
        }

        [$section, $action] = array_pad(explode('.', $permission, 2), 2, '');

        return (bool) ($this->resolvedPermissions()[$section][$action] ?? false);
    }

    /**
     * The editor's effective permissions — stored grants merged OVER the
     * defaults. Empty for non-editors (admins have implicit full access).
     *
     * The merge direction matters both ways: an explicit false in the stored
     * grants still revokes (the grid writes every action), while a section the
     * grants have never heard of inherits its default.
     *
     * @return array<string, array<string, bool>>
     */
    public function resolvedPermissions(): array
    {
        if (! $this->isEditor()) {
            return [];
        }

        $result = Permission::DEFAULTS;

        foreach (($this->permissions ?? []) as $section => $actions) {
            foreach ((array) $actions as $action => $value) {
                $result[$section][$action] = (bool) $value;
            }
        }

        return $result;
    }
}
