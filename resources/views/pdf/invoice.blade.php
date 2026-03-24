<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Invoice {{ $invoice->number }}</title>
    <style>
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 13px;
            color: #3f4254;
            line-height: 1.5;
            margin: 0;
            padding: 0;
        }
        .container {
            width: 100%;
            padding: 40px;
        }
        .header-table {
            width: 100%;
            margin-bottom: 30px;
        }
        .logo {
            font-size: 22px;
            font-weight: bold;
            color: #009EF7;
        }
        .invoice-title {
            text-align: right;
            font-size: 28px;
            font-weight: bold;
            text-transform: uppercase;
            color: #181c32;
            letter-spacing: 2px;
        }
        .info-table {
            width: 100%;
            margin-bottom: 30px;
            border-collapse: collapse;
        }
        .info-table td {
            vertical-align: top;
            padding: 0;
        }
        .info-label {
            font-size: 9px;
            font-weight: bold;
            color: #a1a5b7;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding-bottom: 4px;
        }
        .info-value {
            font-size: 13px;
            color: #3f4254;
            line-height: 1.6;
        }
        .info-value strong {
            font-weight: 600;
            color: #181c32;
        }
        .divider {
            border: none;
            border-top: 1px solid #eff2f5;
            margin: 0 0 30px 0;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .items-table th {
            background-color: #f5f8fa;
            padding: 10px 12px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #7e8299;
            border-bottom: 2px solid #eff2f5;
        }
        .items-table th.col-desc { text-align: left; }
        .items-table th.col-qty { text-align: center; width: 80px; }
        .items-table th.col-rate { text-align: right; width: 120px; }
        .items-table th.col-amount { text-align: right; width: 120px; }
        .items-table td {
            padding: 12px;
            border-bottom: 1px solid #eff2f5;
            vertical-align: top;
        }
        .items-table td.col-desc { text-align: left; }
        .items-table td.col-qty { text-align: center; }
        .items-table td.col-rate { text-align: right; }
        .items-table td.col-amount { text-align: right; }
        .item-name {
            font-weight: 600;
            color: #181c32;
        }
        .item-meta {
            font-size: 10px;
            color: #b5b5c3;
            margin-top: 2px;
        }
        .totals-table {
            width: 100%;
            border-collapse: collapse;
        }
        .totals-table td {
            padding: 6px 12px;
            font-size: 13px;
        }
        .totals-table .label-cell {
            text-align: right;
            color: #7e8299;
        }
        .totals-table .value-cell {
            text-align: right;
            width: 120px;
            color: #3f4254;
        }
        .totals-table .total-row td {
            border-top: 2px solid #eff2f5;
            padding-top: 12px;
            font-size: 16px;
            font-weight: bold;
            color: #181c32;
        }
        .footer {
            margin-top: 80px;
            padding-top: 20px;
            border-top: 1px solid #eff2f5;
            text-align: center;
            font-size: 11px;
            color: #a1a5b7;
        }
    </style>
</head>
<body>
    <div class="container">
        {{-- Header --}}
        <table class="header-table">
            <tr>
                <td style="width: 60%;"><span class="logo">{{ config('app.name') }}</span></td>
                <td style="width: 40%;"><span class="invoice-title">Invoice</span></td>
            </tr>
        </table>

        {{-- Info section --}}
        <table class="info-table">
            <tr>
                <td style="width: 40%;">
                    <div class="info-label">Issued By</div>
                    <div class="info-value">
                        <strong>{{ config('app.name') }}</strong><br>
                        123 Business Avenue<br>
                        Accra, Ghana
                    </div>
                </td>
                <td style="width: 30%;">
                    <div class="info-label">Billed To</div>
                    <div class="info-value">
                        <strong>{{ $invoice->tenant->name }}</strong><br>
                        {{ $invoice->tenant->email }}
                    </div>
                </td>
                <td style="width: 30%; text-align: right;">
                    <div class="info-label">Invoice Number</div>
                    <div class="info-value"><strong>#{{ $invoice->number }}</strong></div>
                    <br>
                    <div class="info-label">Issue Date</div>
                    <div class="info-value">{{ $invoice->created_at->format('M d, Y') }}</div>
                    @if($invoice->due_at)
                        <br>
                        <div class="info-label">Due Date</div>
                        <div class="info-value">{{ $invoice->due_at->format('M d, Y') }}</div>
                    @endif
                </td>
            </tr>
        </table>

        <hr class="divider">

        {{-- Line items --}}
        <table class="items-table">
            <thead>
                <tr>
                    <th class="col-desc">Description</th>
                    <th class="col-qty">Qty</th>
                    <th class="col-rate">Rate</th>
                    <th class="col-amount">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->items as $item)
                    <tr>
                        <td class="col-desc">
                            <div class="item-name">{{ $item->description }}</div>
                            @if($item->metric)
                                <div class="item-meta">Metered: {{ $item->metric->name }}</div>
                            @endif
                        </td>
                        <td class="col-qty">{{ number_format((float)$item->quantity, 2) }}</td>
                        <td class="col-rate">${{ number_format((float)$item->unit_price, 2) }}</td>
                        <td class="col-amount">${{ number_format((float)$item->subtotal, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Totals --}}
        <table class="totals-table">
            <tr>
                <td class="label-cell">Subtotal</td>
                <td class="value-cell">${{ number_format((float)$invoice->subtotal, 2) }}</td>
            </tr>
            @if($invoice->tax_details)
                @foreach($invoice->tax_details as $tax)
                    <tr>
                        <td class="label-cell">{{ $tax['name'] }} ({{ $tax['rate'] }}%)</td>
                        <td class="value-cell">${{ number_format((float)$tax['amount'], 2) }}</td>
                    </tr>
                @endforeach
            @endif
            <tr class="total-row">
                <td class="label-cell">Total Due</td>
                <td class="value-cell">${{ number_format((float)$invoice->total, 2) }}</td>
            </tr>
        </table>

        <div class="footer">
            Thank you for your business! If you have any questions about this invoice, please reach out to our support team.
        </div>
    </div>
</body>
</html>
