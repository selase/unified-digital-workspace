<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $document->type === 'quote' ? 'Quote' : 'Invoice' }} {{ $document->number }}</title>
    <style>
        body { margin: 0; font-family: system-ui, sans-serif; background: #f4f5f7; color: #1f2937; }
        .wrap { max-width: 720px; margin: 0 auto; padding: 24px 16px; }
        .card { background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        h1 { font-size: 20px; margin: 0 0 4px; }
        .muted { color: #6b7280; font-size: 13px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { text-align: left; padding: 8px 0; font-size: 14px; }
        th.r, td.r { text-align: right; }
        tfoot td { font-weight: 600; }
        .btn { display: inline-block; margin-top: 20px; padding: 12px 20px; border: 0; border-radius: 8px; background: #2563eb; color: #fff; font-size: 15px; cursor: pointer; }
        .ok { background: #ecfdf5; color: #047857; padding: 10px 14px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; }
        .bad { background: #fef2f2; color: #b91c1c; padding: 10px 14px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; }
    </style>
</head>
<body>
<div class="wrap">
    @if(session('success'))<div class="ok">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="bad">{{ session('error') }}</div>@endif
    @php use App\Modules\Finance\Support\Money; @endphp
    <div class="card">
        <h1>{{ $settings->legal_name ?: 'Invoice' }}</h1>
        <div class="muted">{{ $document->type === 'quote' ? 'Quote' : 'Invoice' }} {{ $document->number }} · Issued {{ $document->issue_date->toInstitutionalDate() }}@if($document->due_date) · Due {{ $document->due_date->toInstitutionalDate() }}@endif</div>
        <div class="muted">For {{ $document->contact->name }}@if($settings->tax_number) · TIN {{ $settings->tax_number }}@endif</div>

        <table>
            <thead><tr><th>Description</th><th class="r">Qty</th><th class="r">Amount</th></tr></thead>
            <tbody>
            @foreach($document->lines as $line)
                <tr><td>{{ $line->description }}</td><td class="r">{{ rtrim(rtrim(number_format($line->quantity_thousandths / 1000, 3), '0'), '.') }}</td><td class="r">{{ Money::format($line->line_total, '') }}</td></tr>
            @endforeach
            </tbody>
            <tfoot>
                @if($document->tax_total)<tr><td colspan="2" class="r">Tax</td><td class="r">{{ Money::format($document->tax_total, '') }}</td></tr>@endif
                <tr><td colspan="2" class="r">Total</td><td class="r">{{ Money::format($document->total, $document->currency) }}</td></tr>
                <tr><td colspan="2" class="r">Paid</td><td class="r">{{ Money::format($document->amount_paid, $document->currency) }}</td></tr>
                <tr><td colspan="2" class="r">Balance due</td><td class="r">{{ Money::format($document->balanceDue(), $document->currency) }}</td></tr>
            </tfoot>
        </table>

        @if($document->status === 'paid')
            <p class="ok" style="margin-top:20px">This invoice has been paid in full. Thank you.</p>
        @elseif($canPay)
            <form method="POST" action="{{ $payUrl }}">@csrf<button class="btn" type="submit">Pay {{ Money::format($document->balanceDue(), $document->currency) }}</button></form>
        @endif

        @if($document->terms)<p class="muted" style="margin-top:20px">{{ $document->terms }}</p>@endif
    </div>
</div>
</body>
</html>
