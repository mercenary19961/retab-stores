<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Send one real email to prove the mail transport works end to end — the piece
 * `integrations:check` can't tell you (a valid API key still doesn't mean the
 * from-domain is verified or that the message lands in an inbox).
 *
 *   php artisan mail:test you@example.com
 *   php artisan mail:test you@example.com --from=orders@retabstore.com
 *   php artisan mail:test delivered@resend.dev        # Resend's simulator
 *
 * It sends through the named mailer EXPLICITLY (default `resend`) rather than
 * whatever MAIL_MAILER happens to be, so local dev can stay on `log` — that
 * matters because the seeded dev staff use fake addresses (@retab.com.sa) and
 * bouncing those off a fresh Resend account would dent its sending reputation.
 *
 * Resend's simulator recipients are reputation-safe and never reach a real
 * mailbox: delivered@resend.dev, bounced@resend.dev, complained@resend.dev.
 * A 403 "domain is not verified" here is the definitive answer on DNS status.
 */
class SendTestMail extends Command
{
    protected $signature = 'mail:test
        {to : Recipient address}
        {--mailer=resend : Which configured mailer to send through}
        {--from= : Override MAIL_FROM_ADDRESS for this send}';

    protected $description = 'Send a test email through a specific mailer to verify the transport and from-domain';

    public function handle(): int
    {
        $to = (string) $this->argument('to');
        $mailer = (string) $this->option('mailer');
        $from = $this->option('from') ? (string) $this->option('from') : (string) config('mail.from.address');

        $this->newLine();
        $this->line(sprintf('   %-14s <comment>%s</comment>', 'mailer', $mailer));
        $this->line(sprintf('   %-14s <comment>%s</comment>', 'from', $from));
        $this->line(sprintf('   %-14s <comment>%s</comment>', 'to', $to));
        $this->newLine();

        $body = implode("\n", [
            'This is a test message from Retab Stores.',
            '',
            'mailer: '.$mailer,
            'from:   '.$from,
            'app:    '.config('app.url'),
            'sent:   '.now()->toDateTimeString(),
            '',
            'If you received this, transactional email is working.',
        ]);

        try {
            Mail::mailer($mailer)->raw($body, function ($message) use ($to, $from) {
                $message->to($to)
                    ->from($from, (string) config('mail.from.name'))
                    ->subject('Retab Stores — mail configuration test');
            });
        } catch (\Throwable $e) {
            $this->error('Send FAILED: '.$e->getMessage());
            $this->newLine();
            $this->line('  <comment>Common causes:</comment>');
            $this->line('   • 403 "domain is not verified" → add the domain in Resend and paste its DNS records, or send from onboarding@resend.dev');
            $this->line('   • 401/400 invalid key          → check RESEND_KEY');
            $this->line('   • restricted key               → a send-only key cannot use other endpoints, but sending should still work');

            return self::FAILURE;
        }

        $this->info('Accepted by the transport. Check the inbox (and the spam folder).');

        if ($mailer === 'log') {
            $this->line('  <comment>Mailer is `log` — nothing was actually sent; see storage/logs/laravel.log.</comment>');
        }

        return self::SUCCESS;
    }
}
