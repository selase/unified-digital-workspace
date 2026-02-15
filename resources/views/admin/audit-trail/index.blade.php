@extends('layouts.metronic.app')

@section('title', __('locale.menu.activity_logs'))

@section('content')
    <section class="grid gap-6">
        <div class="rounded-xl border border-border bg-background p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Audit Trail</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">{{ __('Activity Logs') }}</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Track actions across tenants, users, and modules.</p>
                </div>
                <a href="{{ route('audit-trail.activity-logs.export') }}" class="kt-btn kt-btn-outline">
                    <i class="ki-filled ki-download text-base"></i>
                    Export CSV
                </a>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-background p-6">
            <div class="overflow-x-auto">
                <table class="kt-table" id="activity-log-table">
                    <thead>
                        <tr class="text-xs uppercase text-muted-foreground">
                            <th>{{ __('locale.labels.log_id') }}</th>
                            <th>{{ __('locale.labels.location') }}</th>
                            <th>{{ __('locale.labels.event') }}</th>
                            <th>{{ __('locale.labels.subject_type') }}</th>
                            <th>{{ __('locale.labels.causer') }}</th>
                            <th>{{ __('locale.labels.created_at') }}</th>
                            <th class="text-right">{{ __('locale.labels.properties') }}</th>
                        </tr>
                    </thead>
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
    <script src="{{ asset('js/scripts.js') }}"></script>
    <script>
        $("#activity-log-table").DataTable({
            "processing": true,
            "serverSide": true,
            "searchDelay": 1500,
            "searching": true,
            "ajax": {
                "url": "{{ route('audit-trail.activity-logs.all') }}",
                "dataType": "json",
                "type": "POST",
                "data": {_token: "{{csrf_token()}}"}
            },
            "lengthMenu": [
                [25, 50, 100, 250, 500, -1], [25, 50, 100, 250, 500, "All"]
            ],
            "columns": [
                {"data": 'id'},
                {"data": "location"},
                {"data": "event"},
                {"data": "subject_type"},
                {"data": "causer"},
                {"data": "created_at"},
                {"data": "properties", orderable: false, searchable: false, className: "text-end"}
            ],
            responsive: true,
            "dom":
                "<'flex flex-wrap items-center justify-between gap-4 mb-4'lf>" +
                "<'table-responsive'tr>" +
                "<'flex flex-wrap items-center justify-between gap-4 mt-4'ip>",
            "order": [5, 'desc'],
        });
    </script>
@endpush
