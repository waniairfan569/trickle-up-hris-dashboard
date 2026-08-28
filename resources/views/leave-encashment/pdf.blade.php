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
        .chips span { display: inline-block; background: #eef2ff; color: #3730a3; border-radius: 8px; padding: 1px 6px; margin-right: 4px; font-size: 8px; }
        .cards { width: 100%; margin: 8px 0 12px; }
        .cards td { border: 1px solid #e2e8f0; border-radius: 4px; padding: 6px 8px; width: 25%; }
        .cards .lbl { font-size: 8px; color: #64748b; text-transform: uppercase; letter-spacing: .4px; }
        .cards .val { font-size: 13px; font-weight: bold; color: #0f172a; }
        table.grid { width: 100%; border-collapse: collapse; }
        table.grid th { background: #1B2D5E; color: #fff; text-align: left; padding: 5px 6px; font-size: 8px; text-transform: uppercase; letter-spacing: .3px; }
        table.grid td { padding: 4px 6px; border-bottom: 1px solid #eef2f7; font-size: 9px; }
        table.grid tr:nth-child(even) td { background: #f8fafc; }
        .r { text-align: right; }
        .muted { color: #94a3b8; }
        .badge { font-size: 8px; font-weight: bold; text-transform: capitalize; }
        .foot { margin-top: 10px; font-size: 8px; color: #94a3b8; text-align: right; }
    </style>
</head>
<body>
    @php $c = $totals['currency']; $money = fn ($v) => $c . ' ' . number_format((float) $v, 2); @endphp
    <div class="head">
        <h1>Leave Encashment Report</h1>
        <div class="meta">
            Leave year {{ $year }}–{{ substr($year + 1, 2) }} · {{ $periodLabel }}
            <span class="chips" style="float:right">
                <span>Status: {{ ucfirst($status) }}</span>
                @if($policyName)<span>Policy: {{ $policyName }}</span>@endif
            </span>
        </div>
    </div>

    <table class="cards">
        <tr>
            <td><div class="lbl">Records</div><div class="val">{{ $totals['count'] }}</div></td>
            <td><div class="lbl">Total amount</div><div class="val">{{ $money($totals['amount']) }}</div></td>
            <td><div class="lbl">Days encashed</div><div class="val">{{ rtrim(rtrim(number_format((float) $totals['days_encashed'], 1), '0'), '.') }}</div></td>
            <td><div class="lbl">Days lapsed</div><div class="val">{{ rtrim(rtrim(number_format((float) $totals['days_lapsed'], 1), '0'), '.') }}</div></td>
        </tr>
    </table>

    <table class="grid">
        <thead>
            <tr>
                <th>Employee</th><th>Department</th><th>Leave type</th>
                <th class="r">Remaining</th><th class="r">Cap</th><th class="r">Encashed</th><th class="r">Lapsed</th>
                <th class="r">Daily rate</th><th class="r">Amount</th><th>Status</th><th>Processed</th><th>Payment</th>
            </tr>
        </thead>
        <tbody>
            @php $d = fn ($v) => rtrim(rtrim(number_format((float) $v, 1), '0'), '.'); @endphp
            @forelse($records as $r)
                <tr>
                    <td>{{ optional($r->employee)->full_name ?? 'Unknown' }}</td>
                    <td class="muted">{{ optional(optional($r->employee)->department)->name ?? '—' }}</td>
                    <td>{{ optional($r->policy)->name ?? '—' }}@if($r->is_pro_rata)<br><span class="muted">pro-rata {{ $r->pro_rata_months }}mo</span>@endif</td>
                    <td class="r">{{ $d($r->days_remaining_before_renewal) }}</td>
                    <td class="r">{{ $d($r->encashment_cap_days) }}</td>
                    <td class="r">{{ $d($r->days_to_encash) }}</td>
                    <td class="r">{{ $d($r->days_lapsed) }}</td>
                    <td class="r">{{ $money($r->daily_rate) }}</td>
                    <td class="r"><strong>{{ $money($r->encashment_amount) }}</strong></td>
                    <td class="badge">{{ ucfirst($r->status) }}</td>
                    <td class="muted">{{ optional($r->processedBy)->full_name ?? '—' }}<br>{{ optional($r->processed_at)->format('d M Y') ?? '' }}</td>
                    <td class="muted">{{ optional($r->payment_date)->format('d M Y') ?? '—' }}{{ $r->payment_reference ? ' · ' . $r->payment_reference : '' }}</td>
                </tr>
            @empty
                <tr><td colspan="12" style="text-align:center;padding:20px" class="muted">No encashment records for this selection.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="foot">Generated {{ $generatedAt->format('d M Y H:i') }}</div>
</body>
</html>
