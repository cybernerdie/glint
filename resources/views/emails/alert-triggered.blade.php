<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Glint Alert: {{ $ruleName }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">

@php
$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"><defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#FF7E47"/><stop offset="1" stop-color="#DE4A18"/></linearGradient></defs><rect width="16" height="16" rx="3.5" fill="url(#g)"/><path fill="#fff" transform="translate(2.96 2.96) scale(0.63)" d="M8 0.5 Q8.9 5.2 14.5 8 Q8.9 10.8 8 15.5 Q7.1 10.8 1.5 8 Q7.1 5.2 8 0.5Z"/></svg>';
$logoSrc = 'data:image/svg+xml;base64,'.base64_encode($svg);
@endphp
    <table width="100%" cellpadding="0" cellspacing="0" style="padding:48px 16px;">
        <tr>
            <td align="center">
                <table width="100%" cellpadding="0" cellspacing="0" style="max-width:480px;">

                    {{-- Logo --}}
                    <tr>
                        <td style="padding-bottom:32px">
                            <img src="{{ $logoSrc }}" width="32" height="32" alt="Glint" style="display:block;border-radius:8px">
                        </td>
                    </tr>

                    {{-- Card --}}
                    <tr>
                        <td style="background:#ffffff;border-radius:12px;border:1px solid #e4e4e7;padding:36px;">

                            <p style="margin:0 0 6px;font-size:12px;font-weight:600;color:#f97316;text-transform:uppercase;letter-spacing:0.06em">{{ $typeLabel }}</p>

                            <h1 style="margin:0 0 12px;font-size:20px;font-weight:700;color:#09090b;letter-spacing:-0.02em;line-height:1.3">
                                {{ $ruleName }}
                            </h1>

                            <p style="margin:0 0 28px;font-size:14px;color:#71717a;line-height:1.6">
                                This alert was triggered because the current value exceeded your configured threshold over the last <strong style="color:#52525b">{{ $period }}</strong>.
                            </p>

                            {{-- Divider --}}
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:28px;border-top:1px solid #f4f4f5;">
                                <tr><td style="padding-top:24px;">
                                    <table width="100%" cellpadding="0" cellspacing="0">
                                        <tr>
                                            <td>
                                                <div style="font-size:11px;color:#a1a1aa;font-weight:500;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:4px">Current</div>
                                                <div style="font-size:26px;font-weight:700;color:#c2410c;letter-spacing:-0.02em;font-family:'Courier New',monospace">{{ $currentValue }}</div>
                                            </td>
                                            <td align="right">
                                                <div style="font-size:11px;color:#a1a1aa;font-weight:500;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:4px">Threshold</div>
                                                <div style="font-size:26px;font-weight:700;color:#a1a1aa;letter-spacing:-0.02em;font-family:'Courier New',monospace">{{ $threshold }}</div>
                                            </td>
                                        </tr>
                                    </table>
                                </td></tr>
                            </table>

                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="padding-top:24px;text-align:center">
                            <p style="margin:0;font-size:12px;color:#a1a1aa">Sent by Glint &middot; LLM Observability</p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>

</body>
</html>
