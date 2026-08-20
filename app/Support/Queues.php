<?php

namespace App\Support;

/**
 * Queue names.
 *
 * 🔑 Everything used to share one queue, which meant a marketing campaign put
 * every order confirmation, receipt and shipping notification behind it in the
 * same single-file line. A 5,000-recipient blast dispatches 5,000 individual
 * `SendWhatsappMessage` jobs, so a customer who paid mid-campaign waited behind
 * all of them for their confirmation.
 *
 * The split is by Meta's own message category, not a second invented concept:
 * `marketing` sends go to BULK, `utility` sends stay on the default queue. That
 * is the same distinction that already governs opt-in rules and template
 * approval, so the two cannot drift apart.
 *
 * ⚠️ Only the EXCEPTION is named. Transactional work never calls `onQueue()`, so
 * it lands on whatever `config('queue.connections.*.queue')` says (`DB_QUEUE`,
 * default `default`). Hardcoding 'default' here would silently break if that env
 * var were ever changed.
 *
 * 🔴 The worker must be told to serve both, cheapest-first:
 *
 *     php artisan queue:work --queue=default,bulk --tries=3 --timeout=60
 *
 * Laravel re-checks the list in order after every job, so a transactional
 * message arriving mid-campaign is picked up on the very next pass rather than
 * waiting for the campaign to drain. Omitting `--queue` entirely means the
 * worker only ever serves `default` and **bulk jobs would never run at all**.
 */
final class Queues
{
    /** Non-urgent, high-volume work that must never delay a transactional send. */
    public const BULK = 'bulk';

    /**
     * The queue list a worker should serve, in priority order. Used by the
     * deploy docs and by the test that pins the worker's configuration.
     */
    public static function workerList(): string
    {
        return config('queue.connections.'.config('queue.default').'.queue', 'default').','.self::BULK;
    }
}
