@extends('layouts.metronic.app')

@section('title', 'HRMS Employees')

@section('content')
    <section class="grid gap-6">
        <div class="rounded-xl border border-border bg-background p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">HRMS Core</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Employee Directory</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Primary staff records with grade and center mapping.</p>
                </div>
                <a class="kt-btn kt-btn-outline" href="{{ route('hrms-core.index') }}">Back to Hub</a>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-background p-6">
            <div class="overflow-x-auto">
                <table class="kt-table table-auto kt-table-border">
                    <thead>
                    <tr class="text-xs uppercase text-muted-foreground">
                        <th>Employee</th>
                        <th>Staff ID</th>
                        <th>Grade</th>
                        <th>Center</th>
                        <th>Status</th>
                    </tr>
                    </thead>
                    <tbody class="text-sm text-foreground">
                    @forelse($employees as $employee)
                        <tr>
                            <td>
                                <p class="font-medium">{{ $employee->displayName() }}</p>
                                <p class="text-xs text-muted-foreground">{{ $employee->email ?: 'No email' }}</p>
                            </td>
                            <td>{{ $employee->employee_staff_id ?: '—' }}</td>
                            <td>{{ $employee->grade?->name ?: '—' }}</td>
                            <td>{{ $employee->center?->name ?: '—' }}</td>
                            <td>
                                <span class="kt-badge {{ $employee->is_active ? 'kt-badge-success' : 'kt-badge-warning' }}">
                                    {{ $employee->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="py-8 text-center text-muted-foreground" colspan="5">No employees found.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $employees->links() }}</div>
        </div>
    </section>
@endsection
