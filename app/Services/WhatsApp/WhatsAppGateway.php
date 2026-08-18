<?php

namespace App\Services\WhatsApp;

/**
 * Transport contract for outbound WhatsApp messages. Implementations speak the
 * wire protocol only (Meta Cloud API, or a no-op log driver for dev/tests) — they
 * know nothing about orders or persistence. {@see WhatsAppService} sits on top and
 * records every send to the whatsapp_messages ledger.
 *
 * Mirrors the PaymentGateway / ShippingGateway abstraction: swap the binding in
 * AppServiceProvider to change providers without touching callers.
 */
interface WhatsAppGateway
{
    /**
     * Send an approved template message. Business-initiated messages outside the
     * customer's 24h reply window MUST be templates (Meta rule).
     *
     * @param  string  $to  recipient phone in E.164 (no '+')
     * @param  string  $template  approved template name
     * @param  string  $language  template language code (e.g. 'ar')
     * @param  list<string>  $params  ordered body placeholder values ({{1}}, {{2}}, …)
     * @return string the provider message id (wam_id)
     *
     * @throws \Throwable on transport failure
     */
    public function sendTemplate(string $to, string $template, string $language, array $params = []): string;

    /**
     * Send a free-form text message. Only valid INSIDE the 24h customer window;
     * used for replies / OTP, not business-initiated marketing.
     *
     * @return string the provider message id (wam_id)
     *
     * @throws \Throwable on transport failure
     */
    public function sendText(string $to, string $body): string;

    /**
     * Whether a message handed to this transport will actually reach a phone.
     *
     * 🔑 This exists because the log driver SUCCEEDS. It writes the message to the
     * application log and returns a synthetic wam_id, so every caller sees a clean
     * send — which is exactly right for dev, and silently catastrophic for anything
     * the customer is waiting on. The OTP flow used to advance to "enter the code"
     * against a code that had gone to a log file.
     *
     * So a caller whose feature is USELESS without real delivery (the sign-in code)
     * must ask this first and refuse. Callers that merely notify (order updates,
     * campaigns) do not — a logged notification in dev is the intended behaviour.
     */
    public function isLive(): bool;
}
