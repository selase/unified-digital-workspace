@extends('layouts.metronic.app')

@section('title', 'HRMS Leave Requests')

@section('content')
    <section class="grid gap-6">
        <div class="kt-card p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">HRMS Core</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Leave Requests</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Review leave lifecycle requests by employee, category, and status.</p>
                </div>
                <a class="kt-btn kt-btn-outline" href="{{ route('hrms-core.index') }}">Back to Hub</a>
            </div>
        </div>

        <div class="kt-card kt-card-grid min-w-full">
            <div class="kt-card-header flex-wrap gap-3">
                <h3 class="kt-card-title text-sm">Leave Register</h3>
                <form class="flex flex-wrap items-center gap-2" method="GET" action="{{ route('hrms-core.leave-requests.index') }}">
                    <input
                        class="kt-input w-full min-w-[220px] lg:w-[280px]"
                        name="search"
                        placeholder="Search employee name"
                        type="text"
                        value="{{ $search }}"
                    />
                    <select class="kt-select min-w-[160px]" name="status">
                        <option value="">All statuses</option>
                        <option value="pending" @selected($status === 'pending')>Pending</option>
                        <option value="verified" @selected($status === 'verified')>Verified</option>
                        <option value="approved" @selected($status === 'approved')>Approved</option>
                        <option value="rejected" @selected($status === 'rejected')>Rejected</option>
                        <option value="cancelled" @selected($status === 'cancelled')>Cancelled</option>
                    </select>
                    <button class="kt-btn kt-btn-outline" type="submit">Filter</button>
                </form>
            </div>
            <div class="kt-card-content">
                <div class="kt-scrollable-x-auto">
                    <table class="kt-table table-auto kt-table-border">
                        <thead>
                            <tr class="text-xs uppercase text-muted-foreground">
                                <th>Employee</th>
                                <th>Category</th>
                                <th>Period</th>
                                <th>Days</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm text-foreground">
                            @forelse($leaveRequests as $leaveRequest)
                                <tr>
                                    <td>{{ $leaveRequest->employee?->displayName() ?: 'Unknown Employee' }}</td>
                                    <td>{{ $leaveRequest->leaveCategory?->name ?: 'Unknown Category' }}</td>
                                    <td>
                                        {{ $leaveRequest->proposed_start_date?->toDateString() }}
                                        &ndash;
                                        {{ $leaveRequest->proposed_end_date?->toDateString() }}
                                    </td>
                                    <td>{{ $leaveRequest->no_requested_days }}</td>
                                    <td>
                                        @php
                                            $leaveStatusValue = (string) $leaveRequest->status->value;
                                            $leaveStatusBadge = match ($leaveStatusValue) {
                                                'approved' => 'kt-badge-success',
                                                'rejected' => 'kt-badge-danger',
                                                'pending' => 'kt-badge-warning',
                                                default => 'kt-badge-outline',
                                            };
                                        @endphp
                                        <span class="kt-badge {{ $leaveStatusBadge }}">{{ $leaveStatusValue }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="py-8 text-center text-muted-foreground" colspan="5">No leave requests found for this filter.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $leaveRequests->links() }}</div>
            </div>
        </div>
    </section>
@endsection
