<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A curated store-level testimonial (Google Maps review or manual). The homepage
 * "آراء العملاء" section pulls a random set of the active ones each request.
 *
 * @mixin IdeHelperClientReview
 */
class ClientReview extends Model
{
    protected $fillable = [
        'author_name',
        'body',
        'rating',
        'language',
        'source',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'rating' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];
}
