<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsappCampaign extends Model
{
    protected $fillable = [
        'whatsapp_template_id',
        'params',
        'segment',
        'audience_count',
        'status',
        'sent_by',
        'sent_at',
    ];

    protected $casts = [
        'params' => 'array',
        'audience_count' => 'integer',
        'sent_at' => 'datetime',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(WhatsappTemplate::class, 'whatsapp_template_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsappMessage::class, 'campaign_id');
    }

    /** Delivery funnel from the ledger: queued/sent/delivered/read/failed => count. */
    public function stats(): array
    {
        return $this->messages()
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status')
            ->all();
    }
}
