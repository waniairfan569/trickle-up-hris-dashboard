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
        .code_sent { background: #ecfdf5; color: #047857; }
        .rejected { background: #fef2f2; color: #b91c1c; }
        .pending { background: #fffbeb; color: #b45309; }
        .cancelled { background: #f1f5f9; color: #64748b; }
        .foot { margin-top: 10px; font-size: 8px; color: #94a3b8; text-align: right; }
        .note { margin-top: 6px; font-size: 8px; color: #94a3b8; }
    </style>
</head>
<body>
    <div class="head">
        <h1>Code Requests</h1>
        <div class="meta">
            @if($dateFrom || $dateTo){{ $dateFrom ?: 'Start' }} → {{ $dateTo ?: 'Now' }}@else All dates @endif
            · Status: {{ ucfirst($status) }} · {{ $requests->count() }} request(s)
        </div>
    </div>

    <table>
        <thead>
            <tr><th>Req #</th><th>Employee</th><th>Department</th><th>Tool</th><th>Message</th><th>Requested</th><th>Status</th><th>Sent</th><th>Handled by</th></tr>
        </thead>
        <tbody>
            @forelse($requests as $r)
                <tr>
                    <td>{{ $r->request_number }}</td>
                    <td>{{ optional($r->employee)->full_name ?? 'Unknown' }}</td>
                    <td class="muted">{{ optional(optional($r->employee)->department)->name ?? '—' }}</td>
                    <td>{{ $r->tool_name }}</td>
                    <td>{{ \Illuminate\Support\Str::limit($r->message, 50) }}</td>
                    <td class="muted">{{ optional($r->created_at)->format('d M Y') }}</td>
                    <td><span class="pill {{ $r->status }}">{{ ucfirst(str_replace('_',' ',$r->status)) }}</span></td>
                    <td class="muted">{{ optional($r->code_sent_at)->format('d M Y') ?? '—' }}</td>
                    <td class="muted">{{ optional($r->responder)->full_name ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="9" style="text-align:center;padding:20px" class="muted">No code requests for this selection.</td></tr>
            @endforelse
        </tbody>
    </table>

    <p class="note">Code values are never included in exports — they are redacted for security 7 days after being sent.</p>
    <div class="foot">Generated {{ $generatedAt->format('d M Y H:i') }}</div>
</body>
</html>
