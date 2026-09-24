<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Letterhead preview — {{ $letterhead->name }}</title>
<style>
    @page { margin: 0; }
    * { margin: 0; padding: 0; }
    /* Body margins reserve room for the fixed letterhead header + footer. */
    body { font-family: 'DejaVu Sans', sans-serif; color: #1f2937; font-size: 10pt; margin: 104pt 34pt 92pt 34pt; }
    h1 { text-align: center; font-size: 15pt; letter-spacing: 3pt; text-transform: uppercase; color: #26324f; margin-bottom: 12pt; }
    p { margin: 7pt 0; line-height: 1.55; }
    .muted { color: #8a93a6; font-style: italic; }
</style>
</head>
<body>
    @include('reports._letterhead', ['letterhead' => $letterhead])

    <h1>Sample Document</h1>
    <p class="muted">Preview of the “{{ $letterhead->name }}” letterhead — the branded header and footer repeat on every page of any HR document generated with it.</p>
    <p>Dear [Employee Name],</p>
    <p>This letter confirms the details discussed. It demonstrates how body text sits between the letterhead header and footer, with comfortable margins so nothing overlaps the branding.</p>
    <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Praesent euismod, nisi vel consectetur euismod, nisl nunc euismod nisi, euismod aliquam nisi nunc euismod. Sed euismod, nisi vel consectetur euismod.</p>
    <p>Kind regards,<br>[Manager Name]</p>
</body>
</html>
