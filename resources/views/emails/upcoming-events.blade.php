<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
    <div style="max-width:600px;margin:0 auto;padding:24px;">
        <div style="background:#1a1a24;border-radius:16px 16px 0 0;padding:20px 24px;">
            <span style="color:#fcd82f;font-size:18px;font-weight:800;">Trickle Up HRIS</span>
            <span style="color:#94a3b8;font-size:13px;"> · Upcoming events</span>
        </div>
        <div style="background:#ffffff;border-radius:0 0 16px 16px;padding:24px;">
            <h2 style="margin:0 0 4px;font-size:18px;">Happening tomorrow</h2>
            <p style="margin:0 0 18px;color:#64748b;font-size:13px;">
                Hi {{ $recipient->first_name ?? 'there' }}, here's what's on for {{ $date->format('l, d M Y') }}.
            </p>

            @foreach($events as $event)
                @php
                    $bg = ['brand'=>'#eab308','indigo'=>'#6366f1','emerald'=>'#10b981','rose'=>'#f43f5e','sky'=>'#0ea5e9'][$event->color] ?? '#eab308';
                @endphp
                <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:10px;border:1px solid #e2e8f0;border-radius:12px;">
                    <tr>
                        <td style="width:56px;padding:12px;vertical-align:top;">
                            <div style="background:{{ $bg }};border-radius:10px;text-align:center;padding:8px 0;color:#ffffff;">
                                <div style="font-size:10px;font-weight:700;text-transform:uppercase;">{{ \Carbon\Carbon::parse($event->date)->format('M') }}</div>
                                <div style="font-size:18px;font-weight:800;line-height:1;">{{ \Carbon\Carbon::parse($event->date)->format('d') }}</div>
                            </div>
                        </td>
                        <td style="padding:12px 12px 12px 4px;vertical-align:top;">
                            <div style="font-size:15px;font-weight:700;">{{ $event->title }}</div>
                            <div style="font-size:12px;color:#64748b;margin-top:2px;">
                                {{ \Carbon\Carbon::parse($event->date)->format('D, d M') }}@if($event->location) · {{ $event->location }}@endif
                            </div>
                            @if($event->description)<div style="font-size:12px;color:#475569;margin-top:6px;">{{ \Illuminate\Support\Str::limit($event->description, 160) }}</div>@endif
                        </td>
                    </tr>
                </table>
            @endforeach

            <p style="margin:18px 0 0;color:#94a3b8;font-size:11px;">Automated reminder from Trickle Up HRIS.</p>
        </div>
    </div>
</body>
</html>
