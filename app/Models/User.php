<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
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
    /** @use HasFactory<\Database\Factories\UserFactory> */
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
        'role',
        'avatar',
        'city',
        'locale',
        'admin_theme',
        'ui_preferences',
        'permissions',
        'whatsapp_opt_in',
        'whatsapp_opt_in_at',
        'confirmed_purchases_count',
    ];

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
     * their granted set (falling back to the defaults while unset).
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
        $perms = $this->permissions ?? \App\Support\Permission::DEFAULTS;

        return (bool) ($perms[$section][$action] ?? false);
    }

    /**
     * The editor's effective permissions — stored grants merged over the
     * defaults. Empty for non-editors (admins have implicit full access).
     *
     * @return array<string, array<string, bool>>
     */
    public function resolvedPermissions(): array
    {
        if (! $this->isEditor()) {
            return [];
        }

        $result = \App\Support\Permission::DEFAULTS;

        foreach (($this->permissions ?? []) as $section => $actions) {
            foreach ((array) $actions as $action => $value) {
                $result[$section][$action] = (bool) $value;
            }
        }

        return $result;
    }
}
