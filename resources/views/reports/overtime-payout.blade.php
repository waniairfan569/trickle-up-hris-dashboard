<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Approved Overtime — {{ $periodLabel }}</title>
<style>
    * { box-sizing: border-box; }
    body { font-family: DejaVu Sans, Arial, sans-serif; color: #1f2937; font-size: 12px; margin: 0; padding: 28px 32px; background: #fff; }
    .muted { color: #6b7280; }
    h1 { font-size: 20px; margin: 0 0 2px; }
    h3 { font-size: 12px; margin: 18px 0 6px; }
    .head { border-bottom: 3px solid #facc15; padding-bottom: 12px; margin-bottom: 6px; }
    .brand { font-size: 12px; font-weight: bold; letter-spacing: .12em; text-transform: uppercase; color: #6b7280; }
    .period { margin: 10px 0 4px; font-size: 12px; }
    .period .pill { display: inline-block; background: #fef9c3; border: 1px solid #fde047; border-radius: 999px; padding: 2px 10px; font-weight: bold; }
    table { width: 100%; border-collapse: collapse; margin-top: 4px; }
    th { text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: .04em; color: #6b7280; border-bottom: 1px solid #d1d5db; padding: 5px 8px; }
    td { padding: 6px 8px; border-bottom: 1px solid #eef2f7; vertical-align: top; }
    .num { text-align: right; }
    .cards { width: 100%; margin: 6px 0 2px; }
    .cards td { border: none; padding: 4px; }
    .card { border: 1px solid #e5e7eb; border-radius: 10px; padding: 10px 12px; text-align: center; }
    .card .n { font-size: 20px; font-weight: bold; color: #111827; }
    .card .l { font-size: 9px; text-transform: uppercase; letter-spacing: .04em; color: #6b7280; margin-top: 2px; }
    .emp { background: #f8fafc; }
    .emp td { font-weight: bold; border-bottom: 1px solid #cbd5e1; }
    .sub td { font-weight: bold; background: #fffbeb; }
    .empty { color: #9ca3af; font-style: italic; padding: 10px; }
    .foot { margin-top: 26px; padding-top: 10px; border-top: 1px solid #e5e7eb; font-size: 10px; color: #9ca3af; }
    @page { margin: 18mm 14mm; }
    @php $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.'); @endphp
</head>
<body>
    <div class="head">
        <div class="brand">{{ $brandName }}</div>
        <h1>Approved Overtime — Payout Report</h1>
        <div class="period">Period: <span class="pill">{{ $periodLabel }}</span> <span class="muted">({{ $from->format('d M Y') }} – {{ $to->format('d M Y') }})</span></div>
    </div>

    <table class="cards">
        <tr>
            <td width="25%"><div class="card"><div class="n">{{ $employeeCount }}</div><div class="l">Employees</div></div></td>
            <td width="25%"><div class="card"><div class="n">{{ $entryCount }}</div><div class="l">Approved entries</div></div></td>
            <td width="25%"><div class="card"><div class="n">{{ $hasHours ? $fmt($totalHours) : '—' }}</div><div class="l">Total hours</div></div></td>
            <td width="25%"><div class="card"><div class="n">{{ $from->format('M Y') }}</div><div class="l">Period</div></div></td>
        </tr>
    </table>

    @if($entryCount === 0)
        <div class="empty">No approved overtime in this period.</div>
    @else
        <table>
            <thead>
                <tr>
                    <th width="16%">Date</th>
                    <th width="12%" class="num">Hours</th>
                    <th>Details</th>
                    <th width="22%">Approved</th>
                </tr>
            </thead>
            <tbody>
            @foreach($byEmployee as $group)
                <tr class="emp"><td colspan="4">{{ $group->employee }}@if($group->email) <span class="muted" style="font-weight:normal">· {{ $group->email }}</span>@endif</td></tr>
                @foreach($group->rows as $r)
                    <tr>
                        <td>{{ optional($r->date)->format('d M Y') }}</td>
                        <td class="num">{{ $r->hours !== null ? $fmt($r->hours) : '—' }}</td>
                        <td>{{ $r->details ?: '—' }}</td>
                        <td class="muted">{{ optional($r->approved_on)->format('d M Y') }}@if($r->approved_by) · {{ $r->approved_by }}@endif</td>
                    </tr>
                @endforeach
                <tr class="sub">
                    <td>{{ $group->entries }} {{ \Illuminate\Support\Str::plural('entry', $group->entries) }}</td>
                    <td class="num">{{ $hasHours ? $fmt($group->hours) : '—' }}</td>
                    <td colspan="2"></td>
                </tr>
            @endforeach
            </tbody>
            <tfoot>
                <tr class="emp">
                    <td>GRAND TOTAL — {{ $employeeCount }} {{ \Illuminate\Support\Str::plural('employee', $employeeCount) }}</td>
                    <td class="num">{{ $hasHours ? $fmt($totalHours) : '—' }}</td>
                    <td colspan="2">{{ $entryCount }} approved {{ \Illuminate\Support\Str::plural('entry', $entryCount) }}</td>
                </tr>
            </tfoot>
        </table>
    @endif

    <div class="foot">
        Generated {{ $generatedAt->format('d M Y, H:i') }} · {{ $brandName }} · Approved overtime for payroll — confidential.
    </div>
</body>
</html>
