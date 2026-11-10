<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; }
        h1 { font-size: 18px; margin: 0; }
        .muted { color: #6b7280; margin-top: 2px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th { text-align: left; background: #f3f4f6; padding: 6px; font-size: 10px; }
        td { padding: 5px 6px; border-bottom: 1px solid #e5e7eb; }
        .r { text-align: right; }
        .section td { font-weight: bold; background: #fafafa; }
        .footer { margin-top: 20px; color: #9ca3af; font-size: 9px; }
    </style>
</head>
<body>
    <div class="muted">{{ $organisation }}</div>
    <h1>{{ $title }}</h1>
    @if($subtitle !== '')<div class="muted">{{ $subtitle }}</div>@endif
    <table>
        @if(count($header))
        <thead><tr>@foreach($header as $i => $cell)<th class="{{ $i > 0 ? 'r' : '' }}">{{ $cell }}</th>@endforeach</tr></thead>
        @endif
        <tbody>
        @foreach($body as $row)
            @php $isSection = count($row) > 1 && collect($row)->slice(1)->every(fn ($c) => $c === '' || $c === null) && ($row[0] ?? '') !== ''; @endphp
            <tr class="{{ $isSection ? 'section' : '' }}">@foreach($row as $i => $cell)<td class="{{ $i > 0 ? 'r' : '' }}">{{ $cell }}</td>@endforeach</tr>
        @endforeach
        </tbody>
    </table>
    <div class="footer">Generated {{ now()->format('j F Y H:i') }}</div>
</body>
</html>
