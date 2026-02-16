@extends('layouts.metronic.app')

@section('title', 'Feature Registry')

@section('content')
    <section class="grid gap-6">
        <div class="rounded-xl border border-border bg-background p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Feature Registry</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Features & Capabilities</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Manage entitlements and system flags across plans.</p>
                </div>
                <a href="{{ route('features.create') }}" class="kt-btn kt-btn-primary">
                    <i class="ki-filled ki-plus text-base"></i>
                    Add Feature
                </a>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-background p-6">
            <div class="flex items-center justify-between gap-3 mb-4">
                <h2 class="text-lg font-semibold text-foreground">All Features</h2>
                <span class="text-xs text-muted-foreground">Search, filter, and manage system features.</span>
            </div>
            <div class="overflow-x-auto">
                <table class="kt-table table-auto kt-table-border" id="features-table">
                    <thead>
                        <tr class="text-xs uppercase text-muted-foreground">
                            <th>Name</th>
                            <th>Slug</th>
                            <th>Type</th>
                            <th>Created At</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
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
        const table = $("#features-table").DataTable({
            "processing": true,
            "serverSide": true,
            "searchDelay": 1500,
            "searching": true,
            "ajax": {
                "url": "{{ route('features.index') }}",
                "type": "GET",
            },
            "columns": [
                { "data": "name" },
                { "data": "slug" },
                { "data": "type" },
                { "data": "created_at" },
                { "data": "action", orderable: false, searchable: false, className: "text-end" }
            ],
            "order": [[3, 'desc']],
            "dom":
                "<'flex flex-wrap items-center justify-between gap-4 mb-4'lf>" +
                "<'table-responsive'tr>" +
                "<'flex flex-wrap items-center justify-between gap-4 mt-4'ip>",
        });

        function deleteFeature(id) {
            if (confirm('Are you sure? This effectively removes this capability from the system.')) {
                $.ajax({
                    url: '/admin/features/' + id,
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    type: 'DELETE',
                    success: function (result) {
                        toastr.success(result.message);
                        table.ajax.reload();
                    },
                    error: function () {
                        toastr.error('Error deleting feature');
                    }
                });
            }
        }
    </script>
@endpush
