<?php

namespace App\Jobs;

use App\Models\WhatsappMessage;
use App\Services\WhatsApp\WhatsAppGateway;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Performs the Meta Cloud API call for one already-recorded ledger row.
 *
 * Why this exists: the transactional sends (order confirmation, admin alerts,
 * return updates) used to run as INLINE HTTP calls inside the customer's
 * request — one per recipient — so a slow Meta API added seconds to checkout and
 * a transient failure lost the alert with nothing but a log line. The ledger row
 * is still written synchronously (the admin WhatsApp log shows the attempt
 * immediately as `queued`); only the network call is deferred, with retries.
 *
 * ⚠️ Params are passed in, NOT read back off the row: the ledger deliberately
 * REDACTS sensitive params (OTP codes), so the stored payload is not a faithful
 * send payload. The OTP path stays synchronous anyway — see
 * WhatsAppService::dispatch — which also keeps plaintext codes out of the queue.
 */
class SendWhatsappMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** A Meta blip clears in seconds; a quota/permission issue needs longer. */
    public array $backoff = [30, 120, 600];

    /**
     * @param  list<string>  $params
     */
    public function __construct(
        public int $messageId,
        public array $params,
    ) {}

    public function handle(WhatsAppGateway $gateway): void
    {
        $message = WhatsappMessage::find($this->messageId);

        // Idempotent: a retry after a send that actually landed (or a row that
        // has since been pruned) must not send a second message.
        if (! $message || $message->status === 'sent') {
            return;
        }

        try {
            $wamId = $gateway->sendTemplate(
                $message->recipient,
                $message->template,
                (string) ($message->payload['language'] ?? config('services.whatsapp.default_language', 'ar')),
                $this->params,
            );

            $message->update(['status' => 'sent', 'wam_id' => $wamId, 'sent_at' => now(), 'error' => null]);
        } catch (\Throwable $e) {
            // Record the failure immediately so the ledger is never silently
            // stuck on `queued`; a later successful retry flips it back to sent.
            $message->update(['status' => 'failed', 'error' => Str::limit($e->getMessage(), 1000)]);

            Log::warning('WhatsApp send failed', [
                'template' => $message->template,
                'to' => $message->recipient,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
            ]);

            // Rethrow so the queue retries with backoff — but NEVER under the
            // `sync` driver, where the exception would surface inside the
            // caller's request. A Meta outage must not break a checkout.
            if (($this->job?->getConnectionName() ?? config('queue.default')) !== 'sync') {
                throw $e;
            }
        }
    }
}
