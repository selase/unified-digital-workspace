@extends('layouts.metronic.app')

@section('title', 'HRMS Departments')

@section('content')
    <section class="grid gap-6">
        <div class="rounded-xl border border-border bg-background p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">HRMS Core</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Department Directory</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Top-level departments and their organizational type mappings.</p>
                </div>
                <a class="kt-btn kt-btn-outline" href="{{ route('hrms-core.index') }}">Back to Hub</a>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-background p-6">
            <div class="overflow-x-auto">
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
                            <td class="py-8 text-center text-muted-foreground" colspan="4">No departments found.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $departments->links() }}</div>
        </div>
    </section>
@endsection
