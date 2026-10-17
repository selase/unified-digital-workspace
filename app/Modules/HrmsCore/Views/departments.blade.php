@extends('layouts.metronic.app')

@section('title', 'HRMS Departments & Organizational Hierarchy')

@section('content')
@php
    $canManageOrg = auth()->user()?->can('hrms.departments.manage')
        || auth()->user()?->can('hrms.departments.create')
        || auth()->user()?->can('hrms.departments.update')
        || auth()->user()?->can('core.roles.update');
    $directoratesList = $directorates ?? collect();
    $unitsList = $units ?? collect();
    $employeesList = $employees ?? collect();
    $rolesList = $availableRoles ?? collect();
    $permissionsList = $allowedPermissions ?? [];
@endphp

<div class="flex flex-col gap-6">
    <x-metronic.page-header
        title="Department Directory"
        description="Institutional hierarchy (Directorates → Departments → Units), Heads & Deputies, and centralized organizational role/permission inheritance."
    >
        <x-slot:actions>
            <a href="{{ route('hrms-core.index') }}" class="kt-btn kt-btn-outline">
                Back to Hub
            </a>
            <a href="{{ route('hrms-core.employees.index') }}" class="kt-btn kt-btn-outline">
                Staff Directory
            </a>
        </x-slot:actions>
    </x-metronic.page-header>

    {{-- Hierarchy Summary Ribbon --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="rounded-xl border border-border bg-card p-5 shadow-xs">
            <div class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Tier 1 • Directorates</div>
            <div class="mt-2 text-2xl font-bold text-foreground">{{ $directoratesList->count() }}</div>
            <div class="mt-1 text-xs text-muted-foreground">Executive divisions with Heads &amp; Deputies</div>
        </div>
        <div class="rounded-xl border border-border bg-card p-5 shadow-xs">
            <div class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Tier 2 • Departments</div>
            <div class="mt-2 text-2xl font-bold text-foreground">{{ $departments->total() }}</div>
            <div class="mt-1 text-xs text-muted-foreground">Operational &amp; clinical departments</div>
        </div>
        <div class="rounded-xl border border-border bg-card p-5 shadow-xs">
            <div class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Tier 3 • Specialist Units</div>
            <div class="mt-2 text-2xl font-bold text-foreground">{{ $unitsList->count() }}</div>
            <div class="mt-1 text-xs text-muted-foreground">Frontline teams &amp; functional units</div>
        </div>
        <div class="rounded-xl border border-border bg-card p-5 shadow-xs">
            <div class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Centralized RBAC Grants</div>
            <div class="mt-2 text-2xl font-bold text-primary">
                {{ $directoratesList->sum(fn ($d) => $d->roleAssignments->count() + $d->permissionAssignments->count()) + $departments->getCollection()->sum(fn ($d) => $d->roleAssignments->count() + $d->permissionAssignments->count()) + $unitsList->sum(fn ($u) => $u->roleAssignments->count() + $u->permissionAssignments->count()) }}
            </div>
            <div class="mt-1 text-xs text-muted-foreground">Inherited roles &amp; permissions across tiers</div>
        </div>
    </div>

    {{-- Directorates Overview --}}
    @if($directoratesList->isNotEmpty())
        <x-metronic.card title="Executive Directorates ({{ $directoratesList->count() }})" subtitle="Top-level institutional directorates, leadership chain, and inherited authorizations">
            <div class="kt-scrollable-x-auto">
                <table class="kt-table table-auto kt-table-border">
                    <thead>
                        <tr class="text-xs uppercase text-muted-foreground border-b border-border bg-muted/20">
                            <th class="py-3 px-4 font-semibold text-left">Directorate</th>
                            <th class="py-3 px-4 font-semibold text-left">Head &amp; Deputy</th>
                            <th class="py-3 px-4 font-semibold text-left">Scope</th>
                            <th class="py-3 px-4 font-semibold text-left">Inherited Roles &amp; Permissions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border text-sm">
                        @foreach($directoratesList as $directorate)
                            <tr class="hover:bg-muted/10 transition-colors">
                                <td class="py-3.5 px-4">
                                    <div class="font-semibold text-foreground">{{ $directorate->name }}</div>
                                    <div class="text-xs font-mono text-muted-foreground">{{ $directorate->slug }}</div>
                                </td>
                                <td class="py-3.5 px-4 text-xs">
                                    <div><span class="text-muted-foreground">Head:</span> <span class="font-semibold text-foreground">{{ $directorate->head?->displayName() ?? 'Unassigned' }}</span></div>
                                    <div class="mt-0.5"><span class="text-muted-foreground">Deputy:</span> <span class="font-medium text-foreground">{{ $directorate->deputy?->displayName() ?? 'Unassigned' }}</span></div>
                                </td>
                                <td class="py-3.5 px-4 text-xs text-muted-foreground">
                                    {{ $directorate->departments_count }} {{ Str::plural('dept', $directorate->departments_count) }} &bull;
                                    {{ $directorate->units_count }} {{ Str::plural('unit', $directorate->units_count) }} &bull;
                                    {{ $directorate->employees_count }} staff
                                </td>
                                <td class="py-3.5 px-4">
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach($directorate->roleAssignments as $ra)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-primary/10 text-primary border border-primary/20">
                                                Role: {{ $ra->role_name }}
                                            </span>
                                        @endforeach
                                        @foreach($directorate->permissionAssignments as $pa)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-mono bg-muted text-foreground border border-border">
                                                {{ $pa->permission_name }}
                                            </span>
                                        @endforeach
                                        @if($directorate->roleAssignments->isEmpty() && $directorate->permissionAssignments->isEmpty())
                                            <span class="text-xs text-muted-foreground">Baseline only</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-metronic.card>
    @endif

    {{-- Department Registry --}}
    <x-metronic.card title="Department Registry ({{ $departments->total() }})" subtitle="Institutional departments, parent directorates, leadership, and inherited access">
        <div class="mb-5 pb-4 border-b border-border">
            <form method="GET" action="{{ route('hrms-core.departments.index') }}" class="flex flex-wrap items-center gap-3">
                <div class="flex-1 min-w-[240px]">
                    <input
                        type="text"
                        name="search"
                        value="{{ $search }}"
                        placeholder="Search department name…"
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
                    <a href="{{ route('hrms-core.departments.index') }}" class="kt-btn kt-btn-ghost text-xs text-muted-foreground">Reset</a>
                @endif
            </form>
        </div>

        <div class="kt-scrollable-x-auto">
            <table class="kt-table table-auto kt-table-border">
                <thead>
                    <tr class="text-xs uppercase text-muted-foreground border-b border-border bg-muted/20">
                        <th class="py-3 px-4 font-semibold text-left">Department Name</th>
                        <th class="py-3 px-4 font-semibold text-left">Directorate</th>
                        <th class="py-3 px-4 font-semibold text-left">Head &amp; Deputy</th>
                        <th class="py-3 px-4 font-semibold text-left">Units &amp; Types</th>
                        <th class="py-3 px-4 font-semibold text-left">Inherited Roles / Permissions</th>
                        <th class="py-3 px-4 font-semibold text-left">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border text-sm">
                    @forelse($departments as $department)
                        <tr class="hover:bg-muted/10 transition-colors">
                            <td class="py-3.5 px-4 font-semibold text-foreground">
                                <div class="flex items-center gap-2.5">
                                    <div class="h-8 w-8 rounded-lg bg-primary/10 text-primary font-bold flex items-center justify-center text-xs shrink-0 border border-primary/20">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                        </svg>
                                    </div>
                                    <div>
                                        <div>{{ $department->name }}</div>
                                        <div class="font-mono text-xs text-muted-foreground">{{ $department->slug }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3.5 px-4 text-xs">
                                @if($department->directorate)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded bg-muted text-foreground border border-border font-medium">
                                        {{ $department->directorate->name }}
                                    </span>
                                @else
                                    <span class="text-muted-foreground">Standalone</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-xs">
                                <div><span class="text-muted-foreground">Head:</span> <span class="font-semibold text-foreground">{{ $department->head?->displayName() ?? 'Unassigned' }}</span></div>
                                <div class="mt-0.5"><span class="text-muted-foreground">Deputy:</span> <span class="font-medium text-foreground">{{ $department->deputy?->displayName() ?? 'Unassigned' }}</span></div>
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-muted text-foreground border border-border">
                                    {{ $department->units_count ?? 0 }} {{ Str::plural('unit', $department->units_count ?? 0) }} &bull;
                                    {{ $department->department_types_count }} {{ Str::plural('type', $department->department_types_count) }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4">
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach($department->roleAssignments ?? [] as $ra)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-primary/10 text-primary border border-primary/20">
                                            Role: {{ $ra->role_name }}
                                        </span>
                                    @endforeach
                                    @foreach($department->permissionAssignments ?? [] as $pa)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-mono bg-muted text-foreground border border-border">
                                            {{ $pa->permission_name }}
                                        </span>
                                    @endforeach
                                    @if(($department->roleAssignments?->isEmpty() ?? true) && ($department->permissionAssignments?->isEmpty() ?? true))
                                        <span class="text-xs text-muted-foreground">—</span>
                                    @endif
                                </div>
                            </td>
                            <td class="py-3.5 px-4">
                                <x-metronic.badge :variant="$department->is_active ? 'success' : 'secondary'" size="sm">
                                    {{ $department->is_active ? 'Active' : 'Inactive' }}
                                </x-metronic.badge>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-12 text-center text-muted-foreground">
                                <p class="text-sm">No departments found matching your criteria.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4 pt-4 border-t border-border">
            {{ $departments->links() }}
        </div>
    </x-metronic.card>

    {{-- Specialist Units Overview --}}
    @if($unitsList->isNotEmpty())
        <x-metronic.card title="Operational & Specialist Units ({{ $unitsList->count() }})" subtitle="Sub-department units with dedicated Unit Heads, Deputies, and role/permission grants">
            <div class="kt-scrollable-x-auto">
                <table class="kt-table table-auto kt-table-border">
                    <thead>
                        <tr class="text-xs uppercase text-muted-foreground border-b border-border bg-muted/20">
                            <th class="py-3 px-4 font-semibold text-left">Unit Name</th>
                            <th class="py-3 px-4 font-semibold text-left">Parent Department / Directorate</th>
                            <th class="py-3 px-4 font-semibold text-left">Unit Head &amp; Deputy</th>
                            <th class="py-3 px-4 font-semibold text-left">Inherited Roles &amp; Permissions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border text-sm">
                        @foreach($unitsList as $unit)
                            <tr class="hover:bg-muted/10 transition-colors">
                                <td class="py-3.5 px-4">
                                    <div class="font-semibold text-foreground">{{ $unit->name }}</div>
                                    <div class="text-xs font-mono text-muted-foreground">{{ $unit->slug }}</div>
                                </td>
                                <td class="py-3.5 px-4 text-xs">
                                    @if($unit->department)
                                        <span class="font-medium text-foreground">{{ $unit->department->name }}</span>
                                    @endif
                                    @if($unit->directorate)
                                        <span class="text-muted-foreground">({{ $unit->directorate->name }})</span>
                                    @endif
                                    @if(! $unit->department && ! $unit->directorate)
                                        <span class="text-muted-foreground">Standalone Unit</span>
                                    @endif
                                </td>
                                <td class="py-3.5 px-4 text-xs">
                                    <div><span class="text-muted-foreground">Head:</span> <span class="font-semibold text-foreground">{{ $unit->head?->displayName() ?? 'Unassigned' }}</span></div>
                                    <div class="mt-0.5"><span class="text-muted-foreground">Deputy:</span> <span class="font-medium text-foreground">{{ $unit->deputy?->displayName() ?? 'Unassigned' }}</span></div>
                                </td>
                                <td class="py-3.5 px-4">
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach($unit->roleAssignments as $ra)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-primary/10 text-primary border border-primary/20">
                                                Role: {{ $ra->role_name }}
                                            </span>
                                        @endforeach
                                        @foreach($unit->permissionAssignments as $pa)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-mono bg-muted text-foreground border border-border">
                                                {{ $pa->permission_name }}
                                            </span>
                                        @endforeach
                                        @if($unit->roleAssignments->isEmpty() && $unit->permissionAssignments->isEmpty())
                                            <span class="text-xs text-muted-foreground">—</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-metronic.card>
    @endif

    {{-- Hierarchy & Centralized RBAC Administration Panel --}}
    @if($canManageOrg)
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <x-metronic.card title="Create Organizational Node" subtitle="Add a Directorate, Department, or Unit and assign its Head & Deputy">
                <form method="POST" action="{{ route('hrms-core.departments.store') }}" class="flex flex-col gap-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold uppercase text-muted-foreground mb-1">Department Name</label>
                        <input type="text" name="name" required class="kt-input w-full" placeholder="e.g., Clinical Diagnostics Department" />
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-semibold uppercase text-muted-foreground mb-1">Parent Directorate (Optional)</label>
                            <select name="directorate_id" class="kt-select w-full">
                                <option value="">Standalone / None</option>
                                @foreach($directoratesList as $dir)
                                    <option value="{{ $dir->id }}">{{ $dir->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase text-muted-foreground mb-1">Head of Department</label>
                            <select name="head_employee_id" class="kt-select w-full">
                                <option value="">Select Head…</option>
                                @foreach($employeesList as $emp)
                                    <option value="{{ $emp->id }}">{{ $emp->displayName() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase text-muted-foreground mb-1">Deputy Head</label>
                            <select name="deputy_employee_id" class="kt-select w-full">
                                <option value="">Select Deputy…</option>
                                @foreach($employeesList as $emp)
                                    <option value="{{ $emp->id }}">{{ $emp->displayName() }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="kt-btn kt-btn-primary">Create Department</button>
                    </div>
                </form>
            </x-metronic.card>

            <x-metronic.card title="Centralized Organizational RBAC" subtitle="Assign Roles or Module Permissions to a Directorate, Department, or Unit">
                <form method="POST" action="{{ route('hrms-core.departments.rbac.assign') }}" class="flex flex-col gap-4">
                    @csrf
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold uppercase text-muted-foreground mb-1">Target Tier</label>
                            <select name="org_type" class="kt-select w-full" required>
                                <option value="department">Department</option>
                                <option value="directorate">Directorate</option>
                                <option value="unit">Unit</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase text-muted-foreground mb-1">Target Organization ID</label>
                            <select name="org_id" class="kt-select w-full" required>
                                @foreach($departments as $dept)
                                    <option value="{{ $dept->id }}">Dept: {{ $dept->name }} (#{{ $dept->id }})</option>
                                @endforeach
                                @foreach($directoratesList as $dir)
                                    <option value="{{ $dir->id }}">Dir: {{ $dir->name }} (#{{ $dir->id }})</option>
                                @endforeach
                                @foreach($unitsList as $u)
                                    <option value="{{ $u->id }}">Unit: {{ $u->name }} (#{{ $u->id }})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold uppercase text-muted-foreground mb-1">Grant Role (Optional)</label>
                            <select name="role_name" class="kt-select w-full">
                                <option value="">— No Role —</option>
                                @foreach($rolesList as $role)
                                    <option value="{{ $role->name }}">{{ $role->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase text-muted-foreground mb-1">Grant Direct Permission (Optional)</label>
                            <select name="permission_name" class="kt-select w-full">
                                <option value="">— No Direct Permission —</option>
                                @foreach($permissionsList as $perm)
                                    <option value="{{ $perm }}">{{ $perm }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="kt-btn kt-btn-primary">Grant Organizational Access</button>
                    </div>
                </form>
            </x-metronic.card>
        </div>
    @endif
</div>
@endsection
