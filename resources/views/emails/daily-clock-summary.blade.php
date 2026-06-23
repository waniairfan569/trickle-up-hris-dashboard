<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
    <div style="max-width:640px;margin:0 auto;padding:24px;">
        <div style="background:#1a1a24;border-radius:16px 16px 0 0;padding:20px 24px;">
            <span style="color:#fcd82f;font-size:18px;font-weight:800;">Trickle Up HRIS</span>
            <span style="color:#94a3b8;font-size:13px;"> · Daily clock summary</span>
        </div>
        <div style="background:#ffffff;border-radius:0 0 16px 16px;padding:24px;">
            <h2 style="margin:0 0 4px;font-size:18px;">Clock-in / clock-out summary</h2>
            <p style="margin:0 0 16px;color:#64748b;font-size:13px;">{{ $date->format('l, d M Y') }}</p>

            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:18px;">
                <tr>
                    <td style="padding:10px;background:#f8fafc;border-radius:10px;text-align:center;">
                        <div style="font-size:22px;font-weight:800;">{{ $counts['scheduled'] }}</div>
                        <div style="font-size:11px;color:#64748b;text-transform:uppercase;">Scheduled</div>
                    </td>
                    <td style="width:8px;"></td>
                    <td style="padding:10px;background:#ecfdf5;border-radius:10px;text-align:center;">
                        <div style="font-size:22px;font-weight:800;color:#059669;">{{ $counts['clocked_in'] }}</div>
                        <div style="font-size:11px;color:#64748b;text-transform:uppercase;">Clocked in</div>
                    </td>
                    <td style="width:8px;"></td>
                    <td style="padding:10px;background:#fff7ed;border-radius:10px;text-align:center;">
                        <div style="font-size:22px;font-weight:800;color:#d97706;">{{ $counts['still_in'] }}</div>
                        <div style="font-size:11px;color:#64748b;text-transform:uppercase;">Not clocked out</div>
                    </td>
                    <td style="width:8px;"></td>
                    <td style="padding:10px;background:#fef2f2;border-radius:10px;text-align:center;">
                        <div style="font-size:22px;font-weight:800;color:#dc2626;">{{ $counts['not_in'] }}</div>
                        <div style="font-size:11px;color:#64748b;text-transform:uppercase;">No clock-in</div>
                    </td>
                </tr>
            </table>

            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:13px;">
                <thead>
                    <tr style="background:#f8fafc;color:#64748b;text-align:left;">
                        <th style="padding:8px 10px;border-bottom:1px solid #e2e8f0;">Employee</th>
                        <th style="padding:8px 10px;border-bottom:1px solid #e2e8f0;">Clock in</th>
                        <th style="padding:8px 10px;border-bottom:1px solid #e2e8f0;">Clock out</th>
                        <th style="padding:8px 10px;border-bottom:1px solid #e2e8f0;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $r)
                        <tr>
                            <td style="padding:8px 10px;border-bottom:1px solid #f1f5f9;font-weight:600;">{{ $r['name'] }}</td>
                            <td style="padding:8px 10px;border-bottom:1px solid #f1f5f9;">{{ $r['clock_in'] ?? '—' }}</td>
                            <td style="padding:8px 10px;border-bottom:1px solid #f1f5f9;">{{ $r['clock_out'] ?? '—' }}</td>
                            <td style="padding:8px 10px;border-bottom:1px solid #f1f5f9;color:#64748b;">{{ ucfirst(str_replace('_',' ', $r['status'])) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" style="padding:16px;text-align:center;color:#94a3b8;">No scheduled employees today.</td></tr>
                    @endforelse
                </tbody>
            </table>

            <p style="margin:18px 0 0;color:#94a3b8;font-size:11px;">Automated summary from Trickle Up HRIS. You receive this as a super admin.</p>
        </div>
    </div>
</body>
</html>
