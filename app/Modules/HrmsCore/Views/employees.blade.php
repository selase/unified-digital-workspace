@extends('layouts.metronic.app')

@section('title', 'HRMS Employees')

@section('content')
    <section class="grid gap-6">
        <div class="kt-card p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">HRMS Core</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Employee Directory</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Primary staff records with job grade, center placement, and profile lifecycle.</p>
                </div>
                <div class="flex items-center gap-2">
                    <a class="kt-btn kt-btn-outline" href="{{ route('hrms-core.index') }}">Back to Hub</a>
                    @can('hrms.employees.create')
                        <a class="kt-btn kt-btn-primary" href="{{ route('hrms-core.employees.create') }}">
                            <i class="ki-filled ki-plus text-base"></i>
                            Add Employee
                        </a>
                    @endcan
                </div>
            </div>
        </div>

        <div class="kt-card kt-card-grid min-w-full">
            <div class="kt-card-header flex-wrap gap-3">
                <h3 class="kt-card-title text-sm">Employee Registry</h3>
                <form class="flex flex-wrap items-center gap-2" method="GET" action="{{ route('hrms-core.employees.index') }}">
                    <input
                        class="kt-input w-full min-w-[220px] lg:w-[280px]"
                        name="search"
                        placeholder="Search name, email, staff ID"
                        type="text"
                        value="{{ $search }}"
                    />
                    <select class="kt-select min-w-[160px]" name="status">
                        <option value="">All statuses</option>
                        <option value="active" @selected($status === 'active')>Active</option>
                        <option value="inactive" @selected($status === 'inactive')>Inactive</option>
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
                                <th>Staff ID</th>
                                <th>Grade</th>
                                <th>Center</th>
                                <th>Status</th>
                                <th class="text-right">Actions</th>
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
                                    <td>
                                        <div class="flex items-center justify-end gap-2">
                                            <a class="kt-btn kt-btn-sm kt-btn-outline" href="{{ route('hrms-core.employees.show', $employee) }}">View</a>
                                            @can('hrms.employees.update')
                                                <a class="kt-btn kt-btn-sm kt-btn-outline" href="{{ route('hrms-core.employees.edit', $employee) }}">Edit</a>
                                            @endcan
                                            @can('hrms.employees.delete')
                                                <form action="{{ route('hrms-core.employees.destroy', $employee) }}" method="POST">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="kt-btn kt-btn-sm kt-btn-danger" onclick="return confirm('Delete this employee profile?');" type="submit">Delete</button>
                                                </form>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="py-8 text-center text-muted-foreground" colspan="6">No employees found for this filter.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $employees->links() }}</div>
            </div>
        </div>
    </section>
@endsection
