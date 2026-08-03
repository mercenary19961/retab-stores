@props([
    'locale' => 'ar',
    'storeName' => '',
    'supportPhone' => null,
    'title' => '',
    'preheader' => null,
])
{{--
    Shared shell for customer transactional email.

    Deliberately hand-rolled rather than Laravel's markdown mail theme: the store
    is Arabic-first, and the whole page has to flip to RTL (direction, alignment,
    and the logical padding on every cell). Driving that through the published
    theme means fighting its LTR-baked CSS on every component.

    Email-client constraints this file is written around:
      • table layout + inline styles — Outlook ignores flex/grid, and several
        clients strip <style> blocks, so nothing structural may live in <head>.
      • `dir` is set on <html> AND on the outer table: clients that re-wrap the
        body (Gmail) drop the <html> attributes entirely.
      • no web fonts — Thmanyah is licensed for web embedding on our own origin,
        not for redistribution through mail clients, so system stacks only.
--}}
@php
    $rtl = $locale === 'ar';
    $align = $rtl ? 'right' : 'left';
    $font = $rtl
        ? "'Segoe UI', Tahoma, 'Geeza Pro', 'Arabic Typesetting', sans-serif"
        : "'Segoe UI', Roboto, Helvetica, Arial, sans-serif";
@endphp
<html dir="{{ $rtl ? 'rtl' : 'ltr' }}" lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f5f1ea; -webkit-text-size-adjust:100%;">
    {{-- Preheader: the grey snippet shown after the subject in most inboxes. --}}
    @if ($preheader)
        <div style="display:none; font-size:1px; color:#f5f1ea; line-height:1px; max-height:0; max-width:0; opacity:0; overflow:hidden;">{{ $preheader }}</div>
    @endif

    <table role="presentation" dir="{{ $rtl ? 'rtl' : 'ltr' }}" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="background-color:#f5f1ea; padding:24px 12px; font-family:{{ $font }};">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                       style="max-width:600px; background-color:#ffffff; border-radius:12px; overflow:hidden;">

                    <tr>
                        <td style="background-color:#1b4e53; padding:24px; text-align:center;">
                            <span style="color:#ffffff; font-size:20px; font-weight:bold; letter-spacing:0.5px;">{{ $storeName }}</span>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:32px 28px; text-align:{{ $align }}; color:#2b2b2b; font-size:15px; line-height:1.7;">
                            {{ $slot }}
                        </td>
                    </tr>

                    <tr>
                        <td style="background-color:#faf8f4; padding:20px 28px; text-align:{{ $align }}; color:#6b6b6b; font-size:12px; line-height:1.7; border-top:1px solid #eee7dc;">
                            @if ($supportPhone)
                                <div style="margin:0 0 6px 0;">{{ __('emails.common.help', ['phone' => $supportPhone]) }}</div>
                            @endif
                            <div>&copy; {{ date('Y') }} {{ $storeName }}. {{ __('emails.common.footer_rights') }}</div>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
