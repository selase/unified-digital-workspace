@extends('layouts.metronic.app')

@section('title', 'View Role Details')

@section('content')
    @php
        $permissionCategoryCount = $role->permissions->groupBy('category')->count();
        $permissionCount = $role->permissions->count();
    @endphp

    <section class="grid gap-6">
        <div class="rounded-xl border border-border bg-background p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Role Overview</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">{{ $role->name }}</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Review role scope, metadata, and assigned permissions.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    @if($role->tenant_id && !$role->isSystemRole())
                        <a href="{{ route('tenant.roles.edit', ['subdomain' => request()->route('subdomain'), 'role' => $role->id]) }}"
                            class="kt-btn kt-btn-primary">Edit Role</a>
                    @else
                        <span class="kt-badge kt-badge-outline kt-badge-warning">System Role</span>
                    @endif
                </div>
            </div>

            <div class="mt-5 grid gap-3 md:grid-cols-3">
                <div class="rounded-lg border border-border bg-muted/30 p-4">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Permission Categories</p>
                    <p class="mt-2 text-xl font-semibold text-foreground">{{ $permissionCategoryCount }}</p>
                </div>
                <div class="rounded-lg border border-border bg-muted/30 p-4">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Total Permissions</p>
                    <p class="mt-2 text-xl font-semibold text-foreground">{{ $permissionCount }}</p>
                </div>
                <div class="rounded-lg border border-border bg-muted/30 p-4">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Role Scope</p>
                    <p class="mt-2 text-xl font-semibold text-foreground">{{ $role->tenant_id ? 'Custom' : 'System' }}</p>
                </div>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="rounded-xl border border-border bg-background p-6">
                <h2 class="text-sm font-semibold uppercase text-foreground">Role Summary</h2>
                <div class="mt-4 space-y-3 text-sm text-muted-foreground">
                    <div class="flex items-center justify-between">
                        <span>Scope</span>
                        <span class="font-medium text-foreground">{{ $role->tenant_id ? 'Custom (Organization)' : 'System' }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span>Guard</span>
                        <span class="font-medium text-foreground">{{ $role->guard_name }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span>Created</span>
                        <span class="font-medium text-foreground">{{ $role->created_at->format('M d, Y') }}</span>
                    </div>
                </div>

                @if(! $role->tenant_id || $role->isSystemRole())
                    <div class="mt-6 rounded-lg border border-yellow-500 bg-yellow-50 p-4 text-sm text-yellow-600">
                        This role is protected and cannot be modified by tenant administrators.
                    </div>
                @endif
            </div>

            <div class="rounded-xl border border-border bg-background p-6 lg:col-span-2">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-sm font-semibold uppercase text-foreground">Role Permissions</h2>
                    <span class="text-xs text-muted-foreground">{{ $role->permissions->count() }} assigned</span>
                </div>
                <div class="kt-table-wrapper mt-4">
                    <table class="kt-table">
                        <thead>
                            <tr class="text-xs uppercase text-muted-foreground">
                                <th>Resource</th>
                                <th>Assigned Permissions</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm text-muted-foreground">
                            @foreach($role->permissions->groupBy('category') as $category => $perms)
                                <tr>
                                    <td class="text-foreground font-medium text-capitalize">{{ str_replace('-', ' ', $category) }}</td>
                                    <td>
                                        <div class="flex flex-wrap gap-2">
                                            @foreach($perms as $permission)
                                                <span class="kt-badge kt-badge-outline kt-badge-primary">{{ $permission->name }}</span>
                                            @endforeach
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                            @if($role->permissions->isEmpty())
                                <tr>
                                    <td colspan="2" class="py-8 text-center text-muted-foreground">
                                        No permissions assigned to this role.
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
@endsection
