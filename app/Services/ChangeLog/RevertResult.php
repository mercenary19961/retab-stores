<?php

namespace App\Services\ChangeLog;

use App\Models\ActivityLog;

/**
 * Outcome of ChangeLogService::revert — always says WHY it failed instead of a
 * blind bool, so controllers can flash a precise, honest message.
 */
final readonly class RevertResult
{
    public const REASON_ALREADY_REVERTED = 'already_reverted';
    public const REASON_NOT_REVERTABLE = 'not_revertable';
    public const REASON_SUBJECT_MISSING = 'subject_missing';
    public const REASON_STATE_CHANGED = 'state_changed';
    public const REASON_CONFLICT = 'conflict';
    public const REASON_FAILED = 'failed';

    /**
     * @param  list<string>  $conflicts  Humanized field labels that changed since the entry.
     * @param  ActivityLog|null  $mirror  The new history entry the revert created.
     * @param  int|null  $blockerId  The later change to undo first (null when the chain is too long to guide).
     * @param  string|null  $blockerLabel  Human label of that blocking change.
     * @param  int  $chainDepth  How many later un-reverted edits touch the conflicting fields.
     * @param  string|null  $editUrl  Where to edit the item directly (offered when the chain is too long).
     */
    private function __construct(
        public bool $ok,
        public ?string $reason = null,
        public array $conflicts = [],
        public ?ActivityLog $mirror = null,
        public ?int $blockerId = null,
        public ?string $blockerLabel = null,
        public int $chainDepth = 0,
        public ?string $editUrl = null,
    ) {}

    public static function ok(?ActivityLog $mirror): self
    {
        return new self(true, null, [], $mirror);
    }

    public static function fail(string $reason): self
    {
        return new self(false, $reason);
    }

    /** @param list<string> $fields */
    public static function conflict(
        array $fields,
        ?int $blockerId = null,
        ?string $blockerLabel = null,
        int $chainDepth = 0,
        ?string $editUrl = null,
    ): self {
        return new self(false, self::REASON_CONFLICT, $fields, null, $blockerId, $blockerLabel, $chainDepth, $editUrl);
    }
}
