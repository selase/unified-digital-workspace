<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Invoice {{ $invoice->number }}</title>
    <style>
        @page {
            margin: 40px 50px;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            color: #3f4254;
            line-height: 1.5;
            margin: 0;
            padding: 0;
        }

        /* Header */
        .header {
            margin-bottom: 30px;
        }
        .header table { width: 100%; }
        .logo {
            font-size: 14px;
            font-weight: bold;
            color: #181c32;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .badge {
            display: inline-block;
            padding: 2px 10px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-radius: 4px;
            color: #ffffff;
        }
        .badge-draft { background-color: #f6c000; color: #181c32; }
        .badge-issued { background-color: #009ef7; }
        .badge-paid { background-color: #50cd89; }
        .badge-overdue { background-color: #f1416c; }
        .invoice-number {
            text-align: right;
            font-size: 20px;
            font-weight: bold;
            color: #181c32;
        }
        .invoice-label {
            text-align: right;
            font-size: 9px;
            color: #a1a5b7;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Info grid */
        .info-table {
            width: 100%;
            margin-bottom: 25px;
            border-collapse: collapse;
        }
        .info-table td {
            vertical-align: top;
            padding: 12px 16px;
            background-color: #f9fafb;
            border-radius: 6px;
        }
        .info-label {
            font-size: 9px;
            font-weight: bold;
            color: #a1a5b7;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }
        .info-value {
            font-size: 12px;
            color: #181c32;
            font-weight: 600;
        }
        .info-sub {
            font-size: 11px;
            color: #7e8299;
            font-weight: normal;
        }
        .amount-due {
            font-size: 22px;
            font-weight: bold;
            color: #181c32;
        }

        /* Divider */
        .divider {
            border: none;
            border-top: 1px solid #e5e7eb;
            margin: 0 0 25px 0;
        }

        /* Items table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .items-table th {
            padding: 8px 12px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #a1a5b7;
            border-bottom: 1px solid #e5e7eb;
        }
        .items-table th.text-left { text-align: left; }
        .items-table th.text-center { text-align: center; }
        .items-table th.text-right { text-align: right; }
        .items-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #f1f3f5;
            vertical-align: top;
            font-size: 12px;
        }
        .items-table td.text-center { text-align: center; }
        .items-table td.text-right { text-align: right; }
        .item-name {
            font-weight: 600;
            color: #181c32;
        }
        .item-meta {
            font-size: 10px;
            color: #a1a5b7;
            margin-top: 2px;
        }

        /* Totals */
        .totals-table {
            width: 250px;
            margin-left: auto;
            border-collapse: collapse;
        }
        .totals-table td {
            padding: 5px 0;
            font-size: 12px;
        }
        .totals-table .label-cell {
            text-align: right;
            color: #a1a5b7;
            padding-right: 16px;
            font-size: 11px;
        }
        .totals-table .value-cell {
            text-align: right;
            color: #181c32;
            font-weight: 600;
        }
        .totals-table .total-row td {
            border-top: 2px solid #e5e7eb;
            padding-top: 10px;
            font-size: 14px;
            font-weight: bold;
            color: #181c32;
        }

        /* Footer */
        .footer {
            margin-top: 60px;
            padding-top: 16px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            font-size: 10px;
            color: #a1a5b7;
        }
    </style>
</head>
<body>
    {{-- Header --}}
    <div class="header">
        <table>
            <tr>
                <td style="width: 50%; vertical-align: top;">
                    <div class="logo">{{ config('app.name') }}</div>
                    <div style="margin-top: 8px;">
                        <span class="badge badge-{{ $invoice->status }}">{{ strtoupper($invoice->status) }}</span>
                        @if($invoice->due_at)
                            <span style="font-size: 10px; color: #a1a5b7; margin-left: 6px;">Due by {{ $invoice->due_at->format('M d, Y') }}</span>
                        @endif
                    </div>
                </td>
                <td style="width: 50%; vertical-align: top;">
                    <div class="invoice-label">Invoice Number</div>
                    <div class="invoice-number">#{{ $invoice->number }}</div>
                    <div style="text-align: right; margin-top: 4px;">
                        <span class="invoice-label">Issue Date</span><br>
                        <span style="font-size: 12px; color: #181c32;">{{ $invoice->created_at->format('M d, Y') }}</span>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    {{-- Info cards --}}
    <table class="info-table">
        <tr>
            <td style="width: 33%;">
                <div class="info-label">Billed To</div>
                <div class="info-value">{{ $invoice->tenant->name }}</div>
                <div class="info-sub">{{ $invoice->tenant->email }}</div>
            </td>
            <td style="width: 2%;"></td>
            <td style="width: 33%;">
                <div class="info-label">Period</div>
                <div class="info-value">{{ $invoice->period_start->format('M d, Y') }} &ndash; {{ $invoice->period_end->format('M d, Y') }}</div>
            </td>
            <td style="width: 2%;"></td>
            <td style="width: 30%; text-align: right;">
                <div class="info-label">Amount Due</div>
                <div class="amount-due">${{ number_format((float)$invoice->total, 2) }}</div>
                @if($invoice->due_at)
                    <div class="info-sub">Due by {{ $invoice->due_at->format('M d, Y') }}</div>
                @endif
            </td>
        </tr>
    </table>

    <hr class="divider">

    {{-- Line items --}}
    <table class="items-table">
        <thead>
            <tr>
                <th class="text-left">Description</th>
                <th class="text-center" style="width: 70px;">Qty</th>
                <th class="text-center" style="width: 100px;">Unit Price</th>
                <th class="text-right" style="width: 100px;">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->items as $item)
                <tr>
                    <td>
                        <div class="item-name">{{ $item->description }}</div>
                        @if($item->metric)
                            <div class="item-meta">Metric: {{ $item->metric->name }}</div>
                        @endif
                    </td>
                    <td class="text-center">{{ number_format((float)$item->quantity, 2) }}</td>
                    <td class="text-center">${{ number_format((float)$item->unit_price, 2) }}</td>
                    <td class="text-right" style="font-weight: 600;">${{ number_format((float)$item->subtotal, 2) }}</td>
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
            <td class="label-cell">Total</td>
            <td class="value-cell">${{ number_format((float)$invoice->total, 2) }}</td>
        </tr>
    </table>

    <div class="footer">
        Thank you for your business! If you have any questions about this invoice, please reach out to our support team.
    </div>
</body>
</html>
