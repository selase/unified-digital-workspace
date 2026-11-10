<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Certificate of service</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 13px; color: #1f2937; text-align: center; }
        .frame { border: 3px solid #1f2937; padding: 48px 40px; margin-top: 12px; }
        h1 { font-size: 30px; margin: 18px 0 6px; letter-spacing: 1px; }
        .muted { color: #6b7280; }
        .name { font-size: 26px; font-weight: bold; margin: 22px 0; }
        .hours { font-size: 34px; font-weight: bold; margin: 12px 0; }
        table { width: 70%; margin: 28px auto 0; border-collapse: collapse; font-size: 11px; text-align: left; }
        td { padding: 4px 0; border-bottom: 1px solid #e5e7eb; }
    </style>
</head>
<body>
<div class="frame">
    <div style="font-size: 16px; font-weight: bold;">{{ $settings->legal_name ?: config('app.name') }}</div>
    <div class="muted">{{ $settings->address }}</div>
    <h1>Certificate of Service</h1>
    <div class="muted">This certifies that</div>
    <div class="name">{{ $volunteer->name }}</div>
    <div class="muted">gave their time as a volunteer, with a total of</div>
    <div class="hours">{{ number_format($totalMinutes / 60, 1) }} hours</div>
    <div class="muted">between {{ $first->format('j F Y') }} and {{ $last->format('j F Y') }}, as recorded and approved by the organisation.</div>
    <div class="muted" style="margin-top: 22px;">Issued {{ now()->format('j F Y') }}</div>

    <table>
        @foreach($hours->groupBy(fn ($hour) => $hour->worked_on->format('F Y')) as $month => $group)
            <tr><td>{{ $month }}</td><td style="text-align: right;">{{ number_format($group->sum('minutes') / 60, 1) }} hours</td></tr>
        @endforeach
    </table>
</div>
</body>
</html>
