@extends('layouts.metronic.app')

@section('title', 'HRMS Departments')

@section('content')
    <section class="grid gap-6">
        <div class="kt-card p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">HRMS Core</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Department Directory</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Top-level departments and their organizational type mappings.</p>
                </div>
                <a class="kt-btn kt-btn-outline" href="{{ route('hrms-core.index') }}">Back to Hub</a>
            </div>
        </div>

        <div class="kt-card kt-card-grid min-w-full">
            <div class="kt-card-header flex-wrap gap-3">
                <h3 class="kt-card-title text-sm">Department Registry</h3>
                <form class="flex flex-wrap items-center gap-2" method="GET" action="{{ route('hrms-core.departments.index') }}">
                    <input
                        class="kt-input w-full min-w-[220px] lg:w-[280px]"
                        name="search"
                        placeholder="Search department name"
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
                                <th>Name</th>
                                <th>Slug</th>
                                <th>Department Types</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm text-foreground">
                            @forelse($departments as $department)
                                <tr>
                                    <td class="font-medium">{{ $department->name }}</td>
                                    <td>{{ $department->slug }}</td>
                                    <td>{{ $department->department_types_count }}</td>
                                    <td>
                                        <span class="kt-badge {{ $department->is_active ? 'kt-badge-success' : 'kt-badge-outline' }}">
                                            {{ $department->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="py-8 text-center text-muted-foreground" colspan="4">No departments found for this filter.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $departments->links() }}</div>
            </div>
        </div>
    </section>
@endsection
