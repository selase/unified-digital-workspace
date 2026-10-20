<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>Invoice {{ $invoice->number }}</title>
    <style>
        body { font: 1rem/1.6 system-ui, sans-serif; color: #182639; background: #f3f5f7; margin: 0; padding: 24px; }
        main { max-width: 720px; margin: 32px auto; padding: 24px; background: white; border: 1px solid #d9dfe5; border-radius: 12px; }
        h1 { overflow-wrap: anywhere; font-size: 1.6rem; } table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 4px; text-align: left; border-bottom: 1px solid #d9dfe5; overflow-wrap: anywhere; }
        button { padding: 12px 20px; background: #173f68; color: white; border: 0; border-radius: 6px; font: inherit; cursor: pointer; }
        a { color: #173f68; } .notice { padding: 12px; background: #eef3f8; } footer { margin-top: 24px; }
    </style>
</head>
<body>
<main>
    <p>{{ config('app.name') }} · Billing</p>
    <h1>Invoice {{ $invoice->number }}</h1>
    <p>{{ $invoice->tenant?->name }}</p>
    <p>Status: <strong>{{ ucfirst($invoice->status) }}</strong> · Due {{ $invoice->due_at?->format('j M Y') ?? 'on receipt' }}</p>
    @if($additionalPayments->isNotEmpty())
        <p role="status" class="notice">An additional payment was received after this invoice was settled. It is recorded for billing support to reconcile. Your original receipt remains valid; no automatic refund has been made.</p>
    @endif
    @if(session('error'))<p role="alert" class="notice">{{ session('error') }}</p>@endif
    @if(session('success'))<p role="status" class="notice">{{ session('success') }}</p>@endif
    <table>
        <caption>Invoice items</caption>
        <thead><tr><th scope="col">Description</th><th scope="col">Amount ({{ $invoice->currency }})</th></tr></thead>
        <tbody>
        @forelse($invoice->items as $item)
            <tr><td>{{ $item->description }}</td><td>{{ number_format((float) $item->subtotal, 2) }}</td></tr>
        @empty
            <tr><td>Billing period {{ $invoice->period_start?->format('j M Y') }} – {{ $invoice->period_end?->format('j M Y') }}</td><td>{{ number_format((float) $invoice->subtotal, 2) }}</td></tr>
        @endforelse
        </tbody>
        <tfoot>
            <tr><th scope="row">Subtotal</th><td>{{ number_format((float) $invoice->subtotal, 2) }}</td></tr>
            <tr><th scope="row">Tax</th><td>{{ number_format((float) $invoice->tax_total, 2) }}</td></tr>
            <tr><th scope="row">Total</th><td><strong>{{ $invoice->currency }} {{ number_format((float) $invoice->total, 2) }}</strong></td></tr>
        </tfoot>
    </table>
    <p><a href="{{ $invoiceUrl }}">Download invoice PDF</a></p>
    @if($invoice->status === \App\Models\Invoice::STATUS_PAID)
        @if(data_get($invoice->meta, 'receipt.number'))
            <p>Receipt {{ data_get($invoice->meta, 'receipt.number') }}</p>
            <a href="{{ $receiptUrl }}">Download payment receipt</a>
        @else
            <p>This invoice is paid. Please contact billing support for its historical receipt.</p>
        @endif
    @elseif($paymentAvailable)
        <form method="POST" action="{{ $payUrl }}">
            @csrf
            <button type="submit">Pay {{ $invoice->currency }} {{ number_format((float) $invoice->total, 2) }}</button>
        </form>
        <p>You will continue to secure hosted checkout.</p>
    @else
        <p role="status" class="notice">Online payment is awaiting setup. Please contact billing support. Do not send card details by email.</p>
    @endif
    <footer>Keep this private link secure. It provides access to this invoice and its receipt.</footer>
</main>
</body>
</html>
