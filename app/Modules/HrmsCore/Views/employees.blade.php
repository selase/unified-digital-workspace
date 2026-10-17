@extends('layouts.metronic.app')

@section('title', 'HRMS Employee Directory')

@section('content')
<div class="flex flex-col gap-6">
    <x-metronic.page-header
        title="Employee Directory"
        description="Institutional staff registry, organizational assignments, grade classifications, and active statuses."
    >
        <x-slot:actions>
            <a href="{{ route('hrms-core.index') }}" class="kt-btn kt-btn-outline">
                Back to Hub
            </a>
            <a href="{{ route('hrms-core.leave-requests.index') }}" class="kt-btn kt-btn-outline">
                Leave Roster
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

    <x-metronic.card title="Staff Registry ({{ $employees->total() }})" subtitle="Directory of enrolled institutional personnel and organizational placements">
        <div class="mb-5 pb-4 border-b border-border">
            <form method="GET" action="{{ route('hrms-core.employees.index') }}" class="flex flex-wrap items-center gap-3">
                <div class="flex-1 min-w-[240px]">
                    <input
                        type="text"
                        name="search"
                        value="{{ $search }}"
                        placeholder="Search name, staff ID, or email address…"
                        class="kt-input w-full"
                    />
                </div>
                <div class="w-44">
                    <select name="status" onchange="this.form.submit()" class="kt-select w-full">
                        <option value="">All Statuses</option>
                        <option value="active" @selected($status === 'active')>Active Only</option>
                        <option value="inactive" @selected($status === 'inactive')>Inactive Only</option>
                    </select>
                </div>
                <button type="submit" class="kt-btn kt-btn-outline">Filter</button>
                @if($search || $status)
                    <a href="{{ route('hrms-core.employees.index') }}" class="kt-btn kt-btn-ghost text-xs text-muted-foreground">Reset</a>
                @endif
            </form>
        </div>

        <div class="kt-scrollable-x-auto">
            <table class="kt-table table-auto kt-table-border">
                <thead>
                    <tr class="text-xs uppercase text-muted-foreground border-b border-border bg-muted/20">
                        <th class="py-3 px-4 font-semibold text-left">Staff Member</th>
                        <th class="py-3 px-4 font-semibold text-left">Staff ID</th>
                        <th class="py-3 px-4 font-semibold text-left">Grade</th>
                        <th class="py-3 px-4 font-semibold text-left">Center</th>
                        <th class="py-3 px-4 font-semibold text-left">Status</th>
                        <th class="py-3 px-4 font-semibold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border text-sm">
                    @forelse($employees as $employee)
                        <tr class="hover:bg-muted/10 transition-colors">
                            <td class="py-3.5 px-4">
                                <div class="flex items-center gap-3">
                                    <div class="h-9 w-9 rounded-full bg-primary/10 text-primary font-bold flex items-center justify-center text-xs shrink-0 border border-primary/20">
                                        {{ strtoupper(substr($employee->first_name ?: 'E', 0, 1) . substr($employee->last_name ?: 'M', 0, 1)) }}
                                    </div>
                                    <div>
                                        <a href="{{ route('hrms-core.employees.show', $employee) }}" class="font-medium text-foreground hover:text-primary transition-colors">
                                            {{ $employee->displayName() }}
                                        </a>
                                        <p class="text-xs text-muted-foreground">{{ $employee->email ?: 'No email registered' }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3.5 px-4">
                                @if($employee->employee_staff_id)
                                    <span class="font-mono text-xs px-2 py-0.5 rounded bg-muted text-foreground border border-border">
                                        {{ $employee->employee_staff_id }}
                                    </span>
                                @else
                                    <span class="text-xs text-muted-foreground italic">—</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="text-sm text-foreground">{{ $employee->grade?->name ?: '—' }}</span>
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="text-sm text-foreground">{{ $employee->center?->name ?: '—' }}</span>
                            </td>
                            <td class="py-3.5 px-4">
                                <x-metronic.badge :variant="$employee->is_active ? 'success' : 'secondary'" size="sm">
                                    {{ $employee->is_active ? 'Active' : 'Inactive' }}
                                </x-metronic.badge>
                            </td>
                            <td class="py-3.5 px-4 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <a href="{{ route('hrms-core.employees.show', $employee) }}" class="kt-btn kt-btn-sm kt-btn-outline text-xs">
                                        Profile
                                    </a>
                                    @can('hrms.employees.update')
                                        <a href="{{ route('hrms-core.employees.edit', $employee) }}" class="kt-btn kt-btn-sm kt-btn-ghost text-xs">
                                            Edit
                                        </a>
                                    @endcan
                                    @can('hrms.employees.delete')
                                        <form action="{{ route('hrms-core.employees.destroy', $employee) }}" method="POST" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="kt-btn kt-btn-sm kt-btn-ghost text-xs text-destructive hover:bg-destructive/10" onclick="return confirm('Are you sure you want to delete this staff record?')">
                                                Delete
                                            </button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-12 text-center text-muted-foreground">
                                <p class="text-sm">No employee records found matching your filters.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4 pt-4 border-t border-border">
            {{ $employees->links() }}
        </div>
    </x-metronic.card>
</div>
@endsection
