@extends('layouts.metronic.app')

@section('title', __('locale.menu.login_history'))

@section('content')
    <section class="grid gap-6">
        <div class="rounded-xl border border-border bg-background p-6 lg:p-8">
            <div>
                <p class="text-xs uppercase tracking-wide text-muted-foreground">Audit Trail</p>
                <h1 class="mt-2 text-2xl font-semibold text-foreground">{{ __('locale.menu.login_history') }}</h1>
                <p class="mt-2 text-sm text-muted-foreground">Monitor login telemetry across browsers, devices, and locations.</p>
            </div>
        </div>

        <div class="grid gap-6 md:grid-cols-2">
            <div class="rounded-xl border border-border bg-background p-6">
                <h3 class="text-sm font-semibold uppercase text-foreground">{{ strtoupper(__('locale.labels.browsers')) }}</h3>
                <div class="mt-4" id="browser-session" style="width: 100%; height: 300px;"></div>
            </div>
            <div class="rounded-xl border border-border bg-background p-6">
                <h3 class="text-sm font-semibold uppercase text-foreground">{{ strtoupper(__('locale.labels.locations')) }}</h3>
                <div class="mt-4" id="location-session" style="width: 100%; height: 300px;"></div>
            </div>
            <div class="rounded-xl border border-border bg-background p-6">
                <h3 class="text-sm font-semibold uppercase text-foreground">{{ strtoupper(__('locale.labels.platform_or_os')) }}</h3>
                <div class="mt-4" id="platform-session" style="width: 100%; height: 300px;"></div>
            </div>
            <div class="rounded-xl border border-border bg-background p-6">
                <h3 class="text-sm font-semibold uppercase text-foreground">{{ strtoupper(__('locale.labels.devices')) }}</h3>
                <div class="mt-4" id="client-device-session" style="width: 100%; height: 300px;"></div>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-background p-6">
            <div class="overflow-x-auto">
                <table class="kt-table table-auto kt-table-border" id="login-history-table">
                    <thead>
                        <tr class="text-xs uppercase text-muted-foreground">
                            <th>{{ __('locale.labels.sl') }}</th>
                            <th>{{ __('locale.labels.user') }}</th>
                            <th>{{ __('locale.labels.location') }}</th>
                            <th>{{ __('locale.labels.device') }}</th>
                            <th>{{ __('locale.labels.platform') }}</th>
                            <th>{{ __('locale.labels.ip_address') }}</th>
                            <th>{{ __('locale.labels.last_login') }}</th>
                            <th>{{ __('locale.labels.last_logout') }}</th>
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
    <script src="https://cdn.jsdelivr.net/npm/echarts@5.3.3/dist/echarts.min.js"></script>
@endpush

@push('custom-scripts')
    <script src="{{ asset('js/scripts.js') }}"></script>
    <script>
        $("#login-history-table").DataTable({
            "processing": true,
            "serverSide": true,
            "searchDelay": 1500,
            "searching": true,
            "ajax": {
                "url": "{{ route('audit-trail.login-history.all') }}",
                "dataType": "json",
                "type": "POST",
                "data": {_token: "{{csrf_token()}}"}
            },
            "lengthMenu": [
                [25, 50, 100, 250, 500, -1], [25, 50, 100, 250, 500, "All"]
            ],
            "columns": [
                {"data": 'id'},
                {"data": "user", className: "align-middle"},
                {"data": "location"},
                {"data": "client_device"},
                {"data": "platform"},
                {"data": "ip_address"},
                {"data": "login_at"},
                {"data": "logout_at"},
            ],
            "order": [6, 'desc'],
            "dom":
                "<'flex flex-wrap items-center justify-between gap-4 mb-4'lf>" +
                "<'table-responsive'tr>" +
                "<'flex flex-wrap items-center justify-between gap-4 mt-4'ip>",
        });
    </script>

    @include('admin.audit-trail.partials.login-history-chartsjs')
@endpush
