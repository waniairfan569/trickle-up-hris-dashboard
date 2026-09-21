<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Time Tracking Report — {{ $employee->full_name }}</title>
<style>
    * { box-sizing: border-box; }
    body { font-family: DejaVu Sans, Arial, sans-serif; color: #1f2937; font-size: 12px; margin: 0; padding: 28px 32px; background: #fff; }
    .muted { color: #6b7280; }
    h1 { font-size: 20px; margin: 0 0 2px; }
    h2 { font-size: 13px; margin: 22px 0 8px; padding-bottom: 5px; border-bottom: 2px solid #111827; text-transform: uppercase; letter-spacing: .04em; }
    .head { border-bottom: 3px solid #facc15; padding-bottom: 12px; margin-bottom: 6px; }
    .brand { font-size: 12px; font-weight: bold; letter-spacing: .12em; text-transform: uppercase; color: #6b7280; }
    .sub { margin-top: 4px; font-size: 12px; }
    .sub strong { color: #111827; }
    .period { margin: 10px 0 4px; font-size: 12px; }
    .period .pill { display: inline-block; background: #fef9c3; border: 1px solid #fde047; border-radius: 999px; padding: 2px 10px; font-weight: bold; }
    table { width: 100%; border-collapse: collapse; margin-top: 4px; }
    th { text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: .04em; color: #6b7280; border-bottom: 1px solid #d1d5db; padding: 5px 8px; }
    td { padding: 6px 8px; border-bottom: 1px solid #eef2f7; vertical-align: top; }
    tr:last-child td { border-bottom: none; }
    .empty { color: #9ca3af; font-style: italic; padding: 8px; }
    .cards { width: 100%; margin: 6px 0 2px; }
    .cards td { border: none; padding: 4px; }
    .card { border: 1px solid #e5e7eb; border-radius: 10px; padding: 10px 12px; text-align: center; }
    .card .n { font-size: 20px; font-weight: bold; color: #111827; }
    .card .l { font-size: 9px; text-transform: uppercase; letter-spacing: .04em; color: #6b7280; margin-top: 2px; }
    .cat { display: inline-block; font-size: 10px; font-weight: bold; background: #f1f5f9; border-radius: 999px; padding: 1px 8px; color: #475569; }
    .foot { margin-top: 26px; padding-top: 10px; border-top: 1px solid #e5e7eb; font-size: 10px; color: #9ca3af; }
    .toolbar { margin-bottom: 16px; }
    .toolbar button { font: inherit; background: #facc15; border: none; border-radius: 8px; padding: 8px 16px; font-weight: bold; cursor: pointer; }
    @media print { .toolbar { display: none; } body { padding: 0; } }
    @page { margin: 18mm 14mm; }
</style>
</head>
<body>
    @php
        $dateRange = function ($r) {
            $s = \Illuminate\Support\Carbon::parse($r->start_date);
            $e = \Illuminate\Support\Carbon::parse($r->end_date);
            return $s->isSameDay($e) ? $s->format('d M Y') : $s->format('d M Y') . ' – ' . $e->format('d M Y');
        };
        $plannedDays   = $planned->sum('days_requested');
        $unplannedDays = $unplanned->sum('days_requested');
        $wfhDays       = $wfh->sum('days_requested');
    @endphp

    <div class="toolbar">
        <button onclick="window.print()">🖨 Print / Save as PDF</button>
    </div>

    <div class="head">
        <div class="brand">{{ $brandName }}</div>
        <h1>Time Tracking Report</h1>
        <div class="sub"><strong>{{ $employee->full_name }}</strong>@if($employee->job_title) · {{ $employee->job_title }}@endif</div>
        <div class="period">Period: <span class="pill">{{ $periodLabel }}</span> <span class="muted">({{ $from->format('d M Y') }} – {{ $to->format('d M Y') }})</span></div>
    </div>

    {{-- Summary --}}
    <table class="cards">
        <tr>
            <td width="20%"><div class="card"><div class="n">{{ rtrim(rtrim(number_format($plannedDays, 1), '0'), '.') }}</div><div class="l">Planned leave days</div></div></td>
            <td width="20%"><div class="card"><div class="n">{{ rtrim(rtrim(number_format($unplannedDays, 1), '0'), '.') }}</div><div class="l">Unplanned leave days</div></div></td>
            <td width="20%"><div class="card"><div class="n">{{ rtrim(rtrim(number_format($wfhDays, 1), '0'), '.') }}</div><div class="l">Work-from-home days</div></div></td>
            <td width="20%"><div class="card"><div class="n">{{ $lates->count() }}</div><div class="l">Lates</div></div></td>
            <td width="20%"><div class="card"><div class="n">{{ $conduct->count() }}</div><div class="l">Conduct notes</div></div></td>
        </tr>
    </table>

    {{-- Planned leaves --}}
    <h2>Planned leaves</h2>
    @if($planned->isEmpty())
        <div class="empty">No planned leaves in this period.</div>
    @else
        <table>
            <thead><tr><th width="30%">Dates</th><th width="12%">Days</th><th width="20%">Type</th><th>Reason</th></tr></thead>
            <tbody>
            @foreach($planned as $r)
                <tr><td>{{ $dateRange($r) }}</td><td>{{ rtrim(rtrim(number_format($r->days_requested, 1), '0'), '.') }}</td><td>{{ optional($r->policy)->name ?? 'Leave' }}</td><td>{{ $r->reason ?: '—' }}</td></tr>
            @endforeach
            </tbody>
        </table>
    @endif

    {{-- Unplanned leaves --}}
    <h2>Unplanned leaves</h2>
    @if($unplanned->isEmpty())
        <div class="empty">No unplanned leaves in this period.</div>
    @else
        <table>
            <thead><tr><th width="30%">Dates</th><th width="12%">Days</th><th width="20%">Type</th><th>Reason</th></tr></thead>
            <tbody>
            @foreach($unplanned as $r)
                <tr><td>{{ $dateRange($r) }}</td><td>{{ rtrim(rtrim(number_format($r->days_requested, 1), '0'), '.') }}</td><td>{{ optional($r->policy)->name ?? 'Leave' }}</td><td>{{ $r->reason ?: '—' }}</td></tr>
            @endforeach
            </tbody>
        </table>
    @endif

    {{-- Work from home --}}
    <h2>Work from home</h2>
    @if($wfh->isEmpty())
        <div class="empty">No work-from-home days in this period.</div>
    @else
        <table>
            <thead><tr><th width="30%">Dates</th><th width="12%">Days</th><th>Reason</th></tr></thead>
            <tbody>
            @foreach($wfh as $r)
                <tr><td>{{ $dateRange($r) }}</td><td>{{ rtrim(rtrim(number_format($r->days_requested, 1), '0'), '.') }}</td><td>{{ $r->reason ?: '—' }}</td></tr>
            @endforeach
            </tbody>
        </table>
    @endif

    {{-- Lates --}}
    <h2>Lates</h2>
    @if($lates->isEmpty())
        <div class="empty">No late arrivals in this period.</div>
    @else
        <table>
            <thead><tr><th width="24%">Date</th><th width="16%">Late by</th><th>Reason</th></tr></thead>
            <tbody>
            @foreach($lates as $rec)
                @php $c = $corrections[\Illuminate\Support\Carbon::parse($rec->date)->toDateString()] ?? null; @endphp
                <tr>
                    <td>{{ \Illuminate\Support\Carbon::parse($rec->date)->format('d M Y (D)') }}</td>
                    <td>{{ $rec->late_minutes }} min</td>
                    <td>{{ $c->reason ?? ($rec->notes ?: '—') }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

    {{-- Conduct / behaviour --}}
    <h2>Conduct &amp; behaviour</h2>
    @if($conduct->isEmpty())
        <div class="empty">No conduct notes logged in this period.</div>
    @else
        <table>
            <thead><tr><th width="18%">Date</th><th width="16%">Category</th><th>Note</th><th width="18%">Logged by</th></tr></thead>
            <tbody>
            @foreach($conduct as $n)
                <tr>
                    <td>{{ $n->occurred_on->format('d M Y') }}</td>
                    <td>@if($n->category)<span class="cat">{{ $n->category }}</span>@else — @endif</td>
                    <td>{{ $n->note }}</td>
                    <td>{{ optional($n->author)->full_name ?? '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

    <div class="foot">
        Generated {{ $generatedAt->format('d M Y, H:i') }} · {{ $brandName }} · Confidential — for internal HR use only.
    </div>
</body>
</html>
