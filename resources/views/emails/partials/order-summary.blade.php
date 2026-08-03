{{--
    Line items + money, shared by every customer order email.

    Product names come from the ORDER ITEM snapshot (product_name_ar/_en), never
    from the live product — the receipt must keep saying what was bought even if
    the catalogue entry is renamed or deleted. Falls back to Arabic, matching
    `useLocalized()` on the storefront.
--}}
@php
    $rtl = $locale === 'ar';
    $align = $rtl ? 'right' : 'left';
    $opposite = $rtl ? 'left' : 'right';
    $money = fn ($v) => number_format((float) $v, 2).' '.__('emails.common.currency');
@endphp

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
       style="margin:24px 0 0 0; border-collapse:collapse; font-size:14px;">
    @foreach ($items as $item)
        @php
            $name = $locale === 'en' && $item->product_name_en ? $item->product_name_en : $item->product_name_ar;
        @endphp
        <tr>
            <td style="padding:10px 0; border-bottom:1px solid #f0ece4; text-align:{{ $align }}; color:#2b2b2b;">
                {{ $name }}
                <span style="color:#8a8a8a;">&times; {{ $item->quantity }}</span>
            </td>
            <td style="padding:10px 0; border-bottom:1px solid #f0ece4; text-align:{{ $opposite }}; color:#2b2b2b; white-space:nowrap;">
                {{ $money($item->line_total) }}
            </td>
        </tr>
    @endforeach

    <tr>
        <td style="padding:10px 0 4px 0; text-align:{{ $align }}; color:#6b6b6b;">{{ __('emails.common.subtotal') }}</td>
        <td style="padding:10px 0 4px 0; text-align:{{ $opposite }}; color:#6b6b6b; white-space:nowrap;">{{ $money($order->subtotal) }}</td>
    </tr>

    @if ((float) $order->discount_total > 0)
        <tr>
            <td style="padding:4px 0; text-align:{{ $align }}; color:#6b6b6b;">{{ __('emails.common.discount') }}</td>
            <td style="padding:4px 0; text-align:{{ $opposite }}; color:#2e7d32; white-space:nowrap;">&minus;{{ $money($order->discount_total) }}</td>
        </tr>
    @endif

    <tr>
        <td style="padding:4px 0; text-align:{{ $align }}; color:#6b6b6b;">{{ __('emails.common.shipping') }}</td>
        <td style="padding:4px 0; text-align:{{ $opposite }}; color:#6b6b6b; white-space:nowrap;">{{ $money($order->shipping_fee) }}</td>
    </tr>

    <tr>
        <td style="padding:12px 0 0 0; border-top:2px solid #1b4e53; text-align:{{ $align }}; color:#1b4e53; font-size:16px; font-weight:bold;">
            {{ __('emails.common.total') }}
        </td>
        <td style="padding:12px 0 0 0; border-top:2px solid #1b4e53; text-align:{{ $opposite }}; color:#1b4e53; font-size:16px; font-weight:bold; white-space:nowrap;">
            {{ $money($order->total) }}
        </td>
    </tr>
</table>
