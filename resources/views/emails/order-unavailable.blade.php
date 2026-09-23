@php
    $rtl = $locale === 'ar';
    $align = $rtl ? 'right' : 'left';

    // Which refund sentence is true depends on how they paid: a card is refunded
    // by the gateway, a Tamara plan is voided so nothing is owed, and a bank
    // transfer has to be sent back by hand. Saying the wrong one would have the
    // customer watching a statement that will never change.
    $refundKey = match ($order->payment_method?->value) {
        'card' => 'refund_card',
        'tamara' => 'refund_tamara',
        default => 'refund_transfer',
    };
@endphp

<x-mail-layout :locale="$locale" :store-name="$storeName" :support-phone="$supportPhone"
               :title="__('emails.unavailable.heading')" :preheader="__('emails.unavailable.intro')">

    <h1 style="margin:0 0 16px 0; font-size:22px; color:#1b4e53; text-align:{{ $align }};">
        {{ __('emails.unavailable.heading') }}
    </h1>

    <p style="margin:0 0 8px 0;">{{ __('emails.common.greeting', ['name' => $order->customer_name]) }}</p>
    <p style="margin:0 0 16px 0;">{{ __('emails.unavailable.intro') }}</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="margin:0 0 16px 0; background-color:#faf8f4; border:1px solid #e6dfd1; border-radius:8px;">
        <tr>
            <td style="padding:18px 20px; text-align:{{ $align }}; font-size:14px;">
                <p style="margin:0 0 8px 0; font-weight:bold; color:#1b4e53;">{{ __('emails.unavailable.refund_heading') }}</p>
                <p style="margin:0; color:#2b2b2b;">{{ __('emails.unavailable.'.$refundKey) }}</p>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 4px 0; color:#6b6b6b; font-size:14px; text-align:{{ $align }};">
        {{ __('emails.common.order_number') }}
        <span style="color:#2b2b2b; font-weight:bold; direction:ltr; unicode-bidi:embed;">{{ $order->order_number }}</span>
    </p>

    @include('emails.partials.order-summary')

    <p style="margin:24px 0 0 0; text-align:{{ $align }};">{{ __('emails.unavailable.sorry') }}</p>

</x-mail-layout>
