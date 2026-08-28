<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { margin: 0; color: #1e293b; font-size: 11px; }
        .head { border-bottom: 2px solid #1B2D5E; padding-bottom: 8px; margin-bottom: 12px; }
        .head h1 { margin: 0; font-size: 17px; color: #1B2D5E; }
        .head .sub { margin-top: 3px; font-size: 9px; color: #64748b; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #1B2D5E; color: #fff; text-align: left; padding: 6px 8px; font-size: 9px; text-transform: uppercase; letter-spacing: .3px; }
        td { padding: 6px 8px; border-bottom: 1px solid #eef2f7; font-size: 10px; }
        tr:nth-child(even) td { background: #f8fafc; }
        .muted { color: #94a3b8; }
        .pill { font-size: 8px; font-weight: bold; padding: 1px 6px; border-radius: 8px; }
        .up { background: #ecfdf5; color: #047857; }
        .past { background: #f1f5f9; color: #64748b; }
        .foot { margin-top: 12px; font-size: 8px; color: #94a3b8; text-align: right; }
    </style>
</head>
<body>
    <div class="head">
        <h1>Company Work-From-Home Days</h1>
        <div class="sub">All employees clock in via the dashboard on these dates · {{ $days->count() }} day(s) on record</div>
    </div>

    <table>
        <thead>
            <tr><th>Date</th><th>Day</th><th>When</th><th>Note</th><th>Added by</th></tr>
        </thead>
        <tbody>
            @forelse($days as $d)
                @php $date = \Illuminate\Support\Carbon::parse($d->date); @endphp
                <tr>
                    <td>{{ $date->format('d M Y') }}</td>
                    <td>{{ $date->format('l') }}</td>
                    <td><span class="pill {{ $date->isFuture() || $date->isToday() ? 'up' : 'past' }}">{{ $date->isToday() ? 'Today' : ($date->isFuture() ? 'Upcoming' : 'Past') }}</span></td>
                    <td>{{ $d->note ?: '—' }}</td>
                    <td class="muted">{{ optional($d->creator)->full_name ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" style="text-align:center;padding:20px" class="muted">No company WFH days on record.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="foot">Generated {{ $generatedAt->format('d M Y H:i') }}</div>
</body>
</html>
