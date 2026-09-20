@php
    $locale = app()->getLocale();
    $rtl = $locale === 'ar';
    $address = $order->shipping_address ?? [];
    // Most specific first, the way a courier reads an address.
    $addressLines = array_values(array_filter([
        $address['building'] ?? null,
        $address['street'] ?? null,
        $address['district'] ?? null,
        trim(($address['city'] ?? '').' '.($address['postal_code'] ?? '')),
        $address['country'] ?? null,
    ]));
    $name = fn ($ar, $en) => $locale === 'en' && filled($en) ? $en : $ar;
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('packing_slip.title') }} {{ $order->order_number }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 32px; font-family: system-ui, -apple-system, 'Segoe UI', Tahoma, sans-serif; color: #111; background: #fff; font-size: 14px; }
        .sheet { max-width: 760px; margin: 0 auto; }
        header { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; border-bottom: 2px solid #111; padding-bottom: 16px; }
        h1 { margin: 0; font-size: 22px; }
        .muted { color: #555; }
        .number { font-family: ui-monospace, Menlo, Consolas, monospace; font-size: 20px; font-weight: 700; direction: ltr; unicode-bidi: embed; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin: 24px 0; }
        .label { font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #555; margin-bottom: 6px; }
        .ltr { direction: ltr; unicode-bidi: embed; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px 8px; border-bottom: 1px solid #ddd; text-align: start; vertical-align: top; }
        th { font-size: 11px; letter-spacing: .06em; text-transform: uppercase; color: #555; border-bottom: 2px solid #111; }
        td.qty, th.qty { text-align: center; width: 70px; }
        td.qty { font-size: 18px; font-weight: 700; }
        td.box, th.box { text-align: center; width: 60px; }
        .check { display: inline-block; width: 18px; height: 18px; border: 2px solid #111; border-radius: 3px; }
        .option { display: block; color: #555; font-size: 12px; margin-top: 2px; }
        .sku { font-family: ui-monospace, Menlo, Consolas, monospace; font-size: 12px; direction: ltr; unicode-bidi: embed; }
        .total { margin-top: 12px; text-align: end; font-weight: 700; }
        .sign { display: grid; grid-template-columns: 1fr 1fr; gap: 32px; margin-top: 48px; }
        .sign div { border-top: 1px solid #111; padding-top: 6px; color: #555; font-size: 12px; }
        .print { position: fixed; inset-inline-end: 24px; top: 24px; padding: 8px 16px; border: 1px solid #111; background: #fff; border-radius: 8px; font: inherit; cursor: pointer; }
        @media print {
            body { padding: 0; }
            .print { display: none; }
        }
    </style>
</head>
<body>
    <button type="button" class="print" onclick="window.print()">{{ __('packing_slip.print') }}</button>

    <div class="sheet">
        <header>
            <div>
                <h1>{{ __('packing_slip.title') }}</h1>
                <div class="muted">{{ __('packing_slip.store') }}</div>
            </div>
            <div style="text-align: end">
                <div class="label">{{ __('packing_slip.order') }}</div>
                <div class="number">{{ $order->order_number }}</div>
                <div class="muted ltr">{{ $order->created_at?->format('Y-m-d H:i') }}</div>
            </div>
        </header>

        <div class="grid">
            <div>
                <div class="label">{{ __('packing_slip.ship_to') }}</div>
                <div><strong><bdi>{{ $order->customer_name }}</bdi></strong></div>
                @foreach ($addressLines as $line)
                    <div><bdi>{{ $line }}</bdi></div>
                @endforeach
            </div>
            <div>
                <div class="label">{{ __('packing_slip.phone') }}</div>
                <div class="ltr">{{ $order->customer_phone }}</div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th class="box">{{ __('packing_slip.packed') }}</th>
                    <th>{{ __('packing_slip.product') }}</th>
                    <th>{{ __('packing_slip.sku') }}</th>
                    <th class="qty">{{ __('packing_slip.qty') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($order->items as $item)
                    <tr>
                        <td class="box"><span class="check"></span></td>
                        <td>
                            <bdi>{{ $name($item->product_name_ar, $item->product_name_en) }}</bdi>
                            @if ($item->option_label_ar)
                                <span class="option"><bdi>{{ $name($item->option_label_ar, $item->option_label_en) }}</bdi></span>
                            @endif
                        </td>
                        <td class="sku">{{ $item->sku ?? '—' }}</td>
                        <td class="qty">{{ $item->quantity }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="total">{{ __('packing_slip.total_units') }}: {{ $order->items->sum('quantity') }}</div>

        <div class="sign">
            <div>{{ __('packing_slip.packed_by') }}</div>
            <div>{{ __('packing_slip.checked_by') }}</div>
        </div>
    </div>
</body>
</html>
