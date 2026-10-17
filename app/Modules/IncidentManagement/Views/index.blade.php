@extends('layouts.metronic.app')

@section('title', 'Incident Management Hub')

@section('content')
    <div class="grid gap-5">
        <x-metronic.page-header
            title="Incident Management Hub"
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Incident Management'],
            ]"
        >
            <x-slot:actions>
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('incident-management.incidents.index') }}" class="kt-btn kt-btn-outline">
                        Incident Register
                    </a>
                    <a href="{{ route('incident-management.tasks.index') }}" class="kt-btn kt-btn-outline">
                        Task Board
                    </a>
                    @can('incidents.create')
                        <a href="{{ route('incident-management.incidents.create') }}" class="kt-btn kt-btn-primary flex items-center gap-1.5">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            Log Incident
                        </a>
                    @endcan
                </div>
            </x-slot:actions>
        </x-metronic.page-header>

        {{-- KPI Metrics Grid --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
            <div class="rounded-xl border border-border/80 bg-card p-4 transition-all hover:border-primary/40 shadow-xs">
                <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Total Incidents</p>
                <p class="mt-2 text-2xl font-bold text-foreground">{{ $totalIncidents }}</p>
                <p class="mt-1 text-[11px] text-muted-foreground">All logged occurrences</p>
            </div>

            <div class="rounded-xl border border-border/80 bg-card p-4 transition-all hover:border-primary/40 shadow-xs">
                <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Open Incidents</p>
                <p class="mt-2 text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $openIncidents }}</p>
                <p class="mt-1 text-[11px] text-muted-foreground">In triage & investigation</p>
            </div>

            <div class="rounded-xl border border-border/80 bg-card p-4 transition-all hover:border-primary/40 shadow-xs">
                <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Resolved</p>
                <p class="mt-2 text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ $resolvedIncidents }}</p>
                <p class="mt-1 text-[11px] text-muted-foreground">CAPA verified & cleared</p>
            </div>

            <div class="rounded-xl border border-border/80 bg-card p-4 transition-all hover:border-primary/40 shadow-xs">
                <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Overdue</p>
                <p class="mt-2 text-2xl font-bold {{ $overdueIncidents > 0 ? 'text-destructive' : 'text-foreground' }}">
                    {{ $overdueIncidents }}
                </p>
                <p class="mt-1 text-[11px] text-muted-foreground">SLA resolution breached</p>
            </div>

            <div class="rounded-xl border border-border/80 bg-card p-4 transition-all hover:border-primary/40 shadow-xs">
                <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">At Risk (24h)</p>
                <p class="mt-2 text-2xl font-bold {{ $atRiskIncidents > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-foreground' }}">
                    {{ $atRiskIncidents }}
                </p>
                <p class="mt-1 text-[11px] text-muted-foreground">Impending SLA escalation</p>
            </div>
        </div>

        {{-- Main Section: Recent Incidents and Breakdown --}}
        <div class="grid grid-cols-1 xl:grid-cols-12 gap-5">
            <div class="xl:col-span-8">
                <x-metronic.card title="Recent Incidents" subtitle="Latest 10 logged or updated occurrences">
                    <x-slot:headerActions>
                        <a href="{{ route('incident-management.incidents.index') }}" class="kt-btn kt-btn-ghost text-xs">
                            View All Incidents &rarr;
                        </a>
                    </x-slot:headerActions>

                    <div class="overflow-x-auto">
                        <x-metronic.table>
                            <thead>
                                <tr class="text-xs uppercase text-muted-foreground">
                                    <th>Reference & Title</th>
                                    <th>Status</th>
                                    <th>Priority</th>
                                    <th>Due At</th>
                                    <th>Updated</th>
                                    <th class="text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="text-xs text-foreground divide-y divide-border/60">
                                @forelse($recentIncidents as $incident)
                                    @php
                                        $priorityName = mb_strtolower($incident->priority?->name ?? '');
                                        $priorityVariant = match (true) {
                                            str_contains($priorityName, 'critical') || str_contains($priorityName, 'urgent') => 'danger',
                                            str_contains($priorityName, 'high') => 'warning',
                                            str_contains($priorityName, 'medium') => 'primary',
                                            default => 'outline',
                                        };

                                        $statusName = mb_strtolower($incident->status?->name ?? '');
                                        $statusVariant = match (true) {
                                            str_contains($statusName, 'resolved') || str_contains($statusName, 'closed') => 'success',
                                            str_contains($statusName, 'investigat') || str_contains($statusName, 'progress') => 'primary',
                                            str_contains($statusName, 'escalat') => 'danger',
                                            default => 'outline',
                                        };
                                    @endphp
                                    <tr class="hover:bg-muted/30 transition-colors">
                                        <td>
                                            <div class="flex flex-col">
                                                <a
                                                    href="{{ route('incident-management.incidents.show', $incident) }}"
                                                    class="font-semibold text-foreground hover:text-primary transition-colors text-sm"
                                                >
                                                    {{ $incident->title }}
                                                </a>
                                                <span class="text-[11px] text-muted-foreground font-mono">{{ $incident->reference_code }}</span>
                                            </div>
                                        </td>
                                        <td>
                                            <x-metronic.badge :variant="$statusVariant" size="sm">
                                                {{ $incident->status?->name ?: 'Unassigned' }}
                                            </x-metronic.badge>
                                        </td>
                                        <td>
                                            <x-metronic.badge :variant="$priorityVariant" size="sm">
                                                {{ $incident->priority?->name ?: 'Standard' }}
                                            </x-metronic.badge>
                                        </td>
                                        <td class="text-muted-foreground whitespace-nowrap">
                                            {{ $incident->due_at?->format('d/m/Y H:i') ?: '—' }}
                                        </td>
                                        <td class="text-muted-foreground whitespace-nowrap">
                                            {{ $incident->updated_at?->diffForHumans() }}
                                        </td>
                                        <td class="text-right">
                                            <a
                                                href="{{ route('incident-management.incidents.show', $incident) }}"
                                                class="kt-btn kt-btn-outline py-1 px-2.5 text-xs"
                                            >
                                                Manage
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="py-8 text-center text-muted-foreground">
                                            No incidents recorded yet.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </x-metronic.table>
                    </div>
                </x-metronic.card>
            </div>

            <div class="xl:col-span-4 flex flex-col gap-5">
                <x-metronic.card title="Workload Breakdown" subtitle="Distribution by status and clinical severity">
                    <div class="space-y-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-2">By Status</p>
                            <div class="flex flex-wrap gap-2">
                                @forelse($statusBreakdown as $label => $count)
                                    <span class="px-2.5 py-1 rounded-md border border-border bg-muted/30 text-xs font-medium text-foreground flex items-center gap-1.5">
                                        <span>{{ $label }}</span>
                                        <span class="font-bold text-primary">{{ $count }}</span>
                                    </span>
                                @empty
                                    <span class="text-xs text-muted-foreground italic">No status data available.</span>
                                @endforelse
                            </div>
                        </div>

                        <hr class="border-border" />

                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-2">By Severity Level</p>
                            <div class="flex flex-wrap gap-2">
                                @forelse($priorityBreakdown as $label => $count)
                                    <span class="px-2.5 py-1 rounded-md border border-border bg-muted/30 text-xs font-medium text-foreground flex items-center gap-1.5">
                                        <span>{{ $label }}</span>
                                        <span class="font-bold text-foreground">{{ $count }}</span>
                                    </span>
                                @empty
                                    <span class="text-xs text-muted-foreground italic">No priority data available.</span>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </x-metronic.card>

                <div class="rounded-xl border border-border/80 bg-muted/20 p-4 text-xs text-muted-foreground space-y-2">
                    <p class="font-semibold text-foreground flex items-center gap-1.5">
                        <svg class="h-4 w-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Clinical Governance & Sentinel Events
                    </p>
                    <p>Critical incidents require immediate triage within 2 hours and an expedited Root Cause Analysis (RCA) per hospital quality assurance protocols.</p>
                </div>
            </div>
        </div>
    </div>
@endsection
