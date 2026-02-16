@extends('layouts.metronic.app')

@section('title', $tenant->name)

@section('content')
    @php
        $statusValue = $tenant->status?->value;
        $statusClass = match ($statusValue) {
            'active' => 'kt-badge-success',
            'deactivated' => 'kt-badge-warning',
            'banned' => 'kt-badge-destructive',
            default => 'kt-badge-secondary',
        };
        $tenantLogo = $tenant->logo_url ?: $tenant->gravatar;
    @endphp

    <section class="grid gap-6">
        <div class="rounded-xl border border-border bg-background p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Tenant</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">{{ $tenant->name }}</h1>
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <span class="kt-badge {{ $statusClass }}">{{ ucfirst($statusValue ?? 'n/a') }}</span>
                        <span class="text-xs text-muted-foreground">Subdomain: {{ $tenant->slug }}</span>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    @can('update tenant')
                        <a href="{{ route('tenants.edit', $tenant->uuid) }}" class="kt-btn kt-btn-outline">Edit Tenant</a>
                    @endcan
                </div>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="rounded-xl border border-border bg-background p-6">
                <div class="flex items-center gap-4">
                    <img src="{{ $tenantLogo }}" alt="{{ $tenant->name }}" class="size-16 rounded-full object-cover" />
                    <div>
                        <div class="text-lg font-semibold text-foreground">{{ $tenant->name }}</div>
                        <div class="text-xs text-muted-foreground">{{ $tenant->email }}</div>
                    </div>
                </div>

                <div class="mt-6 space-y-4 text-sm">
                    <div>
                        <div class="text-xs uppercase text-muted-foreground">{{ __('locale.labels.phone_number') }}</div>
                        <div class="text-sm text-foreground">{{ $tenant->phone_number }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase text-muted-foreground">{{ __('locale.labels.address') }}</div>
                        <div class="text-sm text-foreground">{{ $tenant->address }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase text-muted-foreground">{{ __('locale.labels.date_joined') }}</div>
                        <div class="text-sm text-foreground">{{ $tenant->created_at->format('M d, Y') }}</div>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-2 grid gap-6">
                <div class="rounded-xl border border-border bg-background p-6">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-semibold text-foreground">Usage Analytics</h2>
                            <p class="mt-1 text-xs text-muted-foreground">Track API requests for this tenant.</p>
                        </div>
                        <form action="{{ route('tenants.show', $tenant->uuid) }}" method="GET" class="flex flex-wrap items-end gap-3">
                            <div class="flex flex-col gap-1">
                                <label class="text-xs font-semibold text-muted-foreground" for="start_date">Start</label>
                                <input id="start_date" type="date" name="start_date" class="kt-input kt-input-sm" value="{{ $start_date }}" onchange="this.form.submit()">
                            </div>
                            <div class="flex flex-col gap-1">
                                <label class="text-xs font-semibold text-muted-foreground" for="end_date">End</label>
                                <input id="end_date" type="date" name="end_date" class="kt-input kt-input-sm" value="{{ $end_date }}" onchange="this.form.submit()">
                            </div>
                            <div class="flex flex-col gap-1">
                                <label class="text-xs font-semibold text-muted-foreground" for="days">Range</label>
                                <select id="days" name="days" class="kt-select kt-select-sm" onchange="this.form.submit()">
                                    <option value="1" {{ $days == 1 ? 'selected' : '' }}>Last 24 Hours</option>
                                    <option value="7" {{ $days == 7 ? 'selected' : '' }}>Last 7 Days</option>
                                    <option value="30" {{ $days == 30 ? 'selected' : '' }}>Last 30 Days</option>
                                    <option value="90" {{ $days == 90 ? 'selected' : '' }}>Last 90 Days</option>
                                </select>
                            </div>
                        </form>
                    </div>
                    <div class="mt-6">
                        <x-chart id="tenant-specific-usage-chart" type="line" :data="[
                            'labels' => $usageTrend['labels'],
                            'datasets' => [
                                [
                                    'label' => 'Hourly Requests',
                                    'data' => $usageTrend['data'],
                                    'borderColor' => '#009EF7',
                                    'backgroundColor' => 'rgba(0, 158, 247, 0.1)',
                                    'fill' => true,
                                    'tension' => 0.4,
                                ],
                            ],
                        ]" />
                    </div>
                </div>

                <div class="grid gap-6 lg:grid-cols-2">
                    <div class="rounded-xl border border-border bg-background p-6">
                        <div>
                            <h3 class="text-base font-semibold text-foreground">Storage (MB)</h3>
                            <p class="mt-1 text-xs text-muted-foreground">S3/Object usage.</p>
                        </div>
                        <div class="mt-4">
                            <x-chart id="tenant-storage-chart" type="line" :data="[
                                'labels' => $storageTrend['labels'],
                                'datasets' => [
                                    [
                                        'label' => 'Total MB',
                                        'data' => $storageTrend['data'],
                                        'borderColor' => '#7239EA',
                                        'backgroundColor' => 'rgba(114, 57, 234, 0.1)',
                                        'fill' => true,
                                    ],
                                ],
                            ]" />
                        </div>
                    </div>

                    <div class="rounded-xl border border-border bg-background p-6">
                        <div>
                            <h3 class="text-base font-semibold text-foreground">Database (MB)</h3>
                            <p class="mt-1 text-xs text-muted-foreground">Database footprint.</p>
                        </div>
                        <div class="mt-4">
                            <x-chart id="tenant-db-chart" type="line" :data="[
                                'labels' => $dbTrend['labels'],
                                'datasets' => [
                                    [
                                        'label' => 'Total MB',
                                        'data' => $dbTrend['data'],
                                        'borderColor' => '#FFC700',
                                        'backgroundColor' => 'rgba(255, 199, 0, 0.1)',
                                        'fill' => true,
                                    ],
                                ],
                            ]" />
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-border bg-background p-6">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-semibold text-foreground">Team Members</h2>
                            <p class="mt-1 text-xs text-muted-foreground">Manage tenant users and role assignments.</p>
                        </div>
                        @can('create team')
                            <a href="{{ route('tenants.team.create', $tenant->uuid) }}" class="kt-btn kt-btn-primary">
                                <i class="ki-filled ki-plus text-base"></i>
                                Add Team Member
                            </a>
                        @endcan
                    </div>

                    <div class="mt-4 overflow-x-auto">
                        <table class="kt-table table-auto kt-table-border" id="team-members-table">
                            <thead>
                                <tr class="text-xs uppercase text-muted-foreground">
                                    <th class="w-10">
                                        <input class="kt-checkbox" type="checkbox" data-kt-check="true" data-kt-check-target="#team-members-table .row-check" value="1" />
                                    </th>
                                    <th>User</th>
                                    <th>Role</th>
                                    <th>Joined Date</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>

                <div>
                    <livewire:admin.tenant-health-check :tenant="$tenant" />
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
        $("#team-members-table").DataTable({
            "processing": true,
            "serverSide": true,
            "searchDelay": 1500,
            "searching": true,
            "ajax": {
                "url": "{{ route('tenants.team.all', $tenant->uuid) }}",
                "dataType": "json",
                "type": "POST",
                "data": {_token: "{{csrf_token()}}"}
            },
            "lengthMenu": [
                [25, 50, 100, 250, 500, -1], [25, 50, 100, 250, 500, "All"]
            ],
            "columns": [
                {"data": 'uuid', orderable: false, searchable: false},
                {"data": "client", className: "align-middle"},
                {"data": "role", orderable: false},
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
                            `<input class="kt-checkbox row-check" type="checkbox" value="${data}" />`
                        );
                    },
                    checkboxes: {
                        selectAllRender:
                            '<input class="kt-checkbox" type="checkbox" value="" id="checkboxSelectAll" />',
                        selectRow: true
                    }
                },
            ],
            "order": [3, 'desc'],
            "dom":
                "<'flex flex-wrap items-center justify-between gap-4 mb-4'lf>" +
                "<'table-responsive'tr>" +
                "<'flex flex-wrap items-center justify-between gap-4 mt-4'ip>",
        });
    </script>
@endpush
