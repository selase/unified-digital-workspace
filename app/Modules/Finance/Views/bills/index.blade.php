@extends('layouts.metronic.app')

@section('title', 'Bills')

@section('content')
@php use App\Modules\Finance\Support\Money; @endphp
<div class="flex flex-col gap-6">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-foreground">Bills</h1>
            <p class="text-xs text-muted-foreground">What suppliers have billed you. Approving a bill posts it to the ledger.</p>
        </div>
        @can('finance.bills.manage')
            <a href="{{ route('finance.bills.create') }}" class="kt-btn kt-btn-primary">New bill</a>
        @endcan
    </div>

    @include('finance::partials.nav')

    <div class="kt-card">
        <div class="kt-card-header">
            <form method="GET" class="flex gap-2">
                <select class="kt-select w-48" name="status" onchange="this.form.submit()">
                    <option value="">All statuses</option>
                    @foreach(['draft' => 'Draft', 'approved' => 'Approved', 'partly_paid' => 'Partly paid', 'paid' => 'Paid', 'void' => 'Void'] as $key => $label)
                        <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </form>
        </div>
        <div class="kt-card-table kt-scrollable-x-auto">
            <table class="kt-table table-auto kt-table-border">
                <thead><tr><th>Number</th><th>Supplier</th><th>Their ref.</th><th>Issued</th><th>Due</th><th>Status</th><th class="text-end">Total</th><th class="text-end">Balance</th></tr></thead>
                <tbody>
                @forelse($bills as $bill)
                    <tr>
                        <td><a class="text-primary" href="{{ route('finance.bills.show', $bill->uuid) }}">{{ $bill->number ?? 'Draft' }}</a></td>
                        <td>{{ $bill->contact->name }}</td>
                        <td>{{ $bill->vendor_reference }}</td>
                        <td>{{ $bill->issue_date->toInstitutionalDate() }}</td>
                        <td>{{ $bill->due_date?->toInstitutionalDate() }}</td>
                        <td>{{ $bill->isOverdue() ? 'Overdue' : str_replace('_', ' ', ucfirst($bill->status)) }}</td>
                        <td class="text-end">{{ Money::format($bill->total, $bill->currency) }}</td>
                        <td class="text-end">{{ $bill->isOpen() ? Money::format($bill->balanceDue(), $bill->currency) : '' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted-foreground py-6">No bills yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    {{ $bills->links() }}
</div>
@endsection
