<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * The WhatsApp channel cannot deliver right now, so an operation that depends on a
 * message actually arriving must not proceed.
 *
 * 🔑 It is its own type purely so callers can key the error correctly. `OtpService`
 * already throws a plain RuntimeException for the resend cooldown, which genuinely
 * IS about the phone the customer typed and belongs on that field. This one is not
 * their fault and not their field — putting it there would mark a perfectly good
 * number as invalid, the same trap that made a valid contact-form message look
 * rejected when a Turnstile failure was keyed onto it.
 */
class WhatsAppUnavailableException extends RuntimeException {}
