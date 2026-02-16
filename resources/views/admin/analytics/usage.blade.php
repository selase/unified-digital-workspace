@extends('layouts.metronic.app')

@section('title', 'System Usage Analytics')

@section('content')
    <section class="grid gap-6">
        @php
            $usageMetrics = $usageMetrics ?? \App\Enum\UsageMetric::cases();
        @endphp
        <div class="rounded-xl border border-border bg-background p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Analytics</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Usage Analytics</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Monitor system throughput, storage, and database utilization.</p>
                </div>
                <form action="{{ route('admin.billing.analytics.usage') }}" method="GET"
                    class="flex flex-wrap items-center gap-3">
                    <select name="tenant_id" class="kt-select w-56" data-placeholder="Filter by Tenant"
                        onchange="this.form.submit()">
                        <option value="" {{ !$selectedTenantId ? 'selected' : '' }}>Global / All System</option>
                        @foreach($tenants as $tenant)
                            <option value="{{ $tenant->id }}" {{ $selectedTenantId == $tenant->id ? 'selected' : '' }}>{{ $tenant->name }}</option>
                        @endforeach
                    </select>
                    <div class="flex items-center gap-2">
                        <input type="date" name="start_date" class="kt-input" value="{{ $start_date }}" onchange="this.form.submit()">
                        <span class="text-xs text-muted-foreground">to</span>
                        <input type="date" name="end_date" class="kt-input" value="{{ $end_date }}" onchange="this.form.submit()">
                    </div>
                    <select name="days" class="kt-select w-48" onchange="this.form.submit()">
                        <option value="1" {{ $days == 1 ? 'selected' : '' }}>Last 24 Hours</option>
                        <option value="7" {{ $days == 7 ? 'selected' : '' }}>Last 7 Days</option>
                        <option value="30" {{ $days == 30 ? 'selected' : '' }}>Last 30 Days</option>
                        <option value="90" {{ $days == 90 ? 'selected' : '' }}>Last 90 Days</option>
                    </select>
                </form>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-12">
            <div class="rounded-xl border border-border bg-background p-6 xl:col-span-8">
                <div>
                    <h3 class="text-sm font-semibold uppercase text-foreground">Request Throughput</h3>
                    <p class="text-xs text-muted-foreground">Global system load over time</p>
                </div>
                <div class="mt-4">
                    <x-chart id="throughput-chart" type="line" :data="[
                        'labels' => $requestTrend['labels'],
                        'datasets' => [
                            [
                                'label' => 'Requests',
                                'data' => $requestTrend['data'],
                                'borderColor' => '#009EF7',
                                'backgroundColor' => 'rgba(0, 158, 247, 0.1)',
                                'fill' => true,
                                'tension' => 0.4,
                                'pointRadius' => 0,
                            ],
                        ],
                    ]" />
                </div>
            </div>

            <div class="rounded-xl border border-border bg-background p-6 xl:col-span-4">
                <div>
                    <h3 class="text-sm font-semibold uppercase text-foreground">Traffic Health</h3>
                    <p class="text-xs text-muted-foreground">Status code distribution</p>
                </div>
                <div class="mt-4">
                    <x-chart id="health-chart" type="doughnut" :data="[
                        'labels' => $statusBreakdown['labels'],
                        'datasets' => [
                            [
                                'data' => $statusBreakdown['data'],
                                'backgroundColor' => ['#50CD89', '#F1416C', '#FFC700', '#009EF7'],
                            ],
                        ],
                    ]" />
                </div>
                <div class="mt-6 space-y-3">
                    @foreach($statusBreakdown['labels'] as $index => $label)
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="size-2 rounded-full" style="background-color: {{ ['#50CD89', '#F1416C', '#FFC700', '#009EF7'][$index % 4] }}"></span>
                                <span class="text-sm text-foreground">{{ $label }}</span>
                            </div>
                            <span class="text-sm text-muted-foreground">{{ number_format($statusBreakdown['data'][$index]) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-background p-6">
            <div>
                <h3 class="text-sm font-semibold uppercase text-foreground">Engine Load Profile</h3>
                <p class="text-xs text-muted-foreground">Aggregated hourly request volume (Peak Hours)</p>
            </div>
            <div class="mt-4 flex flex-wrap gap-2">
                @foreach($peakHours as $hour => $val)
                    @php
                        $max = max($peakHours) ?: 1;
                        $opacity = 0.1 + (($val / $max) * 0.9);
                        $color = $val > ($max * 0.8) ? '#F1416C' : '#009EF7';
                    @endphp
                    <div class="flex flex-col items-center grow min-w-24 rounded-lg border border-dashed border-border p-3"
                        style="background-color: {{ $color }}{{ dechex(round($opacity * 255)) }};">
                        <span class="text-xs text-muted-foreground mb-1">{{ sprintf('%02d:00', $hour) }}</span>
                        <span class="text-sm font-semibold text-foreground">{{ number_format($val) }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="rounded-xl border border-border bg-background p-6">
                <div>
                    <h3 class="text-sm font-semibold uppercase text-foreground">Cloud Storage</h3>
                    <p class="text-xs text-muted-foreground">S3/Object storage consumption (GB)</p>
                </div>
                <div class="mt-4">
                    <x-chart id="storage-resource-chart" type="line" :data="[
                        'labels' => $storageTrend['labels'],
                        'datasets' => [
                            [
                                'label' => 'Total GB',
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
                    <h3 class="text-sm font-semibold uppercase text-foreground">Database Footprint</h3>
                    <p class="text-xs text-muted-foreground">Aggregate DB size across tenants (MB)</p>
                </div>
                <div class="mt-4">
                    <x-chart id="db-resource-chart" type="line" :data="[
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
            <div>
                <h3 class="text-sm font-semibold uppercase text-foreground">Metric Catalog</h3>
                <p class="text-xs text-muted-foreground">Definitions of active usage signals</p>
            </div>
            <div class="kt-table-wrapper mt-4">
                <table class="kt-table table-auto kt-table-border">
                    <thead>
                        <tr class="text-xs uppercase text-muted-foreground">
                            <th>Metric Name</th>
                            <th>Category</th>
                            <th>Unit</th>
                            <th>Attribution</th>
                            <th>Default Rate</th>
                            <th>Updated</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm text-muted-foreground">
                        @forelse($usageMetrics as $metric)
                            @php
                                $metricValue = $metric instanceof \App\Enum\UsageMetric ? $metric->value : (string) $metric;
                                $metricCategory = str($metricValue)->before('.')->replace('_', ' ')->title();
                                $metricUnit = $metric instanceof \App\Enum\UsageMetric ? $metric->unit() : 'n/a';
                            @endphp
                            <tr>
                                <td class="text-foreground font-medium">{{ str($metricValue)->replace('_', ' ')->title() }}</td>
                                <td>{{ $metricCategory }}</td>
                                <td>{{ $metricUnit }}</td>
                                <td>System</td>
                                <td>—</td>
                                <td>{{ now()->format('M d, Y') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-6 text-center text-muted-foreground">No metrics configured yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
@endsection
