@php
    $rtl = $locale === 'ar';
    $align = $rtl ? 'right' : 'left';
    $opposite = $rtl ? 'left' : 'right';
@endphp
{{--
    One shell for every STAFF alert email (new order, cancellation, expiring
    payment, return, product request, contact message). Built by
    App\Notifications\Concerns\SendsStaffMail, on the same branded layout as the
    customer emails instead of Laravel's default notification theme.

    ⚠️ The variable names avoid the keys MailMessage::toArray() already puts in
    the view data (greeting, introLines, outroLines, actionText, level, ...).
--}}
<x-mail-layout :locale="$locale" :store-name="$storeName" :logo-url="$logoUrl"
               :title="$heading" :preheader="$lines[0] ?? null">

    <h1 style="margin:0 0 16px 0; font-size:22px; color:#1b4e53; text-align:{{ $align }};">{{ $heading }}</h1>

    @foreach ($lines as $line)
        <p style="margin:0 0 12px 0;">{{ $line }}</p>
    @endforeach

    @if ($details)
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
               style="margin:12px 0 0 0; font-size:14px; border-top:1px solid #eee7dc;">
            @foreach ($details as $label => $value)
                <tr>
                    <td style="padding:8px 0; color:#6b6b6b; text-align:{{ $align }}; vertical-align:top; white-space:nowrap; border-bottom:1px solid #eee7dc; width:1%;">
                        {{ $label }}
                    </td>
                    {{-- dir="auto": a value may be Arabic, Latin (email, order number) or a
                         mix, and each should read in its own direction. --}}
                    <td dir="auto" style="padding:8px 12px; color:#2b2b2b; font-weight:bold; text-align:{{ $align }}; border-bottom:1px solid #eee7dc;">
                        {!! nl2br(e($value)) !!}
                    </td>
                </tr>
            @endforeach
        </table>
    @endif

    @if ($items)
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
               style="margin:20px 0 0 0; font-size:14px;">
            @foreach ($items as $item)
                <tr>
                    <td style="padding:8px 0; text-align:{{ $align }}; border-bottom:1px solid #eee7dc;">
                        <span style="color:#6b6b6b;">{{ $item['quantity'] }} &times;</span> {{ $item['name'] }}
                    </td>
                    <td style="padding:8px 0; text-align:{{ $opposite }}; white-space:nowrap; border-bottom:1px solid #eee7dc; color:#2b2b2b;">
                        {{ $item['total'] }}
                    </td>
                </tr>
            @endforeach
        </table>
    @endif

    @if ($actionUrl)
        <p style="margin:28px 0 0 0; text-align:{{ $align }};">
            <a href="{{ $actionUrl }}"
               style="display:inline-block; background-color:#af9056; color:#ffffff; text-decoration:none; padding:12px 24px; border-radius:6px; font-weight:bold; font-size:15px;">
                {{ $actionLabel }}
            </a>
        </p>
    @endif

    @if ($note)
        <p style="margin:24px 0 0 0; font-size:13px; color:#6b6b6b;">{{ $note }}</p>
    @endif

</x-mail-layout>
