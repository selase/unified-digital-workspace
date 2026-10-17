@extends('layouts.metronic.app')

@section('title', 'Project Management Hub')

@section('content')
<div class="flex flex-col gap-5">
    {{-- Page Header --}}
    <x-metronic.page-header
        title="Project Management & Governance Hub"
        description="Enterprise project chartering, stage-gate governance, work breakdown structures, and portfolio tracking."
    >
        <x-slot:actions>
            <a href="{{ route('project-management.projects.index') }}" class="kt-btn kt-btn-outline">
                Projects Register
            </a>
            <a href="{{ route('project-management.tasks.index') }}" class="kt-btn kt-btn-outline">
                Task Board
            </a>
            @can('projects.create')
                <a href="{{ route('project-management.projects.create') }}" class="kt-btn kt-btn-primary flex items-center gap-1.5">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    New Project Charter
                </a>
            @endcan
        </x-slot:actions>
    </x-metronic.page-header>

    {{-- Top Metrics Row: 5 Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        <div class="rounded-xl border border-border bg-card p-4 flex flex-col justify-between">
            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Total Projects</span>
            <div class="mt-2 flex items-baseline justify-between">
                <span class="text-2xl font-bold text-foreground">{{ $totalProjects }}</span>
                <span class="text-[11px] text-muted-foreground">All portfolios</span>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-card p-4 flex flex-col justify-between">
            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">In Execution</span>
            <div class="mt-2 flex items-baseline justify-between">
                <span class="text-2xl font-bold text-primary">{{ $activeProjects }}</span>
                <span class="text-[11px] text-muted-foreground">Active delivery</span>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-card p-4 flex flex-col justify-between">
            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Charter / Planned</span>
            <div class="mt-2 flex items-baseline justify-between">
                <span class="text-2xl font-bold text-amber-600 dark:text-amber-400">{{ $plannedProjects }}</span>
                <span class="text-[11px] text-muted-foreground">Pre-execution</span>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-card p-4 flex flex-col justify-between">
            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Work Breakdown</span>
            <div class="mt-2 flex items-baseline justify-between">
                <span class="text-2xl font-bold text-foreground">{{ $totalTasks }}</span>
                <span class="text-[11px] text-muted-foreground">{{ $completedTasks }} completed</span>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-card p-4 flex flex-col justify-between">
            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Overdue Tasks</span>
            <div class="mt-2 flex items-baseline justify-between">
                <span class="text-2xl font-bold {{ $overdueTasks > 0 ? 'text-destructive' : 'text-foreground' }}">
                    {{ $overdueTasks }}
                </span>
                <span class="text-[11px] text-muted-foreground">Action required</span>
            </div>
        </div>
    </div>

    {{-- Main Grid: Left Portfolio Register, Right Milestones & Governance --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
        {{-- Left 8 cols: Recent Projects --}}
        <div class="lg:col-span-8 flex flex-col gap-5">
            <x-metronic.card
                title="Active Projects & Strategic Initiatives"
                subtitle="Monitored institutional deliverables, stage gates, and completion progress"
            >
                <div class="overflow-x-auto">
                    <table class="kt-table table-auto kt-table-border w-full text-left">
                        <thead>
                            <tr class="text-xs uppercase text-muted-foreground border-b border-border">
                                <th class="py-3 px-3">Project</th>
                                <th class="py-3 px-3">Stage / Status</th>
                                <th class="py-3 px-3">Priority</th>
                                <th class="py-3 px-3">Progress</th>
                                <th class="py-3 px-3">Lead / Owner</th>
                                <th class="py-3 px-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border text-xs">
                            @forelse($recentProjects as $project)
                                @php
                                    $projectTotal = $project->tasks_count;
                                    $projectCompleted = $project->completed_tasks_count;
                                    $percent = $projectTotal > 0 ? (int) round(($projectCompleted / $projectTotal) * 100) : 0;

                                    $statusVariant = match($project->status) {
                                        'in-progress' => 'primary',
                                        'completed' => 'success',
                                        'on-hold' => 'warning',
                                        'archived' => 'outline',
                                        default => 'muted',
                                    };

                                    $priorityVariant = match($project->priority) {
                                        'critical' => 'destructive',
                                        'high' => 'warning',
                                        'medium' => 'primary',
                                        default => 'muted',
                                    };
                                @endphp
                                <tr class="hover:bg-muted/30 transition">
                                    <td class="py-3 px-3">
                                        <a href="{{ route('project-management.projects.show', $project->uuid) }}" class="font-semibold text-foreground hover:text-primary transition">
                                            {{ $project->name }}
                                        </a>
                                        <p class="text-[11px] text-muted-foreground font-mono">{{ $project->slug }}</p>
                                    </td>
                                    <td class="py-3 px-3">
                                        <x-metronic.badge :variant="$statusVariant" size="sm">
                                            {{ ucfirst(str_replace('-', ' ', $project->status)) }}
                                        </x-metronic.badge>
                                    </td>
                                    <td class="py-3 px-3">
                                        <x-metronic.badge :variant="$priorityVariant" size="sm">
                                            {{ ucfirst($project->priority) }}
                                        </x-metronic.badge>
                                    </td>
                                    <td class="py-3 px-3 min-w-[130px]">
                                        <div class="flex items-center gap-2">
                                            <div class="w-full bg-muted rounded-full h-1.5 overflow-hidden">
                                                <div class="bg-primary h-1.5 rounded-full" style="width: {{ $percent }}%"></div>
                                            </div>
                                            <span class="text-[11px] font-medium text-foreground w-8 text-right">{{ $percent }}%</span>
                                        </div>
                                        <p class="text-[10px] text-muted-foreground">{{ $projectCompleted }} of {{ $projectTotal }} tasks</p>
                                    </td>
                                    <td class="py-3 px-3">
                                        <span class="text-xs font-medium text-foreground">
                                            {{ $project->owner ? $project->owner->first_name . ' ' . $project->owner->last_name : 'Unassigned' }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-3 text-right">
                                        <a href="{{ route('project-management.projects.show', $project->uuid) }}" class="kt-btn kt-btn-outline py-1 px-2.5 text-xs">
                                            Workspace
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-8 text-center text-muted-foreground">
                                        No active projects found. Initialize a project charter to get started.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 pt-3 border-t border-border flex justify-end">
                    <a href="{{ route('project-management.projects.index') }}" class="text-xs font-semibold text-primary hover:underline">
                        View All Projects in Register &rarr;
                    </a>
                </div>
            </x-metronic.card>
        </div>

        {{-- Right 4 cols: Milestones & Project Governance --}}
        <div class="lg:col-span-4 flex flex-col gap-5">
            {{-- Upcoming Milestones --}}
            <x-metronic.card title="Upcoming Milestones" subtitle="Target dates for stage deliverables">
                <div class="space-y-3">
                    @forelse($upcomingMilestones as $milestone)
                        <div class="p-3 rounded-xl border border-border bg-card space-y-1">
                            <div class="flex items-center justify-between">
                                <span class="font-semibold text-xs text-foreground truncate">
                                    {{ $milestone->name }}
                                </span>
                                @if($milestone->due_date)
                                    <span class="text-[11px] text-muted-foreground font-mono">
                                        {{ $milestone->due_date->format('d/m/Y') }}
                                    </span>
                                @endif
                            </div>
                            <p class="text-[11px] text-muted-foreground truncate">
                                Project: <a href="{{ route('project-management.projects.show', $milestone->project->uuid) }}" class="text-primary hover:underline font-medium">{{ $milestone->project->name }}</a>
                            </p>
                        </div>
                    @empty
                        <div class="py-6 text-center text-muted-foreground">
                            <svg class="h-8 w-8 mx-auto text-muted-foreground/50 mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <p class="text-xs">No pending milestones scheduled.</p>
                        </div>
                    @endforelse
                </div>
                <div class="mt-3 pt-2 border-t border-border text-right">
                    <a href="{{ route('project-management.tasks.index') }}" class="text-xs font-semibold text-primary hover:underline">
                        Task Board &rarr;
                    </a>
                </div>
            </x-metronic.card>

            {{-- Institutional Stage-Gate Callout --}}
            <div class="rounded-xl border border-border bg-card p-5 space-y-3">
                <div class="flex items-center gap-2 text-primary font-semibold text-xs uppercase tracking-wider">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                    Stage-Gate Project Governance
                </div>
                <p class="text-xs text-muted-foreground leading-relaxed">
                    Institutional governance requires formal transition through Chartering, Execution, Gate Reviews, and Closure. Milestones and task breakdowns feed real-time organizational KPIs.
                </p>
                <div class="pt-2 border-t border-border flex items-center justify-between text-xs text-muted-foreground">
                    <span>Active Portfolios</span>
                    <span class="font-semibold text-foreground">${{ number_format($totalBudget, 2) }}</span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
