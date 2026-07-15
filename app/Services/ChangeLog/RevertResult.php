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
     */
    private function __construct(
        public bool $ok,
        public ?string $reason = null,
        public array $conflicts = [],
        public ?ActivityLog $mirror = null,
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
    public static function conflict(array $fields): self
    {
        return new self(false, self::REASON_CONFLICT, $fields);
    }
}
