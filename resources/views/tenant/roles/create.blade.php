@extends('layouts.metronic.app')

@section('title', 'Create Custom Role')

@section('content')
    <section class="grid gap-6">
        <div class="rounded-xl border border-border bg-background p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Organization Roles</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Create Custom Role</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Define a new role for your organization and select permissions.</p>
                </div>
                <a href="{{ route('tenant.roles.index', ['subdomain' => request()->route('subdomain')]) }}" class="kt-btn kt-btn-outline">Back to Roles</a>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-background p-6">
            <form class="kt-form" action="{{ route('tenant.roles.store', ['subdomain' => request()->route('subdomain')]) }}" method="POST">
                @csrf
                <div class="kt-form-item">
                    <label class="kt-form-label">Role Name <span class="text-destructive">*</span></label>
                    <div class="kt-form-control">
                        <input type="text" name="name" class="kt-input @error('name') !border-destructive @enderror" placeholder="e.g. Hospital Intern" value="{{ old('name') }}" required />
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
                                                        <input class="kt-checkbox permission-checkbox" type="checkbox" value="{{ $permission->name }}" name="permissions[]" />
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
                        <span class="indicator-label">Create Role</span>
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

        selectAll?.addEventListener('change', function (event) {
            permissionBoxes.forEach((checkbox) => {
                checkbox.checked = event.target.checked;
            });
        });

        permissionBoxes.forEach((checkbox) => {
            checkbox.addEventListener('change', () => {
                const checkedCount = [...permissionBoxes].filter((input) => input.checked).length;
                if (selectAll) {
                    selectAll.checked = checkedCount === permissionBoxes.length;
                }
            });
        });
    </script>
@endpush
