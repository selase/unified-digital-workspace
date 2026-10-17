@extends('layouts.metronic.app')

@section('title', 'HRMS People & Talent Hub')

@section('content')
<div class="flex flex-col gap-5">
    {{-- Page Header --}}
    <x-metronic.page-header
        title="People Operations & Talent Hub"
        description="Institutional human resource management, employee lifecycle directory, leave management, and organizational hierarchy."
    >
        <x-slot:actions>
            <a href="{{ route('hrms-core.employees.index') }}" class="kt-btn kt-btn-outline">
                Employee Directory
            </a>
            <a href="{{ route('hrms-core.leave-requests.index') }}" class="kt-btn kt-btn-outline">
                Leave Register
            </a>
            <a href="{{ route('hrms-core.departments.index') }}" class="kt-btn kt-btn-outline">
                Departments
            </a>
            <a href="{{ route('hrms-core.appraisals.index') }}" class="kt-btn kt-btn-outline">
                Appraisals
            </a>
            <a href="{{ route('hrms-core.appraisals.index', ['scope' => 'mine']) }}" class="kt-btn kt-btn-outline">
                My Appraisals
            </a>
            @can('hrms.employees.create')
                <a href="{{ route('hrms-core.employees.create') }}" class="kt-btn kt-btn-primary flex items-center gap-1.5">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Add Employee
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

    {{-- Top Metrics Row: 5 Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        <div class="rounded-xl border border-border bg-card p-4 flex flex-col justify-between">
            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Total Staff</span>
            <div class="mt-2 flex items-baseline justify-between">
                <span class="text-2xl font-bold text-foreground">{{ $totalEmployees }}</span>
                <span class="text-[11px] text-muted-foreground">Headcount</span>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-card p-4 flex flex-col justify-between">
            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Active Employees</span>
            <div class="mt-2 flex items-baseline justify-between">
                <span class="text-2xl font-bold text-primary">{{ $activeEmployees }}</span>
                <span class="text-[11px] text-muted-foreground">On duty</span>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-card p-4 flex flex-col justify-between">
            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Departments</span>
            <div class="mt-2 flex items-baseline justify-between">
                <span class="text-2xl font-bold text-foreground">{{ $totalDepartments }}</span>
                <span class="text-[11px] text-muted-foreground">Operational units</span>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-card p-4 flex flex-col justify-between">
            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Pending Leaves</span>
            <div class="mt-2 flex items-baseline justify-between">
                <span class="text-2xl font-bold {{ $pendingLeaves > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-foreground' }}">
                    {{ $pendingLeaves }}
                </span>
                <span class="text-[11px] text-muted-foreground">Awaiting review</span>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-card p-4 flex flex-col justify-between">
            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Open Requisitions</span>
            <div class="mt-2 flex items-baseline justify-between">
                <span class="text-2xl font-bold text-foreground">{{ $activePostings }}</span>
                <span class="text-[11px] text-muted-foreground">Talent pipeline</span>
            </div>
        </div>
    </div>

    {{-- Main Grid: Left Staff & Leave Approvals, Right Departments & Standards --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
        {{-- Left 8 cols: Recent Employees & Pending Leave Requests --}}
        <div class="lg:col-span-8 flex flex-col gap-5">
            {{-- Pending Leave Approvals Card --}}
            <x-metronic.card
                title="Leave Requests Awaiting Decision ({{ $pendingLeaveRequests->count() }})"
                subtitle="Employee absence requests requiring administrative review"
            >
                <div class="overflow-x-auto">
                    <table class="kt-table table-auto kt-table-border w-full text-left">
                        <thead>
                            <tr class="text-xs uppercase text-muted-foreground border-b border-border">
                                <th class="py-3 px-3">Employee</th>
                                <th class="py-3 px-3">Category</th>
                                <th class="py-3 px-3">Period</th>
                                <th class="py-3 px-3 text-center">Days</th>
                                <th class="py-3 px-3 text-right">Approval Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border text-xs">
                            @forelse($pendingLeaveRequests as $leave)
                                <tr class="hover:bg-muted/30 transition">
                                    <td class="py-3 px-3">
                                        <p class="font-semibold text-foreground">{{ $leave->employee?->displayName() ?: 'Unknown Employee' }}</p>
                                        <p class="text-[11px] text-muted-foreground font-mono">{{ $leave->employee?->employee_staff_id ?: 'Staff ID Pending' }}</p>
                                    </td>
                                    <td class="py-3 px-3">
                                        <span class="px-2 py-0.5 rounded-full bg-muted text-[11px] font-medium text-foreground">
                                            {{ $leave->leaveCategory?->name ?: 'General Leave' }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-3 text-muted-foreground whitespace-nowrap">
                                        {{ $leave->proposed_start_date?->format('d/m/Y') }} – {{ $leave->proposed_end_date?->format('d/m/Y') }}
                                    </td>
                                    <td class="py-3 px-3 text-center font-bold text-foreground">
                                        {{ $leave->no_requested_days }}
                                    </td>
                                    <td class="py-3 px-3 text-right">
                                        <div class="inline-flex items-center gap-1.5 justify-end">
                                            @can('hrms.leave.approve')
                                                <form method="POST" action="{{ route('hrms-core.leave-requests.approve', $leave) }}">
                                                    @csrf
                                                    <button type="submit" class="kt-btn kt-btn-sm kt-btn-primary py-0.5 px-2 text-xs">
                                                        Approve
                                                    </button>
                                                </form>
                                                <form method="POST" action="{{ route('hrms-core.leave-requests.reject', $leave) }}">
                                                    @csrf
                                                    <button type="submit" class="kt-btn kt-btn-sm kt-btn-outline py-0.5 px-2 text-xs text-destructive hover:bg-destructive/10">
                                                        Reject
                                                    </button>
                                                </form>
                                            @else
                                                <x-metronic.badge variant="warning" size="sm">Pending</x-metronic.badge>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-6 text-center text-muted-foreground">
                                        Zero pending leave requests awaiting approval.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 pt-3 border-t border-border flex justify-end">
                    <a href="{{ route('hrms-core.leave-requests.index') }}" class="text-xs font-semibold text-primary hover:underline">
                        Open Full Leave Register &rarr;
                    </a>
                </div>
            </x-metronic.card>

            {{-- Recent Staff Directory Card --}}
            <x-metronic.card
                title="Employee Registry & Profiles"
                subtitle="Institutional workforce directory and organizational mapping"
            >
                <div class="overflow-x-auto">
                    <table class="kt-table table-auto kt-table-border w-full text-left">
                        <thead>
                            <tr class="text-xs uppercase text-muted-foreground border-b border-border">
                                <th class="py-3 px-3">Staff Member</th>
                                <th class="py-3 px-3">Staff ID</th>
                                <th class="py-3 px-3">Job Grade</th>
                                <th class="py-3 px-3">Center / Location</th>
                                <th class="py-3 px-3">Status</th>
                                <th class="py-3 px-3 text-right">Profile</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border text-xs">
                            @forelse($recentEmployees as $employee)
                                <tr class="hover:bg-muted/30 transition">
                                    <td class="py-3 px-3">
                                        <a href="{{ route('hrms-core.employees.show', $employee) }}" class="font-semibold text-foreground hover:text-primary transition">
                                            {{ $employee->displayName() }}
                                        </a>
                                        <p class="text-[11px] text-muted-foreground">{{ $employee->email ?: 'No email registered' }}</p>
                                    </td>
                                    <td class="py-3 px-3 font-mono text-[11px]">
                                        {{ $employee->employee_staff_id ?: '—' }}
                                    </td>
                                    <td class="py-3 px-3 text-muted-foreground">
                                        {{ $employee->grade?->name ?: 'Unassigned' }}
                                    </td>
                                    <td class="py-3 px-3 text-muted-foreground">
                                        {{ $employee->center?->name ?: 'Main Center' }}
                                    </td>
                                    <td class="py-3 px-3">
                                        <x-metronic.badge :variant="$employee->is_active ? 'success' : 'warning'" size="sm">
                                            {{ $employee->is_active ? 'Active' : 'Inactive' }}
                                        </x-metronic.badge>
                                    </td>
                                    <td class="py-3 px-3 text-right">
                                        <a href="{{ route('hrms-core.employees.show', $employee) }}" class="kt-btn kt-btn-outline py-1 px-2.5 text-xs">
                                            View
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-8 text-center text-muted-foreground">
                                        No employee records registered yet.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 pt-3 border-t border-border flex justify-end">
                    <a href="{{ route('hrms-core.employees.index') }}" class="text-xs font-semibold text-primary hover:underline">
                        View All Employees in Directory &rarr;
                    </a>
                </div>
            </x-metronic.card>
        </div>

        {{-- Right 4 cols: Department Summary & Standards --}}
        <div class="lg:col-span-4 flex flex-col gap-5">
            {{-- Department Distribution --}}
            <x-metronic.card title="Department Headcount" subtitle="Staff distribution across organizational units">
                <div class="space-y-3">
                    @forelse($departmentsSummary as $dept)
                        <div class="p-3 rounded-xl border border-border bg-card flex items-center justify-between">
                            <div>
                                <p class="text-xs font-semibold text-foreground">{{ $dept->name }}</p>
                                <p class="text-[11px] text-muted-foreground font-mono">{{ $dept->slug }}</p>
                            </div>
                            <span class="px-2.5 py-1 rounded-full bg-primary/10 text-primary font-bold text-xs">
                                {{ $dept->employees_count }} staff
                            </span>
                        </div>
                    @empty
                        <div class="py-6 text-center text-muted-foreground">
                            <p class="text-xs">No active departments configured.</p>
                        </div>
                    @endforelse
                </div>
                <div class="mt-3 pt-2 border-t border-border text-right">
                    <a href="{{ route('hrms-core.departments.index') }}" class="text-xs font-semibold text-primary hover:underline">
                        Department Directory &rarr;
                    </a>
                </div>
            </x-metronic.card>

            {{-- Institutional Compliance Callout --}}
            <div class="rounded-xl border border-border bg-card p-5 space-y-3">
                <div class="flex items-center gap-2 text-primary font-semibold text-xs uppercase tracking-wider">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                    Institutional HR Governance
                </div>
                <p class="text-xs text-muted-foreground leading-relaxed">
                    HRMS Core enforces statutory leave tracking, grade salary structures, multi-level promotion recommendations, and role-based staff record access.
                </p>
                <div class="pt-2 border-t border-border flex items-center justify-between text-xs text-muted-foreground">
                    <span>Active Features</span>
                    <span>{{ $featureCount }} Enabled</span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
