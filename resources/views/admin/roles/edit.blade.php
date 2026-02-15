@extends('layouts.metronic.app')

@section('title', 'Edit Role')

@section('content')
    <section class="grid gap-6">
        <div class="rounded-xl border border-border bg-background p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Access Control</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Edit Global Role</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Update the role name and permission set for {{ $role->name }}.</p>
                </div>
                <a href="{{ route('roles.index') }}" class="kt-btn kt-btn-outline">Back to Roles</a>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-background p-6">
            <form id="editRoleForm" class="kt-form" action="{{ route('roles.update', $role->id) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="grid gap-6 lg:grid-cols-2">
                    <div class="kt-form-item">
                        <label class="kt-form-label">Role Name <span class="text-destructive">*</span></label>
                        <div class="kt-form-control">
                            <input type="text" name="name" class="kt-input" placeholder="e.g. Audit Manager"
                                value="{{ old('name', $role->name) }}" required />
                        </div>
                        <p class="kt-form-description">This name will be visible globally or within the selected tenant.</p>
                    </div>

                    <div class="kt-form-item">
                        <label class="kt-form-label">Tenant Scope</label>
                        <div class="kt-form-control">
                            <select name="tenant_id" class="kt-select" disabled>
                                <option value="">Global</option>
                                @foreach($tenants as $tenant)
                                    <option value="{{ $tenant->id }}" {{ $role->tenant_id == $tenant->id ? 'selected' : '' }}>
                                        {{ $tenant->name }} ({{ $tenant->slug }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <p class="kt-form-description">Tenant scope cannot be changed after creation.</p>
                    </div>
                </div>

                <div class="kt-form-item mt-6">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <label class="kt-form-label">Permissions <span class="text-destructive">*</span></label>
                        <span class="text-xs text-muted-foreground">{{ $permissions->count() }} permissions available</span>
                    </div>

                    <div class="kt-table-wrapper mt-4">
                        <table class="kt-table">
                            <thead>
                                <tr class="text-xs uppercase text-muted-foreground">
                                    <th>Resource</th>
                                    <th>Permissions</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm text-muted-foreground">
                                @php
                                    $groupedPermissions = $permissions->groupBy(function ($perm) {
                                        $parts = explode(' ', $perm->name);
                                        return count($parts) > 1 ? end($parts) : 'Other';
                                    });
                                @endphp

                                @foreach($groupedPermissions as $group => $perms)
                                    <tr>
                                        <td class="text-foreground font-medium">{{ ucfirst($group) }}</td>
                                        <td>
                                            <div class="flex flex-wrap gap-3">
                                                @foreach($perms as $permission)
                                                    <label class="flex items-center gap-2 text-sm text-foreground">
                                                        <input class="kt-checkbox" type="checkbox" name="permissions[]"
                                                            value="{{ $permission->name }}" id="perm_{{ $permission->id }}"
                                                            {{ $role->hasPermissionTo($permission->name) ? 'checked' : '' }} />
                                                        <span>{{ $permission->name }}</span>
                                                    </label>
                                                @endforeach
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="kt-form-actions mt-8 flex items-center justify-end gap-3">
                    <a href="{{ route('roles.index') }}" class="kt-btn kt-btn-outline">Discard</a>
                    <button type="submit" class="kt-btn kt-btn-primary">
                        <span class="indicator-label">Update Role</span>
                    </button>
                </div>
            </form>
        </div>
    </section>
@endsection
