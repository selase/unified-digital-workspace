@extends('layouts.metronic.app')

@section('title', 'Employee Profile - ' . $employee->fullName())

@section('content')
<div class="flex flex-col gap-6">
    <x-metronic.page-header
        title="{{ $employee->fullName() }}"
        description="Staff record {{ $employee->employee_staff_id ?: 'unassigned' }} &bull; {{ $employee->grade?->name ?: 'General Staff' }}"
    >
        <x-slot:actions>
            <a href="{{ route('hrms-core.employees.index') }}" class="kt-btn kt-btn-outline">
                Back to Directory
            </a>
            @can('hrms.leaves.create')
                <a href="{{ route('hrms-core.leave-requests.create') }}" class="kt-btn kt-btn-outline flex items-center gap-1.5">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    Apply Leave
                </a>
            @endcan
            @can('hrms.employees.update')
                <a href="{{ route('hrms-core.employees.edit', $employee) }}" class="kt-btn kt-btn-primary flex items-center gap-1.5">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    Edit Profile
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

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        {{-- Profile Main Details (8 cols) --}}
        <div class="lg:col-span-8 flex flex-col gap-6">
            <x-metronic.card title="Personal & Professional Information" subtitle="Official identity and contact records">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="rounded-xl border border-border bg-muted/15 p-4 flex flex-col">
                        <span class="text-xs uppercase tracking-wider font-semibold text-muted-foreground">Full Name</span>
                        <span class="mt-1 font-semibold text-foreground text-base">{{ $employee->fullName() }}</span>
                        @if($employee->title)
                            <span class="text-xs text-muted-foreground">{{ $employee->title }}</span>
                        @endif
                    </div>

                    <div class="rounded-xl border border-border bg-muted/15 p-4 flex flex-col">
                        <span class="text-xs uppercase tracking-wider font-semibold text-muted-foreground">Staff ID Number</span>
                        <div class="mt-1 flex items-center gap-2">
                            @if($employee->employee_staff_id)
                                <span class="font-mono text-sm px-2.5 py-1 rounded-md bg-muted text-foreground border border-border font-semibold">
                                    {{ $employee->employee_staff_id }}
                                </span>
                            @else
                                <span class="text-sm text-muted-foreground italic">Not Assigned</span>
                            @endif
                        </div>
                    </div>

                    <div class="rounded-xl border border-border bg-muted/15 p-4 flex flex-col">
                        <span class="text-xs uppercase tracking-wider font-semibold text-muted-foreground">Email Address</span>
                        <span class="mt-1 text-sm text-foreground font-medium">
                            {{ $employee->email ?: 'Not provided' }}
                        </span>
                    </div>

                    <div class="rounded-xl border border-border bg-muted/15 p-4 flex flex-col">
                        <span class="text-xs uppercase tracking-wider font-semibold text-muted-foreground">Mobile Contact</span>
                        <span class="mt-1 text-sm text-foreground font-medium">
                            {{ $employee->mobile ?: 'Not provided' }}
                        </span>
                    </div>

                    <div class="rounded-xl border border-border bg-muted/15 p-4 flex flex-col">
                        <span class="text-xs uppercase tracking-wider font-semibold text-muted-foreground">Job Grade</span>
                        <span class="mt-1 text-sm font-semibold text-foreground">
                            {{ $employee->grade?->name ?: 'Not Mapped' }}
                        </span>
                    </div>

                    <div class="rounded-xl border border-border bg-muted/15 p-4 flex flex-col">
                        <span class="text-xs uppercase tracking-wider font-semibold text-muted-foreground">Center Location</span>
                        <span class="mt-1 text-sm font-semibold text-foreground">
                            {{ $employee->center?->name ?: 'Main Campus' }}
                        </span>
                    </div>
                </div>
            </x-metronic.card>

            <x-metronic.card title="Organizational Hierarchy & Structure" subtitle="Reporting units, directorates, and departments">
                <div class="space-y-5">
                    <div>
                        <span class="text-xs uppercase tracking-wider font-semibold text-muted-foreground block mb-2">Departments</span>
                        <div class="flex flex-wrap gap-2">
                            @forelse($employee->departments as $department)
                                <span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium bg-primary/10 text-primary border border-primary/20">
                                    <svg class="h-3.5 w-3.5 mr-1.5 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                    </svg>
                                    {{ $department->name }}
                                </span>
                            @empty
                                <span class="text-sm text-muted-foreground italic">No primary department assigned.</span>
                            @endforelse
                        </div>
                    </div>

                    <div class="pt-4 border-t border-border">
                        <span class="text-xs uppercase tracking-wider font-semibold text-muted-foreground block mb-2">Directorates</span>
                        <div class="flex flex-wrap gap-2">
                            @forelse($employee->directorates as $directorate)
                                <span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium bg-secondary text-secondary-foreground border border-border">
                                    {{ $directorate->name }}
                                </span>
                            @empty
                                <span class="text-sm text-muted-foreground italic">No directorate hierarchy mapped.</span>
                            @endforelse
                        </div>
                    </div>

                    <div class="pt-4 border-t border-border">
                        <span class="text-xs uppercase tracking-wider font-semibold text-muted-foreground block mb-2">Operational Units</span>
                        <div class="flex flex-wrap gap-2">
                            @forelse($employee->units as $unit)
                                <span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium bg-muted text-foreground border border-border">
                                    {{ $unit->name }}
                                </span>
                            @empty
                                <span class="text-sm text-muted-foreground italic">No operational unit assigned.</span>
                            @endforelse
                        </div>
                    </div>
                </div>
            </x-metronic.card>
        </div>

        {{-- Side Cards (4 cols) --}}
        <div class="lg:col-span-4 flex flex-col gap-6">
            <x-metronic.card title="Status & Record" subtitle="Personnel system lifecycle">
                <div class="space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-border">
                        <span class="text-sm text-muted-foreground">Employment Status</span>
                        <x-metronic.badge :variant="$employee->is_active ? 'success' : 'secondary'">
                            {{ $employee->is_active ? 'Active' : 'Inactive' }}
                        </x-metronic.badge>
                    </div>

                    <div class="flex items-center justify-between pb-3 border-b border-border">
                        <span class="text-sm text-muted-foreground">Portal Account</span>
                        @if($employee->user_id)
                            <x-metronic.badge variant="primary" size="sm">
                                User #{{ $employee->user_id }}
                            </x-metronic.badge>
                        @else
                            <span class="text-xs text-muted-foreground italic">Unlinked</span>
                        @endif
                    </div>

                    <div class="flex items-center justify-between pb-3 border-b border-border">
                        <span class="text-sm text-muted-foreground">Enrolled Date</span>
                        <span class="text-xs font-medium text-foreground">
                            {{ $employee->created_at?->format('d/m/Y') ?: '—' }}
                        </span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-sm text-muted-foreground">Last Updated</span>
                        <span class="text-xs font-medium text-foreground">
                            {{ $employee->updated_at?->diffForHumans() ?: '—' }}
                        </span>
                    </div>
                </div>
            </x-metronic.card>

            <x-metronic.card title="Quick Actions">
                <div class="flex flex-col gap-2.5">
                    @can('hrms.leaves.create')
                        <a href="{{ route('hrms-core.leave-requests.create') }}" class="kt-btn kt-btn-outline w-full justify-start text-xs gap-2">
                            <svg class="h-4 w-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            Submit Leave on Behalf
                        </a>
                    @endcan
                    @can('hrms.employees.update')
                        <a href="{{ route('hrms-core.employees.edit', $employee) }}" class="kt-btn kt-btn-outline w-full justify-start text-xs gap-2">
                            <svg class="h-4 w-4 text-muted-foreground" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                            </svg>
                            Modify Employee Details
                        </a>
                    @endcan
                    <a href="{{ route('hrms-core.leave-requests.index') }}" class="kt-btn kt-btn-outline w-full justify-start text-xs gap-2">
                        <svg class="h-4 w-4 text-muted-foreground" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                        View All Leave Records
                    </a>
                </div>
            </x-metronic.card>
        </div>
    </div>
</div>
@endsection
