@php use App\Modules\Finance\Support\Money; @endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Receipt {{ $donation->receipt_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1f2937; }
        h1 { font-size: 22px; margin: 0 0 4px; }
        .muted { color: #6b7280; }
        .box { margin-top: 28px; padding: 18px; background: #f3f4f6; }
        .amount { font-size: 26px; font-weight: bold; margin: 6px 0; }
        table.facts { width: 100%; border-collapse: collapse; margin-top: 22px; }
        table.facts td { padding: 8px 0; border-bottom: 1px solid #e5e7eb; }
        .footer { margin-top: 36px; color: #6b7280; font-size: 11px; }
        .void { color: #b91c1c; font-weight: bold; }
    </style>
</head>
<body>
    <h1>{{ $settings->legal_name ?: config('app.name') }}</h1>
    <div class="muted">
        {{ $settings->address }}@if($settings->email) · {{ $settings->email }}@endif @if($settings->phone) · {{ $settings->phone }}@endif
        @if($settings->tax_number)<br>TIN {{ $settings->tax_number }}@endif
    </div>

    <h1 style="margin-top: 28px;">Donation receipt {{ $donation->receipt_number }}</h1>
    @if($donation->isVoid())<div class="void">VOID: this donation was cancelled.</div>@endif

    <div class="box">
        <div class="muted">Received with thanks from</div>
        <div style="font-size: 16px; font-weight: bold;">{{ $donation->donor->name }}</div>
        <div class="amount">{{ Money::format($donation->amount, $donation->currency) }}</div>
    </div>

    <table class="facts">
        <tr><td class="muted">Date</td><td>{{ $donation->date->format('j F Y') }}</td></tr>
        <tr><td class="muted">Payment method</td><td>{{ str_replace('_', ' ', ucfirst($donation->method)) }}</td></tr>
        @if($donation->reference)<tr><td class="muted">Reference</td><td>{{ $donation->reference }}</td></tr>@endif
        @if($donation->campaign)<tr><td class="muted">Campaign</td><td>{{ $donation->campaign->name }}</td></tr>@endif
        @if($donation->fund)<tr><td class="muted">Designated to</td><td>{{ $donation->fund->name }}</td></tr>@endif
        <tr><td class="muted">Donation number</td><td>{{ $donation->number }}</td></tr>
    </table>

    <div class="footer">No goods or services were provided in exchange for this gift. Thank you for your generosity.</div>
</body>
</html>
