<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>All Employees — {{ $data['period_label'] }}</title>
    @include('reports._report-styles')
    <style>
        .sum-caption { padding: 10pt 26pt 0; font-size: 8.5pt; color: #64748B; }
        table.sum { width: 100%; border-collapse: collapse; font-size: 8pt; margin-top: 8pt; }
        table.sum th { background: #1B2D5E; color: #fff; text-align: center; padding: 6pt 5pt; font-size: 7.5pt; }
        table.sum th.l, table.sum td.l { text-align: left; }
        table.sum td { padding: 5pt 5pt; border-bottom: 1pt solid #E5E7EB; text-align: center; }
        table.sum tr:nth-child(even) td { background: #F8FAFC; }
        table.sum .emp { font-weight: bold; color: #1B2D5E; }
        table.sum .dept { font-size: 7pt; color: #64748B; }
        table.sum tr.tot td { font-weight: bold; background: #EEF2FB; border-top: 1.5pt solid #1B2D5E; }
        .g { color: #059669; } .r { color: #DC2626; } .a { color: #B45309; } .b { color: #2563EB; }
    </style>
</head>
<body>
    @php
        $hrs = fn ($m) => intdiv((int) $m, 60) . 'h ' . ((int) $m % 60) . 'm';
        $n = fn ($v) => rtrim(rtrim(number_format((float) $v, 1), '0'), '.');
        $t = $data['totals'];
    @endphp

    <table class="doc-header">
        <tr>
            <td>
                <div class="brand">Trickle Hub</div>
                <div class="brand-sub">All-Employees Summary</div>
                <div class="brand-period">{{ $data['period_label'] }}</div>
            </td>
            <td class="conf"><span>CONFIDENTIAL</span></td>
        </tr>
    </table>

    <div class="sum-caption">{{ $data['count'] }} employee{{ $data['count'] == 1 ? '' : 's' }} · attendance &amp; leave totals for the period</div>

    <div style="padding: 0 26pt;">
        <table class="sum">
            <tr>
                <th class="l">Employee</th>
                <th>Present</th>
                <th>Late</th>
                <th>Absent</th>
                <th>Planned<br>leave</th>
                <th>Unplanned<br>leave</th>
                <th>WFH</th>
                <th>Hours</th>
                <th>Rate</th>
            </tr>
            @forelse($data['rows'] as $row)
                <tr>
                    <td class="l">
                        <div class="emp">{{ $row['name'] }}</div>
                        <div class="dept">{{ $row['department'] }}</div>
                    </td>
                    <td class="g">{{ $row['present'] }}</td>
                    <td class="a">{{ $row['late'] ?: '—' }}</td>
                    <td class="r">{{ $row['absent'] ?: '—' }}</td>
                    <td>{{ $row['planned'] ? $n($row['planned']) : '—' }}</td>
                    <td>{{ $row['unplanned'] ? $n($row['unplanned']) : '—' }}</td>
                    <td class="b">{{ $row['wfh'] ?: '—' }}</td>
                    <td>{{ $hrs($row['minutes']) }}</td>
                    <td>{{ $row['rate'] }}%</td>
                </tr>
            @empty
                <tr><td colspan="9" style="padding:20pt; text-align:center; color:#94A3B8;">No employees to report.</td></tr>
            @endforelse
            @if(count($data['rows']))
                <tr class="tot">
                    <td class="l">Total ({{ $data['count'] }})</td>
                    <td>{{ $t['present'] }}</td>
                    <td>{{ $t['late'] }}</td>
                    <td>{{ $t['absent'] }}</td>
                    <td>{{ $n($t['planned']) }}</td>
                    <td>{{ $n($t['unplanned']) }}</td>
                    <td>{{ $t['wfh'] }}</td>
                    <td>{{ $hrs($t['minutes']) }}</td>
                    <td>{{ $t['rate'] }}%</td>
                </tr>
            @endif
        </table>

        <div class="footer">
            Generated: {{ $data['generated_at'] }} &nbsp;·&nbsp; By: {{ $data['generated_by'] }} &nbsp;·&nbsp; <b>CONFIDENTIAL — Internal use only</b>
        </div>
    </div>
</body>
</html>
