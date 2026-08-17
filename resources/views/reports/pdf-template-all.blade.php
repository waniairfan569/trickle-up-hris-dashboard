<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>All Employees — {{ $periodLabel }}</title>
    @include('reports._report-styles')
</head>
<body>
    @forelse($allData as $data)
        @include('reports._report-body', ['data' => $data])
        @if(! $loop->last)
            <div class="page-break"></div>
        @endif
    @empty
        <div style="padding:40pt; text-align:center; font-family:'DejaVu Sans';">No employees to report.</div>
    @endforelse
</body>
</html>
