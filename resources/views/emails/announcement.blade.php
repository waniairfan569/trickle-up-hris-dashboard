<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
    <div style="max-width:600px;margin:0 auto;padding:24px;">
        <div style="background:#1a1a24;border-radius:14px 14px 0 0;padding:20px 24px;border-bottom:3px solid #fcd82f;">
            <span style="color:#fcd82f;font-size:18px;font-weight:800;">{{ config('app.name', 'Trickle Hub') }}</span>
            <span style="color:#94a3b8;font-size:13px;"> · Announcement</span>
        </div>
        <div style="background:#ffffff;border-radius:0 0 14px 14px;padding:28px 24px;">
            <h1 style="margin:0 0 12px;font-size:20px;color:#0f172a;">{{ $announcement->title }}</h1>
            <div style="font-size:15px;line-height:1.7;color:#334155;">{!! $announcement->bodyHtml() !!}</div>
            <p style="margin:22px 0 0;font-size:12px;color:#94a3b8;">
                Posted by {{ optional($announcement->creator)->full_name ?? 'Admin' }} · {{ $announcement->created_at->format('d M Y, h:i A') }}
            </p>
        </div>
        <p style="text-align:center;margin:16px 0 0;font-size:11px;color:#94a3b8;">
            Automated announcement from {{ config('app.name', 'Trickle Hub') }}. &copy; {{ now()->year }}
        </p>
    </div>
</body>
</html>
