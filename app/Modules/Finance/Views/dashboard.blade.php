@extends('layouts.metronic.app')

@section('title', 'Finance')

@section('content')
@php use App\Modules\Finance\Support\Money; @endphp
<div class="flex flex-col gap-6">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-foreground">Finance</h1>
            <p class="text-xs text-muted-foreground">Your own books: invoices, payments and the ledger behind them.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('finance.invoices.create', ['type' => 'quote']) }}" class="kt-btn kt-btn-sm kt-btn-outline">New quote</a>
            <a href="{{ route('finance.invoices.create') }}" class="kt-btn kt-btn-sm kt-btn-primary">New invoice</a>
        </div>
    </div>

    @include('finance::partials.nav')

    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
        @foreach([
            'Cash and clearing' => $figures['cash'],
            'Owed to you' => $figures['receivables'],
            'Overdue' => $figures['overdue'],
            'Income this month' => $figures['income_month'],
            'Expenses this month' => $figures['expense_month'],
        ] as $label => $value)
            <div class="rounded-lg bg-muted/30 p-4">
                <div class="text-xs text-muted-foreground">{{ $label }}</div>
                <div class="mt-1 text-lg font-semibold text-foreground">{{ Money::format($value, $currency) }}</div>
            </div>
        @endforeach
    </div>

    <div class="kt-card">
        <div class="kt-card-header"><h3 class="kt-card-title">Recent invoices and quotes</h3></div>
        <div class="kt-card-table kt-scrollable-x-auto">
            <table class="kt-table table-auto kt-table-border">
                <thead><tr><th>Number</th><th>Customer</th><th>Status</th><th class="text-end">Total</th></tr></thead>
                <tbody>
                @forelse($recentInvoices as $document)
                    <tr>
                        <td><a class="text-primary" href="{{ route('finance.invoices.show', $document->uuid) }}">{{ $document->number ?? 'Draft' }}</a></td>
                        <td>{{ $document->contact->name }}</td>
                        <td>{{ str_replace('_', ' ', ucfirst($document->status)) }}</td>
                        <td class="text-end">{{ Money::format($document->total, $document->currency) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted-foreground py-6">Nothing here yet. Add a customer, then create your first invoice.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
