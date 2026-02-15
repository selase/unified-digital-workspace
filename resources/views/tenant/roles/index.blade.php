@extends('layouts.metronic.app')

@section('title', 'Roles & Permissions')

@section('content')
    @php
        $customRoleCount = $roles->whereNotNull('tenant_id')->count();
        $systemRoleCount = $roles->count() - $customRoleCount;
    @endphp

    <section class="grid gap-6">
        <div class="rounded-xl border border-border bg-background p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Organization Access</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Organization Roles</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Define permissions for teams and departments.</p>
                </div>
                <a href="{{ route('tenant.roles.create', ['subdomain' => request()->route('subdomain')]) }}"
                    class="kt-btn kt-btn-primary">
                    <i class="ki-filled ki-plus text-base"></i>
                    Add Custom Role
                </a>
            </div>

            <div class="mt-5 grid gap-3 md:grid-cols-3">
                <div class="rounded-lg border border-border bg-muted/30 p-4">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Total Roles</p>
                    <p class="mt-2 text-xl font-semibold text-foreground">{{ $roles->count() }}</p>
                </div>
                <div class="rounded-lg border border-border bg-muted/30 p-4">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Custom Roles</p>
                    <p class="mt-2 text-xl font-semibold text-foreground">{{ $customRoleCount }}</p>
                </div>
                <div class="rounded-lg border border-border bg-muted/30 p-4">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">System Roles</p>
                    <p class="mt-2 text-xl font-semibold text-foreground">{{ $systemRoleCount }}</p>
                </div>
            </div>
        </div>

        <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
            @foreach($roles as $role)
                <article class="rounded-xl border border-border bg-background p-6 flex flex-col gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-foreground">{{ $role->name }}</h2>
                        <p class="text-sm text-muted-foreground mt-1">Total users with this role: {{ $role->users_count ?? 0 }}</p>
                    </div>
                    <div>
                        <div class="text-xs uppercase text-muted-foreground">Permissions</div>
                        <ul class="mt-3 space-y-2 text-sm text-muted-foreground">
                            @foreach($role->permissions->take(5) as $permission)
                                <li class="flex items-center gap-2">
                                    <span class="size-2 rounded-full bg-primary"></span>
                                    <span>{{ $permission->name }}</span>
                                </li>
                            @endforeach
                            @if($role->permissions->count() > 5)
                                <li class="flex items-center gap-2">
                                    <span class="size-2 rounded-full bg-primary"></span>
                                    <em>and {{ $role->permissions->count() - 5 }} more...</em>
                                </li>
                            @endif
                            @if($role->permissions->isEmpty())
                                <li class="text-sm text-muted-foreground">No specific permissions assigned.</li>
                            @endif
                        </ul>
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-3 pt-2 mt-auto border-t border-border">
                        @if(!$role->tenant_id)
                            <span class="kt-badge kt-badge-outline kt-badge-success">System Role</span>
                        @else
                            <span class="kt-badge kt-badge-outline kt-badge-primary">Custom Role</span>
                        @endif

                        <div class="flex flex-wrap gap-2">
                            <a href="{{ route('tenant.roles.show', ['subdomain' => request()->route('subdomain'), 'role' => $role->id]) }}"
                                class="kt-btn kt-btn-sm kt-btn-outline">View</a>

                            @if($role->tenant_id && !$role->isSystemRole())
                                <a href="{{ route('tenant.roles.edit', ['subdomain' => request()->route('subdomain'), 'role' => $role->id]) }}"
                                    class="kt-btn kt-btn-sm kt-btn-outline">Edit</a>
                                <form
                                    action="{{ route('tenant.roles.destroy', ['subdomain' => request()->route('subdomain'), 'role' => $role->id]) }}"
                                    method="POST" class="delete-role-form">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="kt-btn kt-btn-sm kt-btn-outline kt-btn-danger">Delete</button>
                                </form>
                            @endif
                        </div>
                    </div>
                </article>
            @endforeach

            <article class="rounded-xl border border-dashed border-border bg-muted/30 p-6 flex flex-col items-center justify-center text-center">
                <img src="{{ asset('assets/media/illustrations/sketchy-1/4.png') }}" alt=""
                    class="w-28 mb-4" />
                <p class="text-sm text-muted-foreground">Need another role?</p>
                <a href="{{ route('tenant.roles.create', ['subdomain' => request()->route('subdomain')]) }}"
                    class="mt-3 kt-btn kt-btn-outline">
                    Add New Role
                </a>
            </article>
        </div>
    </section>
@endsection

@push('custom-scripts')
    <script>
        const runConfirmDialog = (config, onConfirm) => {
            if (typeof Swal !== 'undefined') {
                Swal.fire(config).then(function (result) {
                    if (result.isConfirmed) {
                        onConfirm();
                    }
                });

                return;
            }

            if (window.confirm(config.text ?? 'Please confirm this action.')) {
                onConfirm();
            }
        };

        document.querySelectorAll('.delete-role-form').forEach((form) => {
            form.addEventListener('submit', (event) => {
                event.preventDefault();
                runConfirmDialog({
                    text: 'Delete this role? Users assigned to it may lose access.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Delete',
                    cancelButtonText: 'Cancel',
                    buttonsStyling: false,
                    customClass: {
                        confirmButton: 'kt-btn kt-btn-danger',
                        cancelButton: 'kt-btn kt-btn-outline',
                    },
                }, () => form.submit());
            });
        });
    </script>
@endpush
