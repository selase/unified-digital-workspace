@extends('layouts.metronic.app')

@section('title', 'Invoice ' . $invoice->number)

@section('content')
    <section class="grid gap-6">
        <div class="rounded-xl border border-border bg-background p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Billing</p>
                    <h1 class="mt-2 text-3xl font-semibold text-foreground">Invoice #{{ $invoice->number }}</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Issued {{ $invoice->created_at->format('M d, Y') }} · Due {{ $invoice->due_at->format('M d, Y') }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" class="kt-btn kt-btn-outline" onclick="window.print()">Print Invoice</button>
                    <a href="{{ route('billing.invoices.download', $invoice->id) }}" class="kt-btn kt-btn-outline">Download PDF</a>
                    @if($invoice->status !== 'paid')
                        <form action="{{ route('billing.checkout') }}" method="POST">
                            @csrf
                            <input type="hidden" name="invoice_id" value="{{ $invoice->id }}">
                            <button type="submit" class="kt-btn kt-btn-primary">Pay Invoice Now</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-background p-6 lg:p-8">
            <div class="grid gap-6 md:grid-cols-2">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Bill To</p>
                    <h2 class="mt-2 text-xl font-semibold text-foreground">{{ $invoice->tenant->name }}</h2>
                    <p class="mt-2 text-sm text-muted-foreground">{{ $invoice->tenant->email }}</p>
                    <p class="text-sm text-muted-foreground">Ghana</p>
                </div>
                <div class="md:text-end">
                    <x-application-logo class="h-9 md:ms-auto" />
                    <p class="mt-3 text-sm text-muted-foreground">
                        {{ config('app.name') }} HQ<br>
                        123 Main Street<br>
                        Accra, Ghana
                    </p>
                </div>
            </div>

            <div class="mt-8 overflow-x-auto">
                <table class="kt-table table-auto kt-table-border">
                    <thead>
                        <tr class="text-xs uppercase text-muted-foreground">
                            <th>Description</th>
                            <th class="text-end">Qty</th>
                            <th class="text-end">Rate</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm text-foreground">
                        @foreach($invoice->items as $item)
                            <tr>
                                <td>
                                    <p class="font-medium">{{ $item->description }}</p>
                                    @if($item->metric)
                                        <p class="text-xs text-muted-foreground">Metered Usage: {{ $item->metric->name }}</p>
                                    @endif
                                </td>
                                <td class="text-end">{{ number_format((float) $item->quantity, 2) }}</td>
                                <td class="text-end">${{ number_format((float) $item->unit_price, 4) }}</td>
                                <td class="text-end font-semibold">${{ number_format((float) $item->subtotal, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-8 flex justify-end">
                <div class="w-full max-w-sm space-y-3 rounded-xl border border-border p-4">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-muted-foreground">Subtotal</span>
                        <span class="font-medium text-foreground">${{ number_format((float) $invoice->subtotal, 2) }}</span>
                    </div>

                    @if($invoice->tax_details)
                        @foreach($invoice->tax_details as $tax)
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-muted-foreground">{{ $tax['name'] }} ({{ $tax['rate'] }}%)</span>
                                <span class="font-medium text-foreground">${{ number_format((float) $tax['amount'], 2) }}</span>
                            </div>
                        @endforeach
                    @endif

                    <div class="border-t border-border pt-3 flex items-center justify-between">
                        <span class="text-base font-semibold text-foreground">Total</span>
                        <span class="text-xl font-semibold text-foreground">${{ number_format((float) $invoice->total, 2) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
