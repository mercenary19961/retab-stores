@php
    $rtl = $locale === 'ar';
    $align = $rtl ? 'right' : 'left';
@endphp

<x-mail-layout :locale="$locale" :store-name="$storeName" :support-phone="$supportPhone"
               :title="__('emails.delivered.heading')" :preheader="__('emails.delivered.intro')">

    <h1 style="margin:0 0 16px 0; font-size:22px; color:#1b4e53; text-align:{{ $align }};">
        {{ __('emails.delivered.heading') }}
    </h1>

    <p style="margin:0 0 8px 0;">{{ __('emails.common.greeting', ['name' => $order->customer_name]) }}</p>
    <p style="margin:0 0 16px 0;">{{ __('emails.delivered.intro') }}</p>

    {{-- The real job of this email: the return window starts now, and it is
         measured from delivery whether or not the customer was told. --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="margin:0 0 16px 0; background-color:#faf8f4; border:1px solid #e6dfd1; border-radius:8px;">
        <tr>
            <td style="padding:18px 20px; text-align:{{ $align }}; font-size:14px;">
                <p style="margin:0 0 8px 0; font-weight:bold; color:#1b4e53;">{{ __('emails.delivered.returns_heading') }}</p>
                <p style="margin:0; color:#2b2b2b;">{{ __('emails.delivered.returns_intro', ['days' => $returnDays]) }}</p>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 4px 0; color:#6b6b6b; font-size:14px; text-align:{{ $align }};">
        {{ __('emails.common.order_number') }}
        <span style="color:#2b2b2b; font-weight:bold; direction:ltr; unicode-bidi:embed;">{{ $order->order_number }}</span>
    </p>

    @include('emails.partials.order-summary')

    @if ($orderUrl)
    <p style="margin:28px 0 0 0; text-align:{{ $align }};">
        <a href="{{ $orderUrl }}"
           style="display:inline-block; background-color:#af9056; color:#ffffff; text-decoration:none; padding:12px 24px; border-radius:6px; font-weight:bold; font-size:15px;">
            {{ __('emails.common.view_order') }}
        </a>
    </p>
    @endif

</x-mail-layout>
