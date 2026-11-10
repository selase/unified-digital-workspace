<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Indicator report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; }
        h1 { font-size: 18px; margin: 0 0 2px; }
        .muted { color: #6b7280; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; background: #f3f4f6; padding: 6px; }
        td { padding: 6px; border-bottom: 1px solid #e5e7eb; }
    </style>
</head>
<body>
    <h1>{{ $organisation }}: {{ $rows[0][0] }}</h1>
    <div class="muted">{{ $rows[0][1] }}</div>
    <table>
        <thead><tr>@foreach($rows[1] as $heading)<th>{{ $heading }}</th>@endforeach</tr></thead>
        <tbody>@foreach(array_slice($rows, 2) as $row)<tr>@foreach($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>@endforeach</tbody>
    </table>
</body>
</html>
