@php
    $rtl = $locale === 'ar';
    $align = $rtl ? 'right' : 'left';
@endphp

<x-mail-layout :locale="$locale" :store-name="$storeName" :support-phone="$supportPhone"
               :title="__('emails.placed.heading')" :preheader="__('emails.placed.intro')">

    <h1 style="margin:0 0 16px 0; font-size:22px; color:#1b4e53; text-align:{{ $align }};">
        {{ __('emails.placed.heading') }}
    </h1>

    <p style="margin:0 0 8px 0;">{{ __('emails.common.greeting', ['name' => $order->customer_name]) }}</p>
    <p style="margin:0 0 16px 0;">{{ __('emails.placed.intro') }}</p>

    <p style="margin:0; color:#6b6b6b; font-size:14px;">
        {{ __('emails.common.order_number') }}:
        <strong style="color:#2b2b2b; direction:ltr; unicode-bidi:embed;">{{ $order->order_number }}</strong>
    </p>

    @include('emails.partials.order-summary')

    {{-- Bank transfer is the reason this email matters most: without it the IBAN
         and the reference number exist only on a page view the customer can close. --}}
    @if ($bank)
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
               style="margin:28px 0 0 0; background-color:#faf8f4; border:1px solid #e6dfd1; border-radius:8px;">
            <tr>
                <td style="padding:20px; text-align:{{ $align }};">
                    <h2 style="margin:0 0 8px 0; font-size:17px; color:#1b4e53;">{{ __('emails.placed.bank_heading') }}</h2>
                    <p style="margin:0 0 16px 0; font-size:14px;">
                        {{ __('emails.placed.bank_intro', [
                            'amount' => number_format((float) $order->total, 2),
                            'number' => $order->order_number,
                        ]) }}
                    </p>

                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:14px;">
                        @foreach ([
                            'bank_name' => $bank['bank_name'] ?? null,
                            'bank_beneficiary' => $bank['beneficiary'] ?? null,
                            'bank_iban' => $bank['iban'] ?? null,
                            'bank_account' => $bank['account'] ?? null,
                        ] as $key => $value)
                            @if ($value)
                                <tr>
                                    <td style="padding:4px 0; color:#6b6b6b; text-align:{{ $align }}; white-space:nowrap;">
                                        {{ __("emails.placed.$key") }}
                                    </td>
                                    {{-- IBAN/account are Latin-digit identifiers: force LTR so RTL
                                         reordering can't visually scramble them mid-copy. --}}
                                    <td style="padding:4px 0; color:#2b2b2b; font-weight:bold; text-align:{{ $align }}; direction:ltr; unicode-bidi:embed;">
                                        {{ $value }}
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </table>

                    <p style="margin:16px 0 0 0; font-size:13px; color:#6b6b6b;">{{ __('emails.placed.bank_after') }}</p>
                </td>
            </tr>
        </table>
    @endif

    {{-- Null for guest orders: the storefront gates the order page on session
         state, so a link opened later would 403. See OrderMail::orderUrl(). --}}
    @if ($orderUrl)
    <p style="margin:28px 0 0 0; text-align:{{ $align }};">
        <a href="{{ $orderUrl }}"
           style="display:inline-block; background-color:#af9056; color:#ffffff; text-decoration:none; padding:12px 24px; border-radius:6px; font-weight:bold; font-size:15px;">
            {{ __('emails.common.view_order') }}
        </a>
    </p>
    @endif

</x-mail-layout>
