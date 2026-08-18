<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $document->template_name }} — {{ optional($document->employee)->full_name }}</title>
    <style>
        @page { margin: 0; }
        * { margin: 0; padding: 0; }
        /* DomPDF honours body margins as the per-page margins — reserve room for
           the fixed letterhead header (top) and footer (bottom) on every page. */
        body { font-family: 'DejaVu Sans', sans-serif; color: #1f2937; font-size: 9.5pt; margin: 104pt 34pt 92pt 34pt; }

        .doc-title { text-align: center; margin-bottom: 4pt; }
        .doc-title h1 { font-size: 15pt; letter-spacing: 3pt; text-transform: uppercase; color: #26324f; }
        .doc-title .sub { font-size: 8pt; color: #8a93a6; font-style: italic; margin-top: 3pt; }

        .sec-head { background: #26324f; color: #ffffff; font-size: 9pt; font-weight: bold; letter-spacing: .5pt;
                    text-transform: uppercase; padding: 5pt 8pt; margin-top: 12pt; }

        table.grid { width: 100%; border-collapse: collapse; }
        table.grid td { border: 0.75pt solid #b9c2d6; vertical-align: top; padding: 5pt 7pt; }
        td.lbl { background: #eaf0fb; color: #26324f; font-weight: bold; font-size: 8.5pt; width: 18%; }
        td.val { font-size: 9pt; width: 32%; }
        td.note { background: #fdf6e3; border-color: #efdfae; color: #7a5b12; font-size: 8.5pt; padding: 6pt 9pt; }
        .opt { white-space: nowrap; padding-right: 14pt; }

        table.inner { width: 100%; border-collapse: collapse; margin: -1pt; }
        table.inner th { background: #26324f; color: #fff; font-size: 8pt; text-align: left; padding: 3.5pt 6pt; }
        table.inner td { border: 0.5pt solid #cfd8e8; font-size: 8.5pt; padding: 3.5pt 6pt; }

        .sig-img { height: 42pt; }
        .sig-line { border-top: 0.75pt solid #26324f; margin-top: 30pt; }
        .sig-meta { font-size: 8pt; color: #64748b; margin-top: 3pt; }
    </style>
</head>
<body>
    @include('reports._letterhead')

    @php
        $data = $document->data ?? [];
        $fmtDate = function ($v) {
            if (! $v) return '';
            try { return \Illuminate\Support\Carbon::parse($v)->format('d M Y'); } catch (\Throwable $e) { return $v; }
        };
        // Group a section's fields into printable rows: half-width inline fields
        // (and signatures) pair two-per-row; textarea/table/note span full width.
        $rowsFor = function ($fields) {
            $rows = []; $pending = null;
            $blocks = ['textarea', 'table', 'note'];
            foreach ($fields as $f) {
                $type = $f['type'] ?? 'text';
                $isBlock = in_array($type, $blocks, true);
                $isPairable = ! $isBlock && (($f['width'] ?? 'full') === 'half' || $type === 'signature');
                if ($isBlock) {
                    if ($pending) { $rows[] = [$pending]; $pending = null; }
                    $rows[] = [$f];
                } elseif ($isPairable) {
                    if ($pending) { $rows[] = [$pending, $f]; $pending = null; }
                    else { $pending = $f; }
                } else {
                    if ($pending) { $rows[] = [$pending]; $pending = null; }
                    $rows[] = [$f];
                }
            }
            if ($pending) { $rows[] = [$pending]; }
            return $rows;
        };
    @endphp

    <div class="doc-title">
        <h1>{{ $document->template_name }}</h1>
        @if(optional($document->template)->subtitle ?? false)
            <div class="sub">{{ $document->template->subtitle }}</div>
        @endif
    </div>

    @foreach($document->schema as $section)
        <div class="sec-head">{{ $section['title'] }}</div>
        <table class="grid">
            @foreach($rowsFor($section['fields']) as $row)
                @php $f = $row[0]; $type = $f['type'] ?? 'text'; $v = $data[$f['id']] ?? null; @endphp

                @if($type === 'note')
                    <tr><td class="note" colspan="4">{{ $f['text'] ?? '' }}</td></tr>

                @elseif($type === 'table')
                    <tr>
                        <td class="lbl">{{ $f['label'] }}</td>
                        <td class="val" colspan="3">
                            <table class="inner">
                                <thead><tr>@foreach($f['columns'] ?? [] as $c)<th>{{ $c }}</th>@endforeach</tr></thead>
                                <tbody>
                                    @forelse(is_array($v) ? $v : [] as $r)
                                        <tr>@foreach($f['columns'] ?? [] as $c)<td>{{ $r[$c] ?? '' }}</td>@endforeach</tr>
                                    @empty
                                        <tr><td colspan="{{ count($f['columns'] ?? []) }}" style="color:#9aa4b8;">—</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </td>
                    </tr>

                @elseif($type === 'textarea')
                    <tr>
                        <td class="lbl">{{ $f['label'] }}</td>
                        <td class="val" colspan="3" style="min-height:34pt;">{!! nl2br(e($v)) !!}</td>
                    </tr>

                @elseif($type === 'signature')
                    <tr>
                        @foreach($row as $sf)
                            @php $sv = $data[$sf['id']] ?? null; @endphp
                            <td class="lbl" style="width:14%;">{{ $sf['label'] }}</td>
                            <td class="val" style="width:36%;">
                                @if(is_array($sv) && ! empty($sv['image']))
                                    <img src="{{ $sv['image'] }}" class="sig-img">
                                @else
                                    <div class="sig-line"></div>
                                @endif
                                <div class="sig-meta">
                                    {{ is_array($sv) ? ($sv['name'] ?? '') : '' }}
                                    @if(is_array($sv) && ! empty($sv['date'])) &nbsp;·&nbsp; {{ $fmtDate($sv['date']) }} @endif
                                </div>
                            </td>
                        @endforeach
                        @if(count($row) === 1)<td class="val" style="border:none;"></td><td class="val" style="border:none;"></td>@endif
                    </tr>

                @else
                    {{-- Row of one or two inline fields (label | value pairs) --}}
                    <tr>
                        @foreach($row as $inf)
                            @php $iv = $data[$inf['id']] ?? null; $itype = $inf['type'] ?? 'text'; @endphp
                            <td class="lbl">{{ $inf['label'] }}</td>
                            <td class="val" @if(count($row) === 1) colspan="3" @endif>
                                @if(in_array($itype, ['checkbox', 'radio'], true))
                                    @foreach($inf['options'] ?? [] as $opt)
                                        @php $on = $itype === 'checkbox' ? (is_array($iv) && in_array($opt, $iv, true)) : ($iv === $opt); @endphp
                                        <span class="opt">{{ $on ? '☒' : '☐' }} {{ $opt }}</span>
                                    @endforeach
                                @elseif($itype === 'date')
                                    {{ $fmtDate($iv) }}
                                @else
                                    {{ is_array($iv) ? implode(', ', $iv) : $iv }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endif
            @endforeach
        </table>
    @endforeach
</body>
</html>
