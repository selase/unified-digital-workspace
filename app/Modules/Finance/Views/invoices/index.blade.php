@extends('layouts.metronic.app')

@section('title', $type === 'quote' ? 'Quotes' : ($type === 'credit_note' ? 'Credit notes' : 'Invoices'))

@section('content')
@php use App\Modules\Finance\Support\Money; $label = $type === 'quote' ? 'Quotes' : ($type === 'credit_note' ? 'Credit notes' : 'Invoices'); @endphp
<div class="flex flex-col gap-6">
    <div class="flex items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-foreground">{{ $label }}</h1>
            <p class="text-xs text-muted-foreground">{{ $type === 'quote' ? 'Offers sent to customers. Convert an accepted quote into an invoice.' : ($type === 'credit_note' ? 'Reduce what a customer owes. Raise one from an invoice.' : 'Invoices post to the ledger when they are issued.') }}</p>
        </div>
        @can('finance.invoices.manage')
            @if($type !== 'credit_note')
                <a href="{{ route('finance.invoices.create', ['type' => $type]) }}" class="kt-btn kt-btn-sm kt-btn-primary">New {{ $type === 'quote' ? 'quote' : 'invoice' }}</a>
            @endif
        @endcan
    </div>

    @include('finance::partials.nav')

    <div class="kt-card">
        <div class="kt-card-header">
            <form method="GET" class="flex gap-2">
                <input type="hidden" name="type" value="{{ $type }}">
                <select class="kt-select" name="status" onchange="this.form.submit()">
                    <option value="">All statuses</option>
                    @foreach(['draft', 'sent', 'partly_paid', 'paid', 'overdue', 'void', 'accepted', 'applied'] as $option)
                        <option value="{{ $option }}" @selected($status === $option)>{{ str_replace('_', ' ', ucfirst($option)) }}</option>
                    @endforeach
                </select>
            </form>
        </div>
        <div class="kt-card-table kt-scrollable-x-auto">
            <table class="kt-table table-auto kt-table-border">
                <thead><tr><th>Number</th><th>Customer</th><th>Issued</th><th>Due</th><th>Status</th><th class="text-end">Total</th><th class="text-end">Balance</th></tr></thead>
                <tbody>
                @forelse($documents as $document)
                    <tr>
                        <td><a class="text-primary" href="{{ route('finance.invoices.show', $document->uuid) }}">{{ $document->number ?? 'Draft' }}</a></td>
                        <td>{{ $document->contact->name }}</td>
                        <td>{{ $document->issue_date->toInstitutionalDate() }}</td>
                        <td>{{ $document->due_date?->toInstitutionalDate() }}</td>
                        <td>{{ $document->isOverdue() ? 'Overdue' : str_replace('_', ' ', ucfirst($document->status)) }}</td>
                        <td class="text-end">{{ Money::format($document->total, $document->currency) }}</td>
                        <td class="text-end">{{ $document->type === 'invoice' ? Money::format($document->balanceDue(), $document->currency) : '' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted-foreground py-6">Nothing found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    {{ $documents->links() }}
</div>
@endsection
