@extends('layouts.metronic.app')

@section('title', 'Incident Management Hub')

@section('content')
    <section class="grid gap-6">
        <div class="rounded-xl border border-border bg-background p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Incident Management</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Incident Management Hub</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Monitor incident health, workload, and SLA risk before escalations occur.</p>
                </div>
                <a href="{{ route('api.incident-management.v1.incidents.index') }}" class="kt-btn kt-btn-outline">Open API</a>
            </div>

            <div class="mt-5 grid gap-3 md:grid-cols-5">
                <div class="rounded-lg border border-border bg-muted/30 p-4">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Total</p>
                    <p class="mt-2 text-xl font-semibold text-foreground">{{ $totalIncidents }}</p>
                </div>
                <div class="rounded-lg border border-border bg-muted/30 p-4">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Open</p>
                    <p class="mt-2 text-xl font-semibold text-foreground">{{ $openIncidents }}</p>
                </div>
                <div class="rounded-lg border border-border bg-muted/30 p-4">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Resolved</p>
                    <p class="mt-2 text-xl font-semibold text-foreground">{{ $resolvedIncidents }}</p>
                </div>
                <div class="rounded-lg border border-border bg-muted/30 p-4">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Overdue</p>
                    <p class="mt-2 text-xl font-semibold {{ $overdueIncidents > 0 ? 'text-destructive' : 'text-foreground' }}">{{ $overdueIncidents }}</p>
                </div>
                <div class="rounded-lg border border-border bg-muted/30 p-4">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">At Risk (24h)</p>
                    <p class="mt-2 text-xl font-semibold {{ $atRiskIncidents > 0 ? 'text-warning' : 'text-foreground' }}">{{ $atRiskIncidents }}</p>
                </div>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-12">
            <div class="rounded-xl border border-border bg-background p-6 xl:col-span-8">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h2 class="text-lg font-semibold text-foreground">Recent Incidents</h2>
                    <span class="text-xs text-muted-foreground">Latest 10 updates</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="kt-table">
                        <thead>
                            <tr class="text-xs uppercase text-muted-foreground">
                                <th>Reference</th>
                                <th>Title</th>
                                <th>Status</th>
                                <th>Priority</th>
                                <th>Due</th>
                                <th>Updated</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm text-foreground">
                            @forelse($recentIncidents as $incident)
                                <tr>
                                    <td><span class="font-medium">{{ $incident->reference_code }}</span></td>
                                    <td>{{ $incident->title }}</td>
                                    <td>
                                        <span class="kt-badge kt-badge-outline">{{ $incident->status?->name ?: 'Unassigned' }}</span>
                                    </td>
                                    <td>{{ $incident->priority?->name ?: 'Unassigned' }}</td>
                                    <td>{{ $incident->due_at?->format('M d, Y H:i') ?: '—' }}</td>
                                    <td>{{ $incident->updated_at?->diffForHumans() }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-8 text-center text-muted-foreground">No incidents available yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-xl border border-border bg-background p-6 xl:col-span-4">
                <h2 class="text-lg font-semibold text-foreground">Breakdown</h2>

                <div class="mt-4 space-y-4">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">By Status</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @forelse($statusBreakdown as $label => $count)
                                <span class="kt-badge kt-badge-outline">{{ $label }}: {{ $count }}</span>
                            @empty
                                <span class="text-sm text-muted-foreground">No status data.</span>
                            @endforelse
                        </div>
                    </div>

                    <div>
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">By Priority</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @forelse($priorityBreakdown as $label => $count)
                                <span class="kt-badge kt-badge-outline">{{ $label }}: {{ $count }}</span>
                            @empty
                                <span class="text-sm text-muted-foreground">No priority data.</span>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
