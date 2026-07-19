<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperContentPage
 */
class ContentPage extends Model
{
    protected $fillable = [
        'slug',
        'title_ar',
        'title_en',
        'body_ar',
        'body_en',
        'is_published',
    ];

    protected $casts = [
        'is_published' => 'boolean',
    ];

    /** The staff account that last saved this page (set explicitly on update, not fillable). */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
