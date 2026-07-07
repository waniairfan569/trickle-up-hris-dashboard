@php
    $company = config('app.name', 'TrickleUp Hub');
    $s = $data['summary'];
    $isTeam = $recipient && method_exists($recipient, 'isManager') && $recipient->isManager() && !$recipient->isAdmin();
    // Brand palette
    $brand = '#fcd82f';        // TrickleUp yellow
    $brandDark = '#1a1a24';    // header / table head
    $brandInk = '#a16207';     // readable brand text on light
    $rowColor = [
        'present' => '#ffffff', 'late' => '#FFF8E1', 'absent' => '#FDECEC',
        'on_leave' => '#EEF4FB', 'overtime' => '#EAF5EC', 'early_departure' => '#FFF4E6',
        'missing_clock_out' => '#F4ECF9',
    ];
@endphp
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:24px 0;">
<tr><td align="center">
<table width="640" cellpadding="0" cellspacing="0" style="max-width:640px;width:100%;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e2e8f0;">

    <!-- Header (brand) -->
    <tr><td style="background:{{ $brandDark }};padding:26px 32px;border-bottom:3px solid {{ $brand }};">
        <div style="font-size:13px;letter-spacing:1px;text-transform:uppercase;color:{{ $brand }};font-weight:bold;">{{ $company }}</div>
        <div style="font-size:24px;font-weight:bold;margin-top:6px;color:#ffffff;">Daily Attendance Report</div>
        <div style="font-size:14px;color:#cbd5e1;margin-top:4px;">{{ $data['date']->format('l, d M Y') }}</div>
        @if($isTeam)
            <div style="margin-top:10px;display:inline-block;background:rgba(252,216,47,.15);color:{{ $brand }};padding:5px 12px;border-radius:999px;font-size:12px;font-weight:bold;">Your Team Report — {{ $recipient->full_name }}</div>
        @endif
    </td></tr>

    <!-- Summary cards -->
    <tr><td style="padding:24px 32px 8px;">
        <table width="100%" cellpadding="0" cellspacing="0"><tr>
            <td width="25%" style="padding:4px;">
                <div style="background:#EAF5EC;border-radius:10px;padding:14px;text-align:center;">
                    <div style="font-size:11px;color:#2e7d32;font-weight:bold;text-transform:uppercase;">Present</div>
                    <div style="font-size:26px;font-weight:bold;color:#1b5e20;">{{ $s['present'] }}</div>
                </div>
            </td>
            <td width="25%" style="padding:4px;">
                <div style="background:#FFF8E1;border-radius:10px;padding:{{ $s['late'] > 0 ? '18px 14px' : '14px' }};text-align:center;{{ $s['late'] > 0 ? 'border:2px solid ' . $brand . ';' : '' }}">
                    <div style="font-size:11px;color:{{ $brandInk }};font-weight:bold;text-transform:uppercase;">Late</div>
                    <div style="font-size:{{ $s['late'] > 0 ? '30px' : '26px' }};font-weight:bold;color:{{ $brandInk }};">{{ $s['late'] }}</div>
                </div>
            </td>
            <td width="25%" style="padding:4px;">
                <div style="background:#FDECEC;border-radius:10px;padding:14px;text-align:center;">
                    <div style="font-size:11px;color:#b71c1c;font-weight:bold;text-transform:uppercase;">Absent</div>
                    <div style="font-size:26px;font-weight:bold;color:#b71c1c;">{{ $s['absent'] }}</div>
                </div>
            </td>
            <td width="25%" style="padding:4px;">
                <div style="background:#EEF4FB;border-radius:10px;padding:14px;text-align:center;">
                    <div style="font-size:11px;color:#1565c0;font-weight:bold;text-transform:uppercase;">On Leave</div>
                    <div style="font-size:26px;font-weight:bold;color:#1565c0;">{{ $s['on_leave'] }}</div>
                </div>
            </td>
        </tr></table>
        <div style="text-align:center;font-size:12px;color:#64748b;margin-top:6px;">{{ $s['total_expected'] }} employees expected today</div>
    </td></tr>

    <!-- Late employees -->
    @if($settings->include_late)
    <tr><td style="padding:16px 32px;">
        <div style="font-size:16px;font-weight:bold;border-left:4px solid {{ $brand }};padding-left:10px;margin-bottom:10px;">Late Arrivals Today</div>
        @if(count($data['late_employees']) > 0)
            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:13px;">
                <tr style="background:#FBEFC2;text-align:left;">
                    <th style="padding:8px 10px;">Employee</th><th style="padding:8px 10px;">Department</th>
                    <th style="padding:8px 10px;">Arrival</th><th style="padding:8px 10px;">Late</th>
                </tr>
                @foreach($data['late_employees'] as $e)
                <tr style="background:#FFF8E1;border-top:1px solid #f1e2ad;">
                    <td style="padding:8px 10px;font-weight:bold;">{{ $e['name'] }}</td>
                    <td style="padding:8px 10px;color:#64748b;">{{ $e['department'] ?? '—' }}</td>
                    <td style="padding:8px 10px;">{{ $e['clock_in'] ?? '—' }}</td>
                    <td style="padding:8px 10px;font-weight:bold;color:#c0392b;">{{ $e['late_minutes'] }} min</td>
                </tr>
                @endforeach
            </table>
        @else
            <div style="background:#EAF5EC;color:#1b5e20;border-radius:8px;padding:12px;text-align:center;font-weight:bold;">No late arrivals today.</div>
        @endif
    </td></tr>
    @endif

    <!-- Absent employees -->
    @if($settings->include_absent && count($data['absent_employees']) > 0)
    <tr><td style="padding:8px 32px 16px;">
        <div style="font-size:16px;font-weight:bold;border-left:4px solid #e53935;padding-left:10px;margin-bottom:10px;">Absent Today</div>
        <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:13px;">
            <tr style="background:#f8caca;text-align:left;"><th style="padding:8px 10px;">Employee</th><th style="padding:8px 10px;">Department</th></tr>
            @foreach($data['absent_employees'] as $e)
            <tr style="background:#FDECEC;border-top:1px solid #f3c0c0;">
                <td style="padding:8px 10px;font-weight:bold;">{{ $e['name'] }}</td>
                <td style="padding:8px 10px;color:#64748b;">{{ $e['department'] ?? '—' }}</td>
            </tr>
            @endforeach
        </table>
    </td></tr>
    @endif

    <!-- On leave today -->
    @if(count($data['on_leave_employees'] ?? []) > 0)
    <tr><td style="padding:8px 32px 16px;">
        <div style="font-size:16px;font-weight:bold;border-left:4px solid #1565c0;padding-left:10px;margin-bottom:10px;">On Leave Today</div>
        <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:13px;">
            <tr style="background:#d6e6f7;text-align:left;">
                <th style="padding:8px 10px;">Employee</th><th style="padding:8px 10px;">Department</th>
                <th style="padding:8px 10px;">Type</th><th style="padding:8px 10px;">Leave</th>
            </tr>
            @foreach($data['on_leave_employees'] as $e)
            <tr style="background:#EEF4FB;border-top:1px solid #cfe0f2;">
                <td style="padding:8px 10px;font-weight:bold;">{{ $e['name'] }}</td>
                <td style="padding:8px 10px;color:#64748b;">{{ $e['department'] ?? '—' }}</td>
                <td style="padding:8px 10px;color:#1565c0;">{{ $e['note'] }}</td>
                <td style="padding:8px 10px;color:#64748b;">{{ $e['policy'] ?? '—' }}</td>
            </tr>
            @endforeach
        </table>
    </td></tr>
    @endif

    <!-- Full table -->
    @if($settings->include_full_table)
    <tr><td style="padding:8px 32px 24px;">
        <div style="font-size:16px;font-weight:bold;margin-bottom:10px;">Complete Attendance — {{ $data['date']->format('d M Y') }}</div>
        <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:12px;">
            <tr style="background:{{ $brandDark }};color:{{ $brand }};text-align:left;">
                <th style="padding:8px;">Name</th><th style="padding:8px;">Dept</th>
                <th style="padding:8px;">Clock In</th><th style="padding:8px;">Clock Out</th>
                <th style="padding:8px;">Hours</th><th style="padding:8px;">Status</th>
            </tr>
            @forelse($data['full_table'] as $r)
            <tr style="background:{{ $rowColor[$r['status']] ?? '#ffffff' }};border-top:1px solid #e2e8f0;">
                <td style="padding:8px;font-weight:bold;">{{ $r['name'] }}</td>
                <td style="padding:8px;color:#64748b;">{{ $r['department'] ?? '—' }}</td>
                <td style="padding:8px;">{{ $r['clock_in'] }}</td>
                <td style="padding:8px;">{{ $r['clock_out'] }}</td>
                <td style="padding:8px;">{{ $r['hours_worked'] }}</td>
                <td style="padding:8px;">{{ $r['status_label'] }}</td>
            </tr>
            @empty
            <tr><td colspan="6" style="padding:14px;text-align:center;color:#94a3b8;">No attendance records for this day.</td></tr>
            @endforelse
        </table>
    </td></tr>
    @endif

    <!-- Footer -->
    <tr><td style="background:#f8fafc;padding:20px 32px;border-top:1px solid #e2e8f0;font-size:11px;color:#94a3b8;text-align:center;">
        Automated report from {{ $company }}.<br>
        Generated {{ now()->format('d M Y, h:i A') }}.<br>
        <a href="{{ route('attendance-reports.settings') }}" style="color:{{ $brandInk }};font-weight:bold;">Change report settings</a><br>
        &copy; {{ now()->year }} {{ $company }}
    </td></tr>

</table>
</td></tr>
</table>
</body>
</html>
