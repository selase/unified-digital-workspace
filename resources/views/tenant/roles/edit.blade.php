@extends('layouts.metronic.app')

@section('title', 'Edit Custom Role')

@section('content')
    <section class="grid gap-6">
        <div class="rounded-xl border border-border bg-background p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Organization Roles</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Edit Custom Role</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Update role details for {{ $role->name }}.</p>
                </div>
                <a href="{{ route('tenant.roles.index', ['subdomain' => request()->route('subdomain')]) }}" class="kt-btn kt-btn-outline">Back to Roles</a>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-background p-6">
            <form class="kt-form" action="{{ route('tenant.roles.update', ['subdomain' => request()->route('subdomain'), 'role' => $role->id]) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="kt-form-item">
                    <label class="kt-form-label">Role Name <span class="text-destructive">*</span></label>
                    <div class="kt-form-control">
                        <input type="text" name="name" class="kt-input @error('name') !border-destructive @enderror" value="{{ old('name', $role->name) }}" required />
                        @error('name')
                            <p class="mt-2 text-xs text-destructive">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="kt-form-item mt-6">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <label class="kt-form-label">Role Permissions <span class="text-destructive">*</span></label>
                        <label class="flex items-center gap-2 text-sm text-foreground">
                            <input class="kt-checkbox" type="checkbox" value="" id="kt_roles_select_all" />
                            <span>Select all</span>
                        </label>
                    </div>

                    <div class="kt-table-wrapper mt-4">
                        <table class="kt-table">
                            <thead>
                                <tr class="text-xs uppercase text-muted-foreground">
                                    <th>Category</th>
                                    <th>Permissions</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm text-muted-foreground">
                                @foreach($permissions->groupBy('category') as $category => $perms)
                                    <tr>
                                        <td class="text-foreground font-medium text-capitalize">{{ str_replace('-', ' ', $category) }}</td>
                                        <td>
                                            <div class="flex flex-wrap gap-3">
                                                @foreach($perms as $permission)
                                                    <label class="flex items-center gap-2 text-sm text-foreground">
                                                        <input class="kt-checkbox permission-checkbox" type="checkbox" value="{{ $permission->name }}" name="permissions[]"
                                                            {{ $role->hasPermissionTo($permission->name) ? 'checked' : '' }} />
                                                        <span>{{ explode(' ', $permission->name)[0] }}</span>
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
                    <a href="{{ route('tenant.roles.index', ['subdomain' => request()->route('subdomain')]) }}" class="kt-btn kt-btn-outline">Discard</a>
                    <button type="submit" class="kt-btn kt-btn-primary">
                        <span class="indicator-label">Update Role</span>
                    </button>
                </div>
            </form>
        </div>
    </section>
@endsection

@push('custom-scripts')
    <script>
        const selectAll = document.getElementById('kt_roles_select_all');
        const permissionBoxes = document.querySelectorAll('.permission-checkbox');

        const syncSelectAllState = () => {
            if (!selectAll || permissionBoxes.length === 0) {
                return;
            }

            const checkedCount = [...permissionBoxes].filter((input) => input.checked).length;
            selectAll.checked = checkedCount === permissionBoxes.length;
        };

        selectAll?.addEventListener('change', function (event) {
            permissionBoxes.forEach((checkbox) => {
                checkbox.checked = event.target.checked;
            });
        });

        permissionBoxes.forEach((checkbox) => {
            checkbox.addEventListener('change', syncSelectAllState);
        });

        syncSelectAllState();
    </script>
@endpush
