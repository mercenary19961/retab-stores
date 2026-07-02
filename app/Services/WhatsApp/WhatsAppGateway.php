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
     * @param  string  $to        recipient phone in E.164 (no '+')
     * @param  string  $template  approved template name
     * @param  string  $language  template language code (e.g. 'ar')
     * @param  list<string>  $params  ordered body placeholder values ({{1}}, {{2}}, …)
     * @return string  the provider message id (wam_id)
     *
     * @throws \Throwable on transport failure
     */
    public function sendTemplate(string $to, string $template, string $language, array $params = []): string;

    /**
     * Send a free-form text message. Only valid INSIDE the 24h customer window;
     * used for replies / OTP, not business-initiated marketing.
     *
     * @return string  the provider message id (wam_id)
     *
     * @throws \Throwable on transport failure
     */
    public function sendText(string $to, string $body): string;
}
