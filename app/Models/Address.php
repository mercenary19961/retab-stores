<?php

namespace App\Models;

use App\Support\NationalAddress;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperAddress
 */
class Address extends Model
{
    protected $fillable = [
        'user_id',
        'label',
        'country',
        'city',
        'district',
        'street',
        'building',
        'postal_code',
        // Saudi National Address short code (RRMD7708). Optional — see the
        // migration for why it must never become required.
        'short_address',
        'phone',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Always store the code in its canonical form, whatever the customer typed.
     * "rrmd 7708", "RRMD-7708" and "RRMD7708" are the same address, and storing
     * them differently would make a saved address look like a new one.
     */
    public function setShortAddressAttribute(?string $value): void
    {
        $this->attributes['short_address'] = NationalAddress::normalize($value);
    }

    /**
     * The order snapshot shape. Orders keep a COPY rather than a reference, so
     * editing or deleting a saved address can never rewrite what an old order
     * was shipped to.
     *
     * @return array<string,string|null>
     */
    public function toOrderSnapshot(): array
    {
        return [
            'country' => $this->country,
            'city' => $this->city,
            'district' => $this->district,
            'street' => $this->street,
            'building' => $this->building,
            'short_address' => $this->short_address,
            'phone' => $this->phone,
        ];
    }

    /** One line for a picker, most specific first — how a courier reads it. */
    public function summary(): string
    {
        return implode('، ', array_filter([
            $this->building,
            $this->street,
            $this->district,
            $this->city,
            $this->short_address,
        ]));
    }
}
