@extends('layouts.metronic.app')

@section('title', $document->number ?? 'Draft')

@section('content')
@php use App\Modules\Finance\Support\Money; $isQuote = $document->type === 'quote'; $isCredit = $document->type === 'credit_note'; @endphp
<div class="flex flex-col gap-6">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-foreground">{{ $isQuote ? 'Quote' : ($isCredit ? 'Credit note' : 'Invoice') }} {{ $document->number ?? '(draft)' }}</h1>
            <p class="text-xs text-muted-foreground">{{ $document->contact->name }} · {{ $document->isOverdue() ? 'Overdue' : str_replace('_', ' ', ucfirst($document->status)) }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('finance.invoices.pdf', $document->uuid) }}" class="kt-btn kt-btn-sm kt-btn-outline" target="_blank">PDF</a>
            @can('finance.invoices.manage')
                @if($document->status === 'draft')
                    <a href="{{ route('finance.invoices.edit', $document->uuid) }}" class="kt-btn kt-btn-sm kt-btn-outline">Edit</a>
                    <form method="POST" action="{{ route('finance.invoices.send', $document->uuid) }}">@csrf<button class="kt-btn kt-btn-sm kt-btn-primary" type="submit">Issue {{ $isQuote ? 'quote' : ($isCredit ? 'credit note' : 'invoice') }}</button></form>
                @endif
                @if(! in_array($document->status, ['draft', 'void'], true) && $document->contact->email)
                    <form method="POST" action="{{ route('finance.invoices.email', $document->uuid) }}">@csrf<button class="kt-btn kt-btn-sm kt-btn-outline" type="submit">Email to customer</button></form>
                @endif
                @if(! $isQuote && ! $isCredit && in_array($document->status, ['sent', 'partly_paid'], true) && $document->balanceDue() > 0)
                    <form method="POST" action="{{ route('finance.invoices.credit-note', $document->uuid) }}">@csrf<button class="kt-btn kt-btn-sm kt-btn-outline" type="submit">Create credit note</button></form>
                @endif
                @if($isQuote && $document->converted_invoice_id === null && $document->status === 'sent')
                    <form method="POST" action="{{ route('finance.invoices.convert', $document->uuid) }}">@csrf<button class="kt-btn kt-btn-sm kt-btn-primary" type="submit">Convert to invoice</button></form>
                @endif
                @if(! in_array($document->status, ['void', 'paid'], true) && $document->amount_paid === 0)
                    <form method="POST" action="{{ route('finance.invoices.void', $document->uuid) }}" onsubmit="return confirm('{{ $document->status === 'draft' ? 'Delete this draft?' : 'Void this document?' }}')">@csrf<button class="kt-btn kt-btn-sm kt-btn-outline" type="submit">{{ $document->status === 'draft' ? 'Delete draft' : 'Void' }}</button></form>
                @endif
            @endcan
            @can('finance.payments.manage')
                @if(! $isQuote && ! $isCredit && in_array($document->status, ['sent', 'partly_paid'], true))
                    <a href="{{ route('finance.payments.index', ['invoice' => $document->uuid]) }}" class="kt-btn kt-btn-sm kt-btn-primary">Record payment</a>
                @endif
            @endcan
        </div>
    </div>

    @include('finance::partials.nav')

    @if($document->evat_status !== 'not_required')
        <div class="rounded-lg bg-muted/30 p-3 text-sm">e-VAT: <strong>{{ str_replace('_', ' ', ucfirst($document->evat_status)) }}</strong>{{ $document->evat_reference ? ' · reference '.$document->evat_reference : '' }}{{ $document->evat_message ? ' · '.$document->evat_message : '' }}</div>
    @endif

    @if($payLink)
        <div class="rounded-lg bg-muted/30 p-4 text-sm" x-data="{ copied: false }">
            <div class="font-medium text-foreground">Payment link</div>
            <div class="mt-1 flex flex-col sm:flex-row gap-2">
                <input class="kt-input" readonly value="{{ $payLink }}" id="pay-link" onclick="this.select()">
                <button type="button" class="kt-btn kt-btn-outline" @click="navigator.clipboard.writeText(document.getElementById('pay-link').value); copied = true">
                    <span x-text="copied ? 'Copied' : 'Copy link'"></span>
                </button>
            </div>
            <p class="mt-1 text-xs text-muted-foreground">Share this with the customer. They pay online and the payment is recorded automatically.</p>
        </div>
    @endif

    <div class="kt-card">
        <div class="kt-card-content grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
            <div><div class="text-xs text-muted-foreground">Customer</div><div>{{ $document->contact->name }}</div><div class="text-muted-foreground">{{ $document->contact->email }}</div></div>
            <div><div class="text-xs text-muted-foreground">Issued</div><div>{{ $document->issue_date->toInstitutionalDate() }}</div></div>
            <div><div class="text-xs text-muted-foreground">Due</div><div>{{ $document->due_date?->toInstitutionalDate() ?? 'On issue' }}</div></div>
        </div>
        <div class="kt-card-table kt-scrollable-x-auto">
            <table class="kt-table table-auto kt-table-border">
                <thead><tr><th>Description</th><th class="text-end">Qty</th><th class="text-end">Price</th><th class="text-end">Tax</th><th class="text-end">Amount</th></tr></thead>
                <tbody>
                @foreach($document->lines as $line)
                    <tr>
                        <td>{{ $line->description }}</td>
                        <td class="text-end">{{ rtrim(rtrim(number_format($line->quantity_thousandths / 1000, 3), '0'), '.') }}</td>
                        <td class="text-end">{{ Money::format($line->unit_price, '') }}</td>
                        <td class="text-end">{{ $line->tax_amount ? Money::format($line->tax_amount, '') : '' }}</td>
                        <td class="text-end">{{ Money::format($line->line_total, '') }}</td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot>
                    <tr><td colspan="4" class="text-end text-muted-foreground">Subtotal</td><td class="text-end">{{ Money::format($document->subtotal, $document->currency) }}</td></tr>
                    @if($document->tax_total)<tr><td colspan="4" class="text-end text-muted-foreground">Tax</td><td class="text-end">{{ Money::format($document->tax_total, $document->currency) }}</td></tr>@endif
                    <tr><td colspan="4" class="text-end font-semibold">Total</td><td class="text-end font-semibold">{{ Money::format($document->total, $document->currency) }}</td></tr>
                    @unless($isQuote || $isCredit)
                    <tr><td colspan="4" class="text-end text-muted-foreground">Paid</td><td class="text-end">{{ Money::format($document->amount_paid, $document->currency) }}</td></tr>
                    @if($document->credits_applied)<tr><td colspan="4" class="text-end text-muted-foreground">Credit notes</td><td class="text-end">{{ Money::format($document->credits_applied, $document->currency) }}</td></tr>@endif
                    <tr><td colspan="4" class="text-end font-semibold">Balance due</td><td class="text-end font-semibold">{{ Money::format($document->balanceDue(), $document->currency) }}</td></tr>
                    @endunless
                </tfoot>
            </table>
        </div>
    </div>
    @if($document->journal_id)
        <p class="text-xs text-muted-foreground">Ledger entry: <a class="text-primary" href="{{ route('finance.journals.index') }}">see journals</a></p>
    @endif
</div>
@endsection
