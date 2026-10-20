<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Receipt {{ $receipt['number'] }}</title>
    <style>
        @page { margin: 45px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #182639; line-height: 1.6; }
        h1 { font-size: 24px; } table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 10px; border-bottom: 1px solid #ddd; overflow-wrap: anywhere; }
    </style>
</head>
<body>
<p>{{ config('app.name') }}</p>
<h1>Payment receipt</h1>
<p>{{ $receipt['number'] }}</p>
<table>
    <tr><th>Invoice</th><td>{{ $receipt['invoice_number'] }}</td></tr>
    <tr><th>Received from</th><td>{{ $receipt['tenant_name'] }}</td></tr>
    <tr><th>Date paid</th><td>{{ $receipt['paid_at'] }}</td></tr>
    <tr><th>Payment provider</th><td>{{ ucfirst($receipt['provider']) }}</td></tr>
    <tr><th>Transaction reference</th><td>{{ $receipt['reference'] }}</td></tr>
    <tr><th>Amount received</th><td>{{ $receipt['currency'] }} {{ number_format($receipt['amount'] / 100, 2) }}</td></tr>
</table>
<p>Payment received in full. Thank you.</p>
</body>
</html>
