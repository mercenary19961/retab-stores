<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Admin audit trail + revert (change-log v2, see CLAUDE.md → Change Log).
 *
 * Generic CRUD entries store before/after in `old_data`/`new_data` (dirty fields
 * only on updates); bespoke actions (SMACC stock import) keep their payload in
 * `changes`. A non-null `reverts_log_id` means this entry was produced by
 * reverting that entry — reverts are first-class history, so "redo" is just
 * reverting the mirror entry.
 *
 * @mixin IdeHelperActivityLog
 */
class ActivityLog extends Model
{
    public const ACTION_CREATED = 'created';
    public const ACTION_UPDATED = 'updated';
    public const ACTION_DELETED = 'deleted';
    public const ACTION_RESTORED = 'restored';

    /** Settings entries have no model row — this sentinel fills subject_type. */
    public const SUBJECT_SETTINGS = 'settings';

    protected $fillable = [
        'user_id',
        'action',
        'subject_type',
        'subject_id',
        'changes',
        'old_data',
        'new_data',
        'label',
        'reverts_log_id',
        'reverted_at',
        'reverted_by',
    ];

    protected $casts = [
        'changes' => 'array',
        'old_data' => 'array',
        'new_data' => 'array',
        'reverted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function revertedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reverted_by');
    }

    /** The entry this one was created by reverting (null for direct edits). */
    public function revertsLog(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverts_log_id');
    }

    public function isReverted(): bool
    {
        return $this->reverted_at !== null;
    }
}
