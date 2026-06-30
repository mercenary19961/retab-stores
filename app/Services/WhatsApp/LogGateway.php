<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Dev / test transport: logs the message instead of calling Meta and returns a
 * synthetic wam_id. The default driver (no WhatsApp credentials needed) so the
 * order flow works end-to-end locally. {@see WhatsAppService} still records the
 * send to the whatsapp_messages ledger exactly as it would in production.
 */
class LogGateway implements WhatsAppGateway
{
    public function sendTemplate(string $to, string $template, string $language, array $params = []): string
    {
        Log::info('WhatsApp (log driver) template', compact('to', 'template', 'language', 'params'));

        return 'log-' . Str::uuid();
    }

    public function sendText(string $to, string $body): string
    {
        Log::info('WhatsApp (log driver) text', compact('to', 'body'));

        return 'log-' . Str::uuid();
    }
}
