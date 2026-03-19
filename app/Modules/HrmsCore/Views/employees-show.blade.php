@extends('layouts.metronic.app')

@section('title', 'Employee Profile')

@section('content')
    <section class="grid gap-6">
        <div class="kt-card p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">HRMS Core</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">{{ $employee->fullName() }}</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Staff profile reference {{ $employee->employee_staff_id ?: 'Not assigned' }}.</p>
                </div>
                <div class="flex items-center gap-2">
                    @can('hrms.employees.update')
                        <a class="kt-btn kt-btn-outline" href="{{ route('hrms-core.employees.edit', $employee) }}">Edit</a>
                    @endcan
                    <a class="kt-btn kt-btn-outline" href="{{ route('hrms-core.employees.index') }}">Back to Directory</a>
                </div>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-12">
            <div class="kt-card p-6 xl:col-span-8">
                <h2 class="text-lg font-semibold text-foreground">Profile Details</h2>
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <div class="rounded-lg bg-muted/30 p-4">
                        <p class="text-xs uppercase text-muted-foreground">Status</p>
                        <p class="mt-2">
                            <span class="kt-badge {{ $employee->is_active ? 'kt-badge-success' : 'kt-badge-warning' }}">
                                {{ $employee->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </p>
                    </div>
                    <div class="rounded-lg bg-muted/30 p-4">
                        <p class="text-xs uppercase text-muted-foreground">Grade</p>
                        <p class="mt-2 text-sm text-foreground">{{ $employee->grade?->name ?: 'Not mapped' }}</p>
                    </div>
                    <div class="rounded-lg bg-muted/30 p-4">
                        <p class="text-xs uppercase text-muted-foreground">Center</p>
                        <p class="mt-2 text-sm text-foreground">{{ $employee->center?->name ?: 'Not mapped' }}</p>
                    </div>
                    <div class="rounded-lg bg-muted/30 p-4">
                        <p class="text-xs uppercase text-muted-foreground">Mobile</p>
                        <p class="mt-2 text-sm text-foreground">{{ $employee->mobile ?: 'Not provided' }}</p>
                    </div>
                    <div class="rounded-lg bg-muted/30 p-4">
                        <p class="text-xs uppercase text-muted-foreground">Email</p>
                        <p class="mt-2 text-sm text-foreground">{{ $employee->email ?: 'Not provided' }}</p>
                    </div>
                    <div class="rounded-lg bg-muted/30 p-4">
                        <p class="text-xs uppercase text-muted-foreground">Created</p>
                        <p class="mt-2 text-sm text-foreground">{{ $employee->created_at?->format('M d, Y H:i') ?: '—' }}</p>
                    </div>
                </div>
            </div>

            <div class="kt-card p-6 xl:col-span-4">
                <h2 class="text-lg font-semibold text-foreground">Structure Links</h2>

                <div class="mt-4 space-y-4">
                    <div>
                        <p class="text-xs uppercase text-muted-foreground">Departments</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @forelse($employee->departments as $department)
                                <span class="kt-badge kt-badge-outline">{{ $department->name }}</span>
                            @empty
                                <span class="text-sm text-muted-foreground">No department mapping.</span>
                            @endforelse
                        </div>
                    </div>

                    <div>
                        <p class="text-xs uppercase text-muted-foreground">Directorates</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @forelse($employee->directorates as $directorate)
                                <span class="kt-badge kt-badge-outline">{{ $directorate->name }}</span>
                            @empty
                                <span class="text-sm text-muted-foreground">No directorate mapping.</span>
                            @endforelse
                        </div>
                    </div>

                    <div>
                        <p class="text-xs uppercase text-muted-foreground">Units</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @forelse($employee->units as $unit)
                                <span class="kt-badge kt-badge-outline">{{ $unit->name }}</span>
                            @empty
                                <span class="text-sm text-muted-foreground">No unit mapping.</span>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
