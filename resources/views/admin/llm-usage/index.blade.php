@extends('layouts.metronic.app')

@section('title', 'Global LLM Usage Dashboard')

@section('content')
    <section class="grid gap-6">
        <div class="rounded-xl border border-border bg-background p-6 lg:p-8">
            <p class="text-xs uppercase tracking-wide text-muted-foreground">AI Operations</p>
            <h1 class="mt-2 text-2xl font-semibold text-foreground">Global LLM Usage Dashboard</h1>
            <p class="mt-2 text-sm text-muted-foreground">Monitor usage, cost, and tenant model distribution across the platform.</p>
        </div>

        <div class="grid gap-5 lg:grid-cols-3">
            <div class="rounded-xl border border-border bg-background p-6">
                <p class="text-xs uppercase text-muted-foreground">Total Tokens</p>
                <p class="mt-2 text-3xl font-semibold text-foreground">{{ number_format((float) ($summary->total_tokens ?? 0)) }}</p>
                <p class="mt-1 text-xs text-muted-foreground">All time</p>
            </div>
            <div class="rounded-xl border border-border bg-background p-6">
                <p class="text-xs uppercase text-muted-foreground">Estimated Cost</p>
                <p class="mt-2 text-3xl font-semibold text-foreground">${{ number_format((float) ($summary->total_cost ?? 0), 2) }}</p>
                <p class="mt-1 text-xs text-muted-foreground">All time</p>
            </div>
            <div class="rounded-xl border border-border bg-background p-6">
                <p class="text-xs uppercase text-muted-foreground">Total Requests</p>
                <p class="mt-2 text-3xl font-semibold text-foreground">{{ number_format((float) ($summary->total_requests ?? 0)) }}</p>
                <p class="mt-1 text-xs text-muted-foreground">All time</p>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-12">
            <div class="rounded-xl border border-border bg-background p-6 xl:col-span-8">
                <h2 class="text-lg font-semibold text-foreground">Top Tenants by Usage (Last 30 Days)</h2>
                <p class="mt-1 text-xs text-muted-foreground">Highest token consumers in the most recent 30-day window.</p>
                <div class="mt-4 overflow-x-auto">
                    <table class="kt-table table-auto kt-table-border">
                        <thead>
                            <tr class="text-xs uppercase text-muted-foreground">
                                <th>Tenant</th>
                                <th>Subdomain</th>
                                <th>Total Tokens</th>
                                <th class="text-right">Estimated Cost</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm text-foreground">
                            @forelse($topTenants as $usage)
                                <tr>
                                    <td class="font-medium text-foreground">{{ $usage->tenant_name }}</td>
                                    <td><code>{{ $usage->tenant_slug }}</code></td>
                                    <td>{{ number_format((float) $usage->total_tokens) }}</td>
                                    <td class="text-right">${{ number_format((float) $usage->total_cost, 4) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-8 text-center text-muted-foreground">No data found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-xl border border-border bg-background p-6 xl:col-span-4">
                <h2 class="text-lg font-semibold text-foreground">Model Distribution</h2>
                <p class="mt-1 text-xs text-muted-foreground">Usage split by model.</p>
                <div class="mt-4 overflow-x-auto">
                    <table class="kt-table table-auto kt-table-border">
                        <thead>
                            <tr class="text-xs uppercase text-muted-foreground">
                                <th>Model</th>
                                <th class="text-right">Tokens</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm text-foreground">
                            @forelse($topModels as $usage)
                                <tr>
                                    <td><code>{{ $usage->model }}</code></td>
                                    <td class="text-right">{{ number_format((float) $usage->total_tokens) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="2" class="py-8 text-center text-muted-foreground">No model usage data found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-background p-6">
            <h2 class="text-lg font-semibold text-foreground">Daily Usage Trend (Last 30 Days)</h2>
            <p class="mt-1 text-xs text-muted-foreground">Daily aggregate of token and cost usage.</p>
            <div class="mt-4 overflow-x-auto">
                <table class="kt-table table-auto kt-table-border">
                    <thead>
                        <tr class="text-xs uppercase text-muted-foreground">
                            <th>Date</th>
                            <th>Total Tokens</th>
                            <th class="text-right">Estimated Cost</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm text-foreground">
                        @forelse($dailyTrend as $trend)
                            <tr>
                                <td>{{ is_string($trend->day) ? $trend->day : $trend->day->format('Y-m-d') }}</td>
                                <td>{{ number_format((float) $trend->total_tokens) }}</td>
                                <td class="text-right">${{ number_format((float) $trend->total_cost, 4) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="py-8 text-center text-muted-foreground">No daily usage data found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
@endsection
