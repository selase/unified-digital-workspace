@extends('layouts.metronic.app')

@section('title', __('locale.menu.roles_and_permissions'))

@section('content')
    <section class="grid gap-6">
        <div class="rounded-xl border border-border bg-background p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Access Control</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Roles & Permissions</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Create roles and assign guard scopes.</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <div>
                        <select id="tenant_filter" class="kt-select w-64" data-placeholder="Filter by Tenant">
                            <option value=""></option>
                            <option value="all">All (Global + Tenants)</option>
                            <option value="global">Global Only</option>
                            @foreach($tenants as $tenant)
                                <option value="{{ $tenant->id }}">{{ $tenant->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <a href="{{ route('roles.create') }}" class="kt-btn kt-btn-primary">
                        <i class="ki-filled ki-plus text-base"></i>
                        Add New Role
                    </a>
                </div>
            </div>
        </div>

        <div class="kt-card kt-card-grid min-w-full">
            <div class="kt-card-header flex-wrap gap-2">
                <h3 class="kt-card-title text-sm">Roles Registry</h3>
                <div class="flex items-center gap-2 text-xs text-muted-foreground">Filter by tenant or global scope.</div>
            </div>
            <div class="kt-card-content">
                <div class="kt-scrollable-x-auto">
                    <table class="kt-table table-auto kt-table-border" id="roles-table">
                        <thead>
                            <tr class="text-left">
                                <th class="w-10">
                                    <input class="kt-checkbox" type="checkbox" data-kt-check="true"
                                        data-kt-check-target="#roles-table .row-check" value="1" />
                                </th>
                                <th class="py-3">Role Name</th>
                                <th class="py-3">Guard</th>
                                <th class="py-3">Created At</th>
                                <th class="py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {{-- Content loaded via AJAX --}}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
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
    <script>
        const table = $("#roles-table").DataTable({
            "processing": true,
            "serverSide": true,
            "searchDelay": 1500,
            "searching": true,
            "ajax": {
                "url": "{{ route('roles.index') }}",
                "type": "GET",
                "data": function (d) {
                    d.tenant_id = $('#tenant_filter').val();
                }
            },
            "columns": [
                { "data": 'id', orderable: false, searchable: false },
                { "data": "name" },
                { "data": "guard_name" },
                { "data": "created_at" },
                { "data": "action", orderable: false, searchable: false, className: "text-end" }
            ],
            "columnDefs": [
                {
                    targets: 0,
                    orderable: false,
                    render: function (data) {
                        return `
                                <input class="kt-checkbox row-check" type="checkbox" value="${data}" />`;
                    }
                }
            ],
            "order": [[3, 'desc']],
            "dom":
                "<'flex flex-wrap items-center justify-between gap-4 mb-4'lf>" +
                "<'table-responsive'tr>" +
                "<'flex flex-wrap items-center justify-between gap-4 mt-4'ip>",
        });

        $('#tenant_filter').on('change', function() {
            table.draw();
        });
    </script>
@endpush
