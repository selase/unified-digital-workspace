@extends('layouts.metronic.app')

@section('title', __('locale.labels.users_list'))

@section('content')
    <section class="grid gap-6">
        <div class="rounded-xl border border-border bg-background p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">User Management</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">{{ __('locale.labels.users_list') }}</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Manage access, roles, and onboarding activity.</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <div class="flex flex-wrap gap-2" data-kt-user-table-toolbar="base">
                        @can('create user')
                            <button type="button" class="kt-btn kt-btn-primary" onclick="createUser()">
                                <i class="ki-filled ki-plus text-base"></i>
                                {{ __('locale.labels.add_user') }}
                            </button>
                        @endcan
                    </div>
                    <div class="hidden items-center gap-3" data-kt-user-table-toolbar="selected">
                        <div class="text-sm text-muted-foreground">
                            <span class="font-semibold text-foreground" data-kt-user-table-select="selected_count"></span>
                            Selected
                        </div>
                        <button type="button" class="kt-btn kt-btn-danger" data-kt-user-table-select="delete_selected">
                            Delete Selected
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="kt-card kt-card-grid min-w-full">
            <div class="kt-card-header flex-wrap gap-2">
                <h3 class="kt-card-title text-sm">User Directory</h3>
                <div class="flex items-center gap-2 text-xs text-muted-foreground">Search, sort, and manage assignments.</div>
            </div>
            <div class="kt-card-content">
                <div class="kt-scrollable-x-auto">
                    <table class="kt-table table-auto kt-table-border" id="users-table">
                        <thead>
                            <tr class="text-left">
                                <th class="w-10">
                                    <input class="kt-checkbox" type="checkbox" data-kt-check="true"
                                        data-kt-check-target="#users-table .row-check" value="1" />
                                </th>
                                <th class="py-3">User</th>
                                <th class="py-3">Role</th>
                                <th class="py-3">Last login</th>
                                <th class="py-3">Joined Date</th>
                                <th class="py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>

        @include('admin.user-management.users.modals.create')
        @include('admin.user-management.users.modals.edit')
        @include('admin.user-management.users.modals.resend-password')
    </section>
@endsection

@push('styles')
    <link href="{{ asset('assets/plugins/global/plugins.bundle.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('assets/plugins/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />
@endpush

@push('vendor-scripts')
    <script src="{{ asset('assets/plugins/global/plugins.bundle.js') }}"></script>
    <script src="{{ asset('assets/plugins/custom/datatables/datatables.bundle.js') }}"></script>
@endpush

@push('custom-scripts')
    <script src="{{ asset('js/scripts.js') }}"></script>
    <script>
        $("#users-table").DataTable({
            "processing": true,
            "serverSide": true,
            "searchDelay": 1500,
            "searching": true,
            "ajax": {
                "url": "{{ request()->route('subdomain') ? route('tenant.users.all', ['subdomain' => request()->route('subdomain')]) : route('users.all') }}",
                "dataType": "json",
                "type": "POST",
                "data": { _token: "{{csrf_token()}}" }
            },
            "language": {
                "emptyTable": `
                        <div class="flex flex-col items-center gap-3 py-10">
                            <img src="{{ asset('assets/media/illustrations/sketchy-1/5.png') }}" class="w-56" />
                            <div class="text-lg font-semibold text-foreground">No users found</div>
                            <div class="text-sm text-muted-foreground">It looks like you haven't added any team members yet.</div>
                            @can('create user')
                                <button type="button" class="kt-btn kt-btn-primary" onclick="createUser()">
                                    <i class="ki-filled ki-plus"></i>
                                    Add Your First User
                                </button>
                            @endcan
                        </div>
                    `
            },
            "lengthMenu": [
                [25, 50, 100, 250, 500, -1], [25, 50, 100, 250, 500, "All"]
            ],
            "columns": [
                { "data": 'uuid', orderable: false, searchable: false },
                { "data": "client", className: "align-middle" },
                { "data": "role", orderable: false },
                { "data": "last_login_at" },
                { "data": "created_at" },
                { "data": "action", orderable: false, searchable: false, className: "text-end" }
            ],
            "columnDefs": [
                {
                    // For Responsive
                    className: 'control',
                    orderable: false,
                    responsivePriority: 2,
                    targets: 0
                },
                {
                    // For Checkboxes
                    targets: 0,
                    orderable: false,
                    responsivePriority: 3,
                    render: function (data) {
                        return (
                            `
                                    <input class="kt-checkbox row-check" type="checkbox" value="${data}" />`
                        );
                    },
                    checkboxes: {
                        selectAllRender:
                            '<input class="kt-checkbox" type="checkbox" value="" id="checkboxSelectAll" />',
                        selectRow: true
                    }
                },
            ],
            "order": [4, 'desc'],
            "dom":
                "<'flex flex-wrap items-center justify-between gap-4 mb-4'lf>" +
                "<'table-responsive'tr>" +
                "<'flex flex-wrap items-center justify-between gap-4 mt-4'ip>",

        });
    </script>
@endpush
