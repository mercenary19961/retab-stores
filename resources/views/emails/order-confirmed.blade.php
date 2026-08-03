@php
    $rtl = $locale === 'ar';
    $align = $rtl ? 'right' : 'left';
@endphp

<x-mail-layout :locale="$locale" :store-name="$storeName" :support-phone="$supportPhone"
               :title="__('emails.confirmed.heading')" :preheader="__('emails.confirmed.intro')">

    <h1 style="margin:0 0 16px 0; font-size:22px; color:#1b4e53; text-align:{{ $align }};">
        {{ __('emails.confirmed.heading') }}
    </h1>

    <p style="margin:0 0 8px 0;">{{ __('emails.common.greeting', ['name' => $order->customer_name]) }}</p>
    <p style="margin:0 0 16px 0;">{{ __('emails.confirmed.intro') }}</p>

    <p style="margin:0; color:#6b6b6b; font-size:14px;">
        {{ __('emails.common.order_number') }}:
        <strong style="color:#2b2b2b; direction:ltr; unicode-bidi:embed;">{{ $order->order_number }}</strong>
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
