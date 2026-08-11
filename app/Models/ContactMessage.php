<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A submission of the Contact Us page's "أرسل رسالتك" form. Deliberately not tied
 * to a user_id (see the migration) — it's a one-off enquiry, not account history.
 *
 * @mixin IdeHelperContactMessage
 */
class ContactMessage extends Model
{
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'inquiry_type',
        'message',
        'ip',
        'handled_at',
    ];

    protected $casts = [
        'handled_at' => 'datetime',
    ];
}
