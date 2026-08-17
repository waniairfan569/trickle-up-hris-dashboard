<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>All Employees — {{ $data['period_label'] }}</title>
    @include('reports._report-styles')
    <style>
        .sum-caption { padding: 12pt 26pt 0; font-size: 10.5pt; color: #64748B; }
        table.sum { width: 100%; border-collapse: collapse; font-size: 11pt; margin-top: 10pt; }
        table.sum th { background: #fcd82f; color: #1a1a24; text-align: center; padding: 9pt 6pt; font-size: 10.5pt; white-space: nowrap; vertical-align: middle; }
        table.sum th.l, table.sum td.l { text-align: left; }
        table.sum td { padding: 8pt 6pt; border-bottom: 1pt solid #E5E7EB; text-align: center; }
        table.sum tr:nth-child(even) td { background: #F8FAFC; }
        table.sum .emp { font-weight: bold; color: #1a1a24; font-size: 11pt; }
        table.sum .dept { font-size: 9pt; color: #64748B; }
        table.sum tr.tot td { font-weight: bold; background: #FEF9E0; border-top: 1.5pt solid #fcd82f; font-size: 11pt; }
        .g { color: #059669; } .r { color: #DC2626; } .a { color: #B45309; } .o { color: #EA580C; }

        /* Day-wise detail per employee */
        .dw-head { font-size: 13pt; font-weight: bold; color: #1a1a24; border-bottom: 2pt solid #fcd82f; padding-bottom: 5pt; margin-bottom: 8pt; }
        .dw-emp { font-size: 11pt; font-weight: bold; color: #1a1a24; margin: 12pt 0 3pt; }
        .dw-dept { font-size: 9pt; font-weight: normal; color: #64748B; }
        table.dw { width: 100%; border-collapse: collapse; font-size: 9pt; margin-bottom: 4pt; }
        table.dw th { background: #1a1a24; color: #fff; text-align: center; padding: 5pt 6pt; font-size: 8.5pt; }
        table.dw th.l, table.dw td.l { text-align: left; }
        table.dw td { padding: 4.5pt 6pt; border-bottom: 1pt solid #E5E7EB; text-align: center; }
        table.dw tr:nth-child(even) td { background: #F8FAFC; }
    </style>
</head>
<body>
    @include('reports._letterhead')
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

        {{-- Day-wise detail per employee (monthly / short range) --}}
        @php $anyDaily = collect($data['rows'])->contains(fn ($r) => ! empty($r['daily'])); @endphp
        @if($anyDaily)
            @php
                $dwStatus = ['present'=>'Present','late'=>'Late','absent'=>'Absent','overtime'=>'Overtime','early_departure'=>'Early out','on_leave'=>'On leave','missing_clock_out'=>'No clock-out','half_day'=>'Half day','weekend'=>'Weekend'];
            @endphp
            <div class="page-break"></div>
            <div class="dw-head">Day-wise Attendance — {{ $data['period_label'] }}</div>
            @foreach($data['rows'] as $row)
                <div class="dw-emp">{{ $row['name'] }} <span class="dw-dept">· {{ $row['department'] }}</span></div>
                @if(! empty($row['daily']))
                    <table class="dw">
                        <tr><th class="l">Date</th><th>Day</th><th>In</th><th>Out</th><th>Hours</th><th>Late</th><th class="l">Status</th></tr>
                        @foreach($row['daily'] as $d)
                            <tr>
                                <td class="l">{{ $d['date'] }}</td>
                                <td>{{ $d['day'] }}</td>
                                <td>{{ $d['clock_in'] }}</td>
                                <td>{{ $d['clock_out'] }}</td>
                                <td>{{ $d['hours'] }}</td>
                                <td>{{ $d['late_minutes'] ? $d['late_minutes'].'m' : '—' }}</td>
                                <td class="l"><span class="st st-{{ $d['status'] }}">{{ $dwStatus[$d['status']] ?? ucfirst(str_replace('_',' ',$d['status'])) }}</span></td>
                            </tr>
                        @endforeach
                    </table>
                @else
                    <div style="font-size:8.5pt; color:#94A3B8; margin-bottom:4pt;">No attendance records in this period.</div>
                @endif
            @endforeach
        @endif

        <div class="footer" style="font-size:9pt;">
            Generated: {{ $data['generated_at'] }} &nbsp;·&nbsp; By: {{ $data['generated_by'] }} &nbsp;·&nbsp; <b>CONFIDENTIAL — Internal use only</b>
        </div>
    </div>
</body>
</html>
