@extends('layouts.metronic.app')

@section('title', 'Create Role')

@section('content')
    <section class="grid gap-6">
        <div class="rounded-xl border border-border bg-background p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Access Control</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Create Global Role</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Define a role, assign permissions, and scope it to a tenant if needed.</p>
                </div>
                <a href="{{ route('roles.index') }}" class="kt-btn kt-btn-outline">Back to Roles</a>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-background p-6">
            <form id="createRoleForm" class="kt-form" action="{{ route('roles.store') }}" method="POST">
                @csrf

                <div class="grid gap-6 lg:grid-cols-2">
                    <div class="kt-form-item">
                        <label class="kt-form-label">Role Name <span class="text-destructive">*</span></label>
                        <div class="kt-form-control">
                            <input type="text" name="name" class="kt-input" placeholder="e.g. Audit Manager"
                                value="{{ old('name') }}" required />
                        </div>
                        <p class="kt-form-description">This name will be visible globally or within the selected tenant.</p>
                    </div>

                    <div class="kt-form-item">
                        <label class="kt-form-label">Assign to Tenant (Optional)</label>
                        <div class="kt-form-control">
                            <select name="tenant_id" class="kt-select" data-placeholder="Select a Tenant (Leave empty for Global)">
                                <option value=""></option>
                                @foreach($tenants as $tenant)
                                    <option value="{{ $tenant->id }}" {{ old('tenant_id') == $tenant->id ? 'selected' : '' }}>
                                        {{ $tenant->name }} ({{ $tenant->slug }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <p class="kt-form-description">If selected, this role will only be available to the specific organization.</p>
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
                                                            value="{{ $permission->name }}" id="perm_{{ $permission->id }}" />
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
                        <span class="indicator-label">Submit</span>
                    </button>
                </div>
            </form>
        </div>
    </section>
@endsection
