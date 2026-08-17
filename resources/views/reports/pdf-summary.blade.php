<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>All Employees — {{ $data['period_label'] }}</title>
    @include('reports._report-styles')
    <style>
        .sum-caption { padding: 12pt 26pt 0; font-size: 10.5pt; color: #64748B; }
        table.sum { width: 100%; border-collapse: collapse; font-size: 11pt; margin-top: 10pt; }
        table.sum th { background: #1B2D5E; color: #fff; text-align: center; padding: 9pt 6pt; font-size: 10.5pt; white-space: nowrap; vertical-align: middle; }
        table.sum th.l, table.sum td.l { text-align: left; }
        table.sum td { padding: 8pt 6pt; border-bottom: 1pt solid #E5E7EB; text-align: center; }
        table.sum tr:nth-child(even) td { background: #F8FAFC; }
        table.sum .emp { font-weight: bold; color: #1B2D5E; font-size: 11pt; }
        table.sum .dept { font-size: 9pt; color: #64748B; }
        table.sum tr.tot td { font-weight: bold; background: #EEF2FB; border-top: 1.5pt solid #1B2D5E; font-size: 11pt; }
        .g { color: #059669; } .r { color: #DC2626; } .a { color: #B45309; } .o { color: #EA580C; }
    </style>
</head>
<body>
    @php
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
                <th>Planned leave</th>
                <th>Unplanned leave</th>
                <th>Missing clock-out</th>
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
                    <td class="o">{{ $row['missing_clock_out'] ?: '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" style="padding:20pt; text-align:center; color:#94A3B8;">No employees to report.</td></tr>
            @endforelse
            @if(count($data['rows']))
                <tr class="tot">
                    <td class="l">Total ({{ $data['count'] }})</td>
                    <td>{{ $t['present'] }}</td>
                    <td>{{ $t['late'] }}</td>
                    <td>{{ $t['absent'] }}</td>
                    <td>{{ $n($t['planned']) }}</td>
                    <td>{{ $n($t['unplanned']) }}</td>
                    <td>{{ $t['missing_clock_out'] }}</td>
                </tr>
            @endif
        </table>

        <div class="footer" style="font-size:9pt;">
            Generated: {{ $data['generated_at'] }} &nbsp;·&nbsp; By: {{ $data['generated_by'] }} &nbsp;·&nbsp; <b>CONFIDENTIAL — Internal use only</b>
        </div>
    </div>
</body>
</html>
