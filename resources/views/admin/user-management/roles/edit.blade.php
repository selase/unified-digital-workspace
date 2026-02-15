@extends('layouts.metronic.app')

@section('title', __('locale.labels.roles'))

@section('content')
    <section class="grid gap-6">
        <div class="rounded-xl border border-border bg-background p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Access Control</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">{{ __('locale.labels.update_role') }}</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Update role metadata and permission assignments.</p>
                </div>
                <a href="{{ route('roles.index') }}" class="kt-btn kt-btn-outline">Back to Roles</a>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-background p-6">
            <form id="kt_modal_update_role_form" class="kt-form" action="{{ route('roles.update', $role->id) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="kt-form-item">
                    <label class="kt-form-label">Role name <span class="text-destructive">*</span></label>
                    <div class="kt-form-control">
                        <input class="kt-input @error('name') is-invalid @enderror" placeholder="Enter a role name"
                            name="name" value="{{ old('name', isset($role->name) ? $role->name : null) }}" />
                    </div>
                    @error('name')
                        <p class="kt-form-message">{{ $message }}</p>
                    @enderror
                </div>

                <div class="kt-form-item mt-6">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <label class="kt-form-label">Role Permissions</label>
                        <label class="flex items-center gap-2 text-sm text-foreground">
                            <input class="kt-checkbox" type="checkbox" value="" id="kt_roles_select_all" />
                            <span>Select all</span>
                        </label>
                    </div>

                    <div class="kt-table-wrapper mt-4">
                        <table class="kt-table">
                            <tbody class="text-sm text-muted-foreground">
                                @foreach ($permissions as $key => $category)
                                    <tr>
                                        <td class="text-foreground font-medium text-uppercase">{{ ucwords($key) }}</td>
                                        <td>
                                            <div class="flex flex-wrap gap-3">
                                                @foreach ($category as $permission)
                                                    <label class="flex items-center gap-2 text-sm text-foreground">
                                                        <input class="kt-checkbox" type="checkbox" value="{{ $permission->id }}"
                                                            @foreach ($existing_permissions as $role_permission)
                                                                @if ($role_permission->id == $permission->id)
                                                                    checked
                                                                @endif
                                                            @endforeach
                                                            name="permissions[]" />
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
                    <button type="reset" class="kt-btn kt-btn-outline">Discard</button>
                    <button type="submit" id="updateRoleButton" class="kt-btn kt-btn-primary">
                        <span class="indicator-label">Submit</span>
                    </button>
                </div>
            </form>
        </div>
    </section>
@endsection

@push('custom-scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const allPermissions = document.getElementById('kt_roles_select_all');
            const checkboxes = document.querySelectorAll('input[type="checkbox"]');

            if (allPermissions) {
                allPermissions.addEventListener('change', function () {
                    checkboxes.forEach(function (checkbox) {
                        checkbox.checked = allPermissions.checked;
                    });
                });
            }
        });
    </script>
@endpush
