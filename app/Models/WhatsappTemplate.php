<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsappTemplate extends Model
{
    public const STATUSES = ['draft', 'pending', 'approved', 'rejected'];

    protected $fillable = [
        'name',
        'language',
        'category',
        'body',
        'param_count',
        'status',
    ];

    protected $casts = [
        'param_count' => 'integer',
    ];

    public function campaigns(): HasMany
    {
        return $this->hasMany(WhatsappCampaign::class);
    }

    /** Only Meta-approved templates may be sent outside the 24h window. */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }
}
