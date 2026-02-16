@extends('layouts.metronic.app')

@section('title', 'HRMS Leave Requests')

@section('content')
    <section class="grid gap-6">
        <div class="rounded-xl border border-border bg-background p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">HRMS Core</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Leave Requests</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Review leave lifecycle requests by employee, category, and status.</p>
                </div>
                <a class="kt-btn kt-btn-outline" href="{{ route('hrms-core.index') }}">Back to Hub</a>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-background p-6">
            <div class="overflow-x-auto">
                <table class="kt-table">
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
                                -
                                {{ $leaveRequest->proposed_end_date?->toDateString() }}
                            </td>
                            <td>{{ $leaveRequest->no_requested_days }}</td>
                            <td><span class="kt-badge kt-badge-outline">{{ (string) $leaveRequest->status->value }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td class="py-8 text-center text-muted-foreground" colspan="5">No leave requests found.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $leaveRequests->links() }}</div>
        </div>
    </section>
@endsection
