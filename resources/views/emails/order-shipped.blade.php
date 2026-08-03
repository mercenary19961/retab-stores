@php
    $rtl = $locale === 'ar';
    $align = $rtl ? 'right' : 'left';
@endphp

<x-mail-layout :locale="$locale" :store-name="$storeName" :support-phone="$supportPhone"
               :title="__('emails.shipped.heading')" :preheader="__('emails.shipped.intro')">

    <h1 style="margin:0 0 16px 0; font-size:22px; color:#1b4e53; text-align:{{ $align }};">
        {{ __('emails.shipped.heading') }}
    </h1>

    <p style="margin:0 0 8px 0;">{{ __('emails.common.greeting', ['name' => $order->customer_name]) }}</p>
    <p style="margin:0 0 16px 0;">{{ __('emails.shipped.intro') }}</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="margin:0 0 8px 0; background-color:#faf8f4; border:1px solid #e6dfd1; border-radius:8px;">
        <tr>
            <td style="padding:18px 20px; text-align:{{ $align }}; font-size:14px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:14px;">
                    <tr>
                        <td style="padding:4px 0; color:#6b6b6b; text-align:{{ $align }}; white-space:nowrap;">{{ __('emails.common.order_number') }}</td>
                        <td style="padding:4px 0; color:#2b2b2b; font-weight:bold; text-align:{{ $align }}; direction:ltr; unicode-bidi:embed;">{{ $order->order_number }}</td>
                    </tr>
                    @if ($order->carrier)
                        <tr>
                            <td style="padding:4px 0; color:#6b6b6b; text-align:{{ $align }}; white-space:nowrap;">{{ __('emails.shipped.carrier') }}</td>
                            <td style="padding:4px 0; color:#2b2b2b; font-weight:bold; text-align:{{ $align }};">{{ $order->carrier }}</td>
                        </tr>
                    @endif
                    @if ($order->tracking_number)
                        <tr>
                            <td style="padding:4px 0; color:#6b6b6b; text-align:{{ $align }}; white-space:nowrap;">{{ __('emails.shipped.tracking_number') }}</td>
                            {{-- Tracking codes are Latin identifiers — pin them LTR inside an RTL page. --}}
                            <td style="padding:4px 0; color:#2b2b2b; font-weight:bold; text-align:{{ $align }}; direction:ltr; unicode-bidi:embed;">{{ $order->tracking_number }}</td>
                        </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

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
