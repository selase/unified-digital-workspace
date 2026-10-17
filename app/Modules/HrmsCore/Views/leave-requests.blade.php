@extends('layouts.metronic.app')

@section('title', 'Leave Requests & Approvals')

@section('content')
<div class="flex flex-col gap-5">
    {{-- Page Header --}}
    <x-metronic.page-header
        title="Leave Requests & Absence Management"
        description="Statutory and casual leave applications, supervisor verifications, and departmental approvals."
    >
        <x-slot:actions>
            <a href="{{ route('hrms-core.index') }}" class="kt-btn kt-btn-outline">
                Back to Hub
            </a>
            <a href="{{ route('hrms-core.employees.index') }}" class="kt-btn kt-btn-outline">
                Employee Directory
            </a>
            @can('hrms.leave.view')
                <a href="{{ route('hrms-core.leave-requests.create') }}" class="kt-btn kt-btn-primary flex items-center gap-1.5">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Request Leave
                </a>
            @endcan
        </x-slot:actions>
    </x-metronic.page-header>

    @if(session('message'))
        <div class="rounded-xl border border-emerald-500/20 bg-emerald-500/10 p-4 text-emerald-800 dark:text-emerald-300 text-sm flex items-center gap-2">
            <svg class="h-5 w-5 shrink-0 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            {{ session('message') }}
        </div>
    @endif

    {{-- Top Metrics Row: 4 Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="rounded-xl border border-border bg-card p-4 flex flex-col justify-between">
            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Total Requests</span>
            <div class="mt-2 flex items-baseline justify-between">
                <span class="text-2xl font-bold text-foreground">{{ $metrics['total'] ?? $leaveRequests->total() }}</span>
                <span class="text-[11px] text-muted-foreground">All historical</span>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-card p-4 flex flex-col justify-between">
            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Pending Decisions</span>
            <div class="mt-2 flex items-baseline justify-between">
                <span class="text-2xl font-bold text-amber-600 dark:text-amber-400">{{ $metrics['pending'] ?? 0 }}</span>
                <span class="text-[11px] text-muted-foreground">Action required</span>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-card p-4 flex flex-col justify-between">
            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Approved Leaves</span>
            <div class="mt-2 flex items-baseline justify-between">
                <span class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ $metrics['approved'] ?? 0 }}</span>
                <span class="text-[11px] text-muted-foreground">Granted absences</span>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-card p-4 flex flex-col justify-between">
            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Rejected / Revoked</span>
            <div class="mt-2 flex items-baseline justify-between">
                <span class="text-2xl font-bold text-destructive">{{ $metrics['rejected'] ?? 0 }}</span>
                <span class="text-[11px] text-muted-foreground">Declined</span>
            </div>
        </div>
    </div>

    {{-- Table Card with Filter Bar --}}
    <x-metronic.card title="All Leave Requests ({{ $leaveRequests->total() }})" subtitle="Filter and process employee leave applications">
        {{-- Filters --}}
        <div class="mb-5 pb-4 border-b border-border">
            <form method="GET" action="{{ route('hrms-core.leave-requests.index') }}" class="flex flex-wrap items-center gap-3">
                <div class="flex-1 min-w-[220px]">
                    <input
                        type="text"
                        name="search"
                        value="{{ $search }}"
                        placeholder="Search employee name or staff ID…"
                        class="kt-input w-full"
                    />
                </div>
                <div class="w-48">
                    <select name="status" onchange="this.form.submit()" class="kt-select w-full">
                        <option value="">All Statuses</option>
                        <option value="pending" @selected($status === 'pending')>Pending Decision</option>
                        <option value="verified" @selected($status === 'verified')>Supervisor Verified</option>
                        <option value="approved" @selected($status === 'approved')>Approved</option>
                        <option value="rejected" @selected($status === 'rejected')>Rejected</option>
                        <option value="cancelled" @selected($status === 'cancelled')>Cancelled</option>
                    </select>
                </div>
                <button type="submit" class="kt-btn kt-btn-primary">Filter</button>
                @if($search !== '' || $status !== '')
                    <a href="{{ route('hrms-core.leave-requests.index') }}" class="kt-btn kt-btn-outline">Clear</a>
                @endif
            </form>
        </div>

        {{-- Table --}}
        <div class="kt-scrollable-x-auto overflow-x-auto">
            <table class="kt-table table-auto kt-table-border w-full text-left">
                <thead>
                    <tr class="text-xs uppercase text-muted-foreground border-b border-border">
                        <th class="py-3 px-3">Employee</th>
                        <th class="py-3 px-3">Leave Category</th>
                        <th class="py-3 px-3">Leave Period</th>
                        <th class="py-3 px-3 text-center">Days</th>
                        <th class="py-3 px-3">Status</th>
                        <th class="py-3 px-3">Reason / Remarks</th>
                        <th class="py-3 px-3 text-right">Approval Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border text-xs">
                    @forelse($leaveRequests as $leaveRequest)
                        @php
                            $leaveStatusValue = is_object($leaveRequest->status) ? $leaveRequest->status->value : (string) $leaveRequest->status;
                            $leaveStatusVariant = match ($leaveStatusValue) {
                                'approved' => 'success',
                                'rejected' => 'destructive',
                                'pending' => 'warning',
                                'verified' => 'primary',
                                default => 'outline',
                            };
                        @endphp
                        <tr class="hover:bg-muted/30 transition">
                            <td class="py-3 px-3">
                                <p class="font-semibold text-foreground">{{ $leaveRequest->employee?->displayName() ?: 'Unknown Employee' }}</p>
                                <p class="text-[11px] text-muted-foreground font-mono">{{ $leaveRequest->employee?->employee_staff_id ?: 'No Staff ID' }}</p>
                            </td>
                            <td class="py-3 px-3">
                                <span class="px-2 py-0.5 rounded-full bg-muted text-[11px] font-medium text-foreground">
                                    {{ $leaveRequest->leaveCategory?->name ?: 'Standard Leave' }}
                                </span>
                            </td>
                            <td class="py-3 px-3 text-muted-foreground whitespace-nowrap">
                                {{ $leaveRequest->proposed_start_date?->format('d/m/Y') }} &ndash; {{ $leaveRequest->proposed_end_date?->format('d/m/Y') }}
                            </td>
                            <td class="py-3 px-3 text-center font-bold text-foreground">
                                {{ $leaveRequest->no_requested_days }}
                            </td>
                            <td class="py-3 px-3">
                                <x-metronic.badge :variant="$leaveStatusVariant" size="sm">
                                    {{ ucfirst($leaveStatusValue) }}
                                </x-metronic.badge>
                            </td>
                            <td class="py-3 px-3 text-muted-foreground max-w-xs truncate">
                                {{ $leaveRequest->leave_reasons ?: '—' }}
                            </td>
                            <td class="py-3 px-3 text-right">
                                <div class="inline-flex items-center gap-1.5 justify-end">
                                    @if($leaveStatusValue === 'pending' || $leaveStatusValue === 'verified')
                                        @can('hrms.leave.approve')
                                            <form method="POST" action="{{ route('hrms-core.leave-requests.approve', $leaveRequest) }}">
                                                @csrf
                                                <button type="submit" class="kt-btn kt-btn-sm kt-btn-primary py-0.5 px-2 text-xs">
                                                    Approve
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('hrms-core.leave-requests.reject', $leaveRequest) }}">
                                                @csrf
                                                <button type="submit" class="kt-btn kt-btn-sm kt-btn-outline py-0.5 px-2 text-xs text-destructive hover:bg-destructive/10">
                                                    Reject
                                                </button>
                                            </form>
                                        @else
                                            <span class="text-xs text-muted-foreground italic">Awaiting Approval</span>
                                        @endcan
                                    @else
                                        <span class="text-xs text-muted-foreground font-mono">Decision Logged</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-muted-foreground">
                                No leave requests found matching the current filter.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4 pt-3 border-t border-border">
            {{ $leaveRequests->links() }}
        </div>
    </x-metronic.card>
</div>
@endsection
