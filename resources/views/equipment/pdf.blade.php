<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { margin: 0; color: #1e293b; font-size: 10px; }
        .head { border-bottom: 2px solid #1B2D5E; padding-bottom: 8px; margin-bottom: 10px; }
        .head h1 { margin: 0; font-size: 16px; color: #1B2D5E; }
        .meta { margin-top: 3px; font-size: 9px; color: #64748b; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #1B2D5E; color: #fff; text-align: left; padding: 5px 6px; font-size: 8px; text-transform: uppercase; letter-spacing: .3px; }
        td { padding: 4px 6px; border-bottom: 1px solid #eef2f7; font-size: 9px; vertical-align: top; }
        tr:nth-child(even) td { background: #f8fafc; }
        .muted { color: #94a3b8; }
        .pill { font-size: 8px; font-weight: bold; padding: 1px 6px; border-radius: 8px; text-transform: capitalize; }
        .approved { background: #ecfdf5; color: #047857; }
        .rejected { background: #fef2f2; color: #b91c1c; }
        .pending { background: #fffbeb; color: #b45309; }
        .foot { margin-top: 10px; font-size: 8px; color: #94a3b8; text-align: right; }
    </style>
</head>
<body>
    <div class="head">
        <h1>Equipment Requests</h1>
        <div class="meta">
            @if($dateFrom || $dateTo){{ $dateFrom ?: 'Start' }} → {{ $dateTo ?: 'Now' }}@else All dates @endif
            · Status: {{ ucfirst($status) }} · {{ $requests->count() }} request(s)
        </div>
    </div>

    <table>
        <thead>
            <tr><th>Req #</th><th>Employee</th><th>Department</th><th>Equipment</th><th>Reason</th><th>Requested</th><th>Return by</th><th>Status</th><th>Reviewed by</th></tr>
        </thead>
        <tbody>
            @forelse($requests as $r)
                <tr>
                    <td>{{ $r->request_number }}</td>
                    <td>{{ optional($r->employee)->full_name ?? 'Unknown' }}</td>
                    <td class="muted">{{ optional(optional($r->employee)->department)->name ?? '—' }}</td>
                    <td>{{ $r->equipment_name }}</td>
                    <td>{{ \Illuminate\Support\Str::limit($r->reason, 60) }}</td>
                    <td class="muted">{{ optional($r->created_at)->format('d M Y') }}</td>
                    <td class="muted">{{ optional($r->expected_return_date)->format('d M Y') ?? '—' }}</td>
                    <td><span class="pill {{ $r->status }}">{{ ucfirst($r->status) }}</span>@if($r->status==='rejected' && $r->review_note)<br><span class="muted" style="font-size:7px">{{ \Illuminate\Support\Str::limit($r->review_note,40) }}</span>@endif</td>
                    <td class="muted">{{ optional($r->reviewer)->full_name ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="9" style="text-align:center;padding:20px" class="muted">No equipment requests for this selection.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="foot">Generated {{ $generatedAt->format('d M Y H:i') }}</div>
</body>
</html>
