<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
    <div style="max-width:520px;margin:0 auto;padding:24px;">
        <div style="background:#1a1a24;border-radius:16px 16px 0 0;padding:20px 24px;">
            <span style="color:#fcd82f;font-size:18px;font-weight:800;">Trickle Hub</span>
            <span style="color:#94a3b8;font-size:13px;"> · Login code</span>
        </div>
        <div style="background:#ffffff;border-radius:0 0 16px 16px;padding:28px 24px;text-align:center;">
            <p style="margin:0 0 4px;font-size:15px;color:#334155;">Your verification code for</p>
            <p style="margin:0 0 20px;font-size:20px;font-weight:800;">{{ $codeRequest->tool_name }}</p>

            <div style="border:2px dashed #cbd5e1;border-radius:14px;padding:22px;background:#f8fafc;">
                <div style="font-size:12px;font-weight:700;letter-spacing:2px;color:#94a3b8;text-transform:uppercase;">Your code</div>
                <div style="font-family:'Courier New',monospace;font-size:40px;font-weight:800;letter-spacing:4px;color:#0f172a;margin-top:8px;word-break:break-all;">{{ $codeRequest->code_provided }}</div>
            </div>

            @if($codeRequest->code_expires_note)
                <p style="margin:16px 0 0;font-size:13px;font-weight:700;color:#dc2626;">⏱ {{ $codeRequest->code_expires_note }}</p>
            @endif

            <table width="100%" cellpadding="0" cellspacing="0" style="margin-top:22px;font-size:12px;color:#64748b;text-align:left;">
                <tr>
                    <td style="padding:4px 0;">Requested by</td>
                    <td style="padding:4px 0;text-align:right;font-weight:600;color:#0f172a;">{{ optional($codeRequest->employee)->full_name ?? '—' }}</td>
                </tr>
                <tr>
                    <td style="padding:4px 0;">Provided by</td>
                    <td style="padding:4px 0;text-align:right;font-weight:600;color:#0f172a;">{{ optional($codeRequest->responder)->full_name ?? 'HR' }}</td>
                </tr>
            </table>

            <p style="margin:22px 0 0;color:#94a3b8;font-size:11px;">Use this code quickly — it may expire soon. Automated message from Trickle Hub.</p>
        </div>
    </div>
</body>
</html>
