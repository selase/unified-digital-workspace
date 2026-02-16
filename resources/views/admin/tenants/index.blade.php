@extends('layouts.metronic.app')

@section('title', __('locale.menu.tenants'))

@section('content')
    <section class="grid gap-6">
        <div class="rounded-xl border border-border bg-background p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Tenant Directory</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Tenants</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Manage organizations, access, and subscription status.</p>
                </div>
                @can('create tenant')
                    <a href="{{ route('tenants.create') }}" class="kt-btn kt-btn-primary">
                        <i class="ki-filled ki-plus text-base"></i>
                        Add Tenant
                    </a>
                @endcan
            </div>
        </div>

        <div class="kt-card kt-card-grid min-w-full">
            <div class="kt-card-header flex-wrap gap-2">
                <h3 class="kt-card-title text-sm">Tenant Directory</h3>
                <div class="flex items-center gap-2 text-xs text-muted-foreground">Search, sort, and manage tenant access.</div>
            </div>
            <div class="kt-card-content">
                <div class="kt-scrollable-x-auto">
                    <table class="kt-table table-auto kt-table-border" id="tenants-table">
                        <thead>
                            <tr class="text-xs uppercase text-muted-foreground">
                                <th class="w-10">
                                    <input class="kt-checkbox" type="checkbox" data-kt-check="true" data-kt-check-target="#tenants-table .row-check" value="1" />
                                </th>
                                <th>{{ __('locale.labels.name') }}</th>
                                <th>{{ __('locale.labels.phone_number') }}</th>
                                <th>{{ __('locale.labels.subdomain') }}</th>
                                <th>{{ __('locale.labels.status') }}</th>
                                <th>{{ __('locale.labels.created_at') }}</th>
                                <th class="text-right">{{ __('locale.labels.actions') }}</th>
                            </tr>
                        </thead>
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
    <script src="{{ asset('js/scripts.js') }}"></script>
    <script>
        $("#tenants-table").DataTable({
            "processing": true,
            "serverSide": true,
            "searchDelay": 1500,
            "searching": true,
            "ajax": {
                "url": "{{ route('tenants.all') }}",
                "dataType": "json",
                "type": "POST",
                "data": {_token: "{{csrf_token()}}"}
            },
            "lengthMenu": [
                [25, 50, 100, 250, 500, -1], [25, 50, 100, 250, 500, "All"]
            ],
            "columns": [
                {"data": 'uuid', orderable: false, searchable: false},
                {"data": "name", className: "align-middle"},
                {"data": "phone"},
                {"data": "subdomain"},
                {"data": "status"},
                {"data": "created_at"},
                {"data": "action", orderable: false, searchable: false, className: "text-end"}
            ],
            "columnDefs": [
                {
                    className: 'control',
                    orderable: false,
                    responsivePriority: 2,
                    targets: 0
                },
                {
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
            "order": [5, 'desc'],
            "dom":
                "<'flex flex-wrap items-center justify-between gap-4 mb-4'lf>" +
                "<'table-responsive'tr>" +
                "<'flex flex-wrap items-center justify-between gap-4 mt-4'ip>",
        });
    </script>
@endpush
