@php
    use App\Modules\Finance\Support\Money;
    $isQuote = $document->type === 'quote';
    $isCredit = $document->type === 'credit_note';
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $isQuote ? 'Quote' : ($isCredit ? 'Credit note' : 'Invoice') }} {{ $document->number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1f2937; }
        h1 { font-size: 22px; margin: 0 0 4px; }
        .muted { color: #6b7280; }
        .row { width: 100%; margin-top: 18px; }
        .row td { vertical-align: top; }
        table.lines { width: 100%; border-collapse: collapse; margin-top: 24px; }
        table.lines th { text-align: left; background: #f3f4f6; padding: 8px; font-size: 11px; }
        table.lines td { padding: 8px; border-bottom: 1px solid #e5e7eb; }
        .r { text-align: right; }
        .totals { width: 45%; margin-left: 55%; margin-top: 14px; border-collapse: collapse; }
        .totals td { padding: 5px 8px; }
        .grand td { font-weight: bold; font-size: 14px; border-top: 2px solid #1f2937; }
        .footer { margin-top: 32px; color: #6b7280; font-size: 11px; }
        .status { display: inline-block; padding: 2px 8px; background: #ecfdf5; color: #047857; font-weight: bold; }
    </style>
</head>
<body>
    <table class="row"><tr>
        <td>
            <h1>{{ $settings->legal_name ?: config('app.name') }}</h1>
            <div class="muted">
                @if($settings->tax_number)TIN {{ $settings->tax_number }}<br>@endif
                @if($settings->address){!! nl2br(e($settings->address)) !!}<br>@endif
                {{ $settings->email }} {{ $settings->phone }}
            </div>
        </td>
        <td class="r">
            <h1>{{ $isQuote ? 'QUOTE' : ($isCredit ? 'CREDIT NOTE' : 'INVOICE') }}</h1>
            <div>{{ $document->number ?? 'Draft' }}</div>
            @if($document->status === 'paid')<div class="status">PAID</div>@endif
        </td>
    </tr></table>

    <table class="row"><tr>
        <td>
            <div class="muted">Billed to</div>
            <strong>{{ $document->contact->name }}</strong><br>
            @if($document->contact->company){{ $document->contact->company }}<br>@endif
            @if($document->contact->address){!! nl2br(e($document->contact->address)) !!}<br>@endif
            @if($document->contact->tax_number)TIN {{ $document->contact->tax_number }}@endif
        </td>
        <td class="r">
            <div><span class="muted">Issued</span> {{ $document->issue_date->format('j M Y') }}</div>
            @if($document->due_date)<div><span class="muted">Due</span> {{ $document->due_date->format('j M Y') }}</div>@endif
        </td>
    </tr></table>

    <table class="lines">
        <thead><tr><th>Description</th><th class="r">Qty</th><th class="r">Price</th><th class="r">Amount</th></tr></thead>
        <tbody>
        @foreach($document->lines as $line)
            <tr>
                <td>{{ $line->description }}</td>
                <td class="r">{{ rtrim(rtrim(number_format($line->quantity_thousandths / 1000, 3), '0'), '.') }}</td>
                <td class="r">{{ Money::format($line->unit_price, '') }}</td>
                <td class="r">{{ Money::format($line->line_total, '') }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Subtotal</td><td class="r">{{ Money::format($document->subtotal, $document->currency) }}</td></tr>
        @if($document->tax_total)<tr><td>Tax</td><td class="r">{{ Money::format($document->tax_total, $document->currency) }}</td></tr>@endif
        <tr class="grand"><td>Total</td><td class="r">{{ Money::format($document->total, $document->currency) }}</td></tr>
        @unless($isQuote || $isCredit)
            @if($document->credits_applied)<tr><td>Credit notes</td><td class="r">{{ Money::format($document->credits_applied, $document->currency) }}</td></tr>@endif
            <tr><td>Paid</td><td class="r">{{ Money::format($document->amount_paid, $document->currency) }}</td></tr>
            <tr><td><strong>Balance due</strong></td><td class="r"><strong>{{ Money::format($document->balanceDue(), $document->currency) }}</strong></td></tr>
        @endunless
    </table>

    @if($document->notes)<p class="footer">{{ $document->notes }}</p>@endif
    @if($document->terms)<p class="footer">{{ $document->terms }}</p>@endif
</body>
</html>
