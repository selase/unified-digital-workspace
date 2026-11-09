@extends('layouts.metronic.app')

@section('title', $grant->title)

@section('content')
@php use App\Modules\Finance\Support\Money; use App\Modules\Grants\Models\GrtGrant; @endphp
<div class="flex flex-col gap-6">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-foreground">{{ $grant->title }}</h1>
            <p class="text-xs text-muted-foreground">{{ $grant->funder->name }} · {{ GrtGrant::STATUSES[$grant->status] }} · fund: {{ $grant->fund?->name }}</p>
        </div>
        @can('grants.manage')
        <form method="POST" action="{{ route('grants.grants.status', $grant->uuid) }}" class="flex gap-2">@csrf
            <select class="kt-select" name="status">@foreach(GrtGrant::STATUSES as $key => $label)<option value="{{ $key }}" @selected($grant->status === $key)>{{ $label }}</option>@endforeach</select>
            <button class="kt-btn kt-btn-outline" type="submit">Update status</button>
        </form>
        @endcan
    </div>

    @include('grants::partials.nav')

    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
        <div class="rounded-lg bg-muted/30 p-4"><div class="text-xs text-muted-foreground">Awarded</div><div class="mt-1 font-semibold">{{ Money::format($position['awarded'], $currency) }}</div></div>
        <div class="rounded-lg bg-muted/30 p-4"><div class="text-xs text-muted-foreground">Received</div><div class="mt-1 font-semibold">{{ Money::format($position['received'], $currency) }}</div></div>
        <div class="rounded-lg bg-muted/30 p-4"><div class="text-xs text-muted-foreground">Still to receive</div><div class="mt-1 font-semibold">{{ Money::format($position['outstanding'], $currency) }}</div></div>
        <div class="rounded-lg bg-muted/30 p-4"><div class="text-xs text-muted-foreground">Spent</div><div class="mt-1 font-semibold">{{ Money::format($position['spent'], $currency) }}</div></div>
        <div class="rounded-lg bg-muted/30 p-4"><div class="text-xs text-muted-foreground">Unspent</div><div class="mt-1 font-semibold">{{ Money::format($position['remaining'], $currency) }}</div></div>
    </div>
    <div class="h-2 rounded bg-muted"><div class="h-2 rounded bg-primary" style="width: {{ $position['spent_percent'] }}%"></div></div>
    @if($grant->purpose)<p class="text-sm text-muted-foreground">Purpose: {{ $grant->purpose }}</p>@endif
    <p class="text-xs text-muted-foreground">Spending is every expense booked to this grant's fund{{ $grant->starts_on ? ' between '.$grant->starts_on->toInstitutionalDate().' and '.($grant->ends_on?->toInstitutionalDate() ?? 'today') : '' }}. Tag bills and expenses with the fund "{{ $grant->fund?->name }}" to count them here.</p>

    <div class="kt-card">
        <div class="kt-card-header"><h3 class="kt-card-title">Instalments</h3></div>
        <div class="kt-card-table kt-scrollable-x-auto"><table class="kt-table table-auto kt-table-border">
            <thead><tr><th>Expected</th><th>Status</th><th>Received</th><th class="text-end">Amount</th><th></th></tr></thead>
            <tbody>
            @forelse($grant->instalments as $item)
                <tr class="{{ $item->status === 'void' ? 'text-muted-foreground line-through' : '' }}">
                    <td class="{{ $item->isOverdue() ? 'text-destructive' : '' }}">{{ $item->expected_on->toInstitutionalDate() }}</td>
                    <td>{{ ucfirst($item->status) }}</td>
                    <td>{{ $item->received_on?->toInstitutionalDate() }} {{ $item->reference }}</td>
                    <td class="text-end">{{ Money::format($item->amount, $currency) }}</td>
                    <td class="text-end">
                        @can('grants.record')
                        @if($item->status === 'expected')
                            <form method="POST" action="{{ route('grants.grants.instalments.receive', $item->uuid) }}" class="flex justify-end gap-1">@csrf
                                <input class="kt-input w-36" type="date" name="date" value="{{ today()->toDateString() }}" required>
                                <select class="kt-select w-40" name="deposit_account_id"><option value="">Into Bank</option>@foreach($depositAccounts as $account)<option value="{{ $account->id }}">{{ $account->name }}</option>@endforeach</select>
                                <button class="kt-btn kt-btn-xs kt-btn-primary" type="submit">Receive</button>
                            </form>
                        @elseif($item->status === 'received')
                            <form method="POST" action="{{ route('grants.grants.instalments.void', $item->uuid) }}" onsubmit="return confirm('Void this instalment?')">@csrf<button class="kt-btn kt-btn-xs kt-btn-outline" type="submit">Void</button></form>
                        @endif
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted-foreground py-6">No instalments planned yet.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        @can('grants.manage')
        <form method="POST" action="{{ route('grants.grants.instalments.store', $grant->uuid) }}" class="kt-card-content flex flex-wrap gap-2">@csrf
            <input class="kt-input w-44" type="date" name="expected_on" required>
            <input class="kt-input w-44" type="number" step="0.01" min="0.01" name="amount" placeholder="Amount" required>
            <button class="kt-btn kt-btn-outline" type="submit">Plan an instalment</button>
        </form>
        @endcan
    </div>

    <div class="kt-card">
        <div class="kt-card-header"><h3 class="kt-card-title">Reporting deadlines</h3></div>
        <div class="kt-card-table kt-scrollable-x-auto"><table class="kt-table table-auto kt-table-border">
            <thead><tr><th>Due</th><th>Report</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse($grant->reports as $report)
                <tr>
                    <td class="{{ $report->isOverdue() ? 'text-destructive' : '' }}">{{ $report->due_on->toInstitutionalDate() }}</td>
                    <td>{{ $report->title }}</td>
                    <td>{{ $report->status === 'submitted' ? 'Submitted '.$report->submitted_on?->toInstitutionalDate() : ($report->isOverdue() ? 'Overdue' : 'Pending') }}</td>
                    <td class="text-end">@can('grants.manage')@if($report->status === 'pending')<form method="POST" action="{{ route('grants.grants.reports.submit', $report->uuid) }}">@csrf<button class="kt-btn kt-btn-xs kt-btn-outline" type="submit">Mark submitted</button></form>@endif @endcan</td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-center text-muted-foreground py-6">No reporting deadlines yet.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        @can('grants.manage')
        <form method="POST" action="{{ route('grants.grants.reports.store', $grant->uuid) }}" class="kt-card-content flex flex-wrap gap-2">@csrf
            <input class="kt-input w-64" name="title" placeholder="e.g. Six-month narrative report" required>
            <input class="kt-input w-44" type="date" name="due_on" required>
            <button class="kt-btn kt-btn-outline" type="submit">Add deadline</button>
        </form>
        @endcan
    </div>
</div>
@endsection
