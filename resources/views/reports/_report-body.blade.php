@php
    $emp = $data['employee'];
    $att = $data['attendance'];
    $lv  = $data['leaves'];
    $sc  = $data['score'];
    $meta = $data['meta'];
    $period = $data['period'];

    $daily = $att['daily_breakdown'];
    $showDaily = count($daily) > 0 && count($daily) <= 45;   // whole-year daily tables would be huge

    $statusLabel = [
        'present' => 'Present', 'late' => 'Late ⚠', 'absent' => 'Absent ✗',
        'overtime' => 'Overtime', 'early_departure' => 'Early out', 'on_leave' => 'On leave',
        'missing_clock_out' => 'No clock-out', 'half_day' => 'Half day', 'weekend' => 'Weekend',
    ];
    $balTotals = ['allocated' => 0, 'used' => 0, 'remaining' => 0];
    foreach ($lv['balances'] as $b) { $balTotals['allocated'] += $b['allocated']; $balTotals['used'] += $b['used']; $balTotals['remaining'] += $b['remaining']; }
@endphp

<!-- Header -->
<table class="doc-header">
    <tr>
        <td>
            <div class="brand">Trickle Hub</div>
            <div class="brand-sub">Employee Report</div>
            <div class="brand-period">{{ $meta['period_label'] }}</div>
        </td>
        <td class="conf"><span>CONFIDENTIAL</span></td>
    </tr>
</table>

<!-- Employee info bar -->
<table class="info">
    <tr>
        <td><b>Name:</b> {{ $emp['name'] }}</td>
        <td><b>ID:</b> {{ $emp['id'] }}</td>
    </tr>
    <tr>
        <td><b>Role:</b> {{ $emp['job_title'] }}</td>
        <td><b>Dept:</b> {{ $emp['department'] }}</td>
    </tr>
    <tr>
        <td><b>Manager:</b> {{ $emp['manager'] }}</td>
        <td><b>Joined:</b> {{ $emp['join_date'] }}</td>
    </tr>
</table>

<div class="wrap">

    <!-- Summary cards -->
    <table class="cards">
        <tr>
            <td class="c-green"><div class="card-num">{{ $att['present_days'] }}</div><div class="card-lbl">Present days</div></td>
            <td class="c-red"><div class="card-num">{{ $att['absent_days'] }}</div><div class="card-lbl">Absent days</div></td>
            <td class="c-amber"><div class="card-num">{{ $att['late_days'] }}</div><div class="card-lbl">Late arrivals</div></td>
            <td class="c-blue"><div class="card-num">{{ $att['total_hours'] }}</div><div class="card-lbl">Hours worked</div></td>
        </tr>
    </table>

    <!-- Attendance rate -->
    <div class="rate">
        <div class="rate-top"><b>Attendance rate:</b> {{ $att['attendance_rate'] }}% &nbsp;·&nbsp; {{ $period['working_days'] }} scheduled working days</div>
        <div class="rate-track"><div class="rate-fill" style="width: {{ min(100, $att['attendance_rate']) }}%; background: {{ $sc['grade_color'] }};"></div></div>
    </div>

    <!-- Daily attendance -->
    @if($showDaily)
        <div class="sec">
            <div class="sec-title">Daily Attendance</div>
            <table class="data">
                <thead><tr><th>Date</th><th>Day</th><th>In</th><th>Out</th><th>Hours</th><th class="num">Late</th><th>Status</th></tr></thead>
                @foreach($daily as $d)
                    <tr>
                        <td>{{ $d['date'] }}</td>
                        <td>{{ $d['day'] }}</td>
                        <td>{{ $d['clock_in'] }}</td>
                        <td>{{ $d['clock_out'] }}</td>
                        <td>{{ $d['hours'] }}</td>
                        <td class="num">{{ $d['late_minutes'] ? $d['late_minutes'].'m' : '—' }}</td>
                        <td class="st st-{{ $d['status'] }}">{{ $statusLabel[$d['status']] ?? ucfirst(str_replace('_',' ',$d['status'])) }}</td>
                    </tr>
                @endforeach
            </table>
        </div>
    @endif

    <!-- Leave balances -->
    <div class="sec">
        <div class="sec-title">Leave Summary &amp; Balances ({{ $period['end'] ? \Illuminate\Support\Str::afterLast($period['end'],' ') : '' }})</div>
        @if(count($lv['balances']))
            <table class="data">
                <tr><th>Leave type</th><th class="num">Allocated</th><th class="num">Used</th><th class="num">Pending</th><th class="num">Remaining</th></tr>
                @foreach($lv['balances'] as $b)
                    <tr>
                        <td>{{ $b['policy'] }}</td>
                        <td class="num">{{ rtrim(rtrim(number_format($b['allocated'],1),'0'),'.') }}</td>
                        <td class="num">{{ rtrim(rtrim(number_format($b['used'],1),'0'),'.') }}</td>
                        <td class="num">{{ rtrim(rtrim(number_format($b['pending'],1),'0'),'.') }}</td>
                        <td class="num">{{ rtrim(rtrim(number_format($b['remaining'],1),'0'),'.') }}</td>
                    </tr>
                @endforeach
                <tr class="tot">
                    <td>Total</td>
                    <td class="num">{{ rtrim(rtrim(number_format($balTotals['allocated'],1),'0'),'.') }}</td>
                    <td class="num">{{ rtrim(rtrim(number_format($balTotals['used'],1),'0'),'.') }}</td>
                    <td class="num">—</td>
                    <td class="num">{{ rtrim(rtrim(number_format($balTotals['remaining'],1),'0'),'.') }}</td>
                </tr>
            </table>
        @else
            <div class="muted" style="font-size:8pt;">No leave balances on record for this year.</div>
        @endif
    </div>

    <!-- Leave requests in this period -->
    @if(count($lv['by_type']))
        <div class="sec">
            <div class="sec-title">Leave Taken In This Period ({{ rtrim(rtrim(number_format($lv['total_days'],1),'0'),'.') }} days)</div>
            @foreach($lv['by_type'] as $typeName => $t)
                <div class="lv-type">{{ $typeName }} — {{ $t['count'] }} request{{ $t['count'] == 1 ? '' : 's' }} ({{ rtrim(rtrim(number_format($t['days'],1),'0'),'.') }} days)</div>
                @foreach($t['requests'] as $rq)
                    <div class="lv-item">• {{ $rq['from'] }}@if($rq['from'] !== $rq['to']) – {{ $rq['to'] }}@endif ({{ rtrim(rtrim(number_format($rq['days'],1),'0'),'.') }} day{{ $rq['days'] == 1 ? '' : 's' }}) <span class="lv-reason">— {{ $rq['reason'] }}</span></div>
                @endforeach
            @endforeach
        </div>
    @endif

    <!-- Monthly breakdown (yearly / mid-year / custom) -->
    @if(count($data['monthly_breakdown']))
        <div class="sec">
            <div class="sec-title">Monthly Breakdown</div>
            <table class="data">
                <thead><tr><th>Month</th><th class="num">Present</th><th class="num">Absent</th><th class="num">Late</th><th class="num">On leave</th><th class="num">Hours</th><th class="num">Late min</th></tr></thead>
                @foreach($data['monthly_breakdown'] as $m)
                    <tr>
                        <td>{{ $m['month'] }}</td>
                        <td class="num">{{ $m['present'] }}</td>
                        <td class="num">{{ $m['absent'] }}</td>
                        <td class="num">{{ $m['late'] }}</td>
                        <td class="num">{{ $m['on_leave'] }}</td>
                        <td class="num">{{ $m['hours'] }}h</td>
                        <td class="num">{{ $m['late_min'] }}</td>
                    </tr>
                @endforeach
            </table>
        </div>
    @endif

    <!-- Performance score -->
    <div class="sec">
        <div class="sec-title">Performance Score</div>
        <table class="score">
            <tr>
                <td style="width: 72pt;">
                    <table><tr><td class="score-badge" style="background: {{ $sc['grade_color'] }};">
                        <div class="score-val">{{ $sc['value'] }}</div>
                        <div class="score-max">/ 100</div>
                    </td></tr></table>
                </td>
                <td>
                    <div class="score-grade" style="color: {{ $sc['grade_color'] }};">{{ $sc['grade'] }}</div>
                    <div class="score-bar-track"><div class="score-bar-fill" style="width: {{ $sc['value'] }}%; background: {{ $sc['grade_color'] }};"></div></div>
                    <div class="muted" style="font-size:7.5pt; margin-top:5pt;">Based on absences, lateness, early departures and missed clock-outs in the period.</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Footer -->
    <div class="footer">
        Generated: {{ $meta['generated_at'] }} &nbsp;·&nbsp; By: {{ $meta['generated_by'] }} &nbsp;·&nbsp; <b>CONFIDENTIAL — Internal use only</b>
    </div>

</div>
