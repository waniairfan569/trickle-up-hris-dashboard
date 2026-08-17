<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $data['employee']['name'] }} — Report</title>
    @include('reports._report-styles')
</head>
<body>
    @include('reports._letterhead')
    @include('reports._report-body', ['data' => $data])
</body>
</html>
