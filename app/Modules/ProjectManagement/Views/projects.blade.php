@extends('layouts.metronic.app')

@section('title', 'Projects Register')

@section('content')
<div class="flex flex-col gap-5">
    {{-- Page Header --}}
    <x-metronic.page-header
        title="Projects Register"
        description="Institutional project directory with stage-gate lifecycle tracking, schedules, and team allocation."
    >
        <x-slot:actions>
            <a href="{{ route('project-management.index') }}" class="kt-btn kt-btn-outline">
                Back to Hub
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

    @if(session('message'))
        <div class="rounded-xl border border-emerald-500/20 bg-emerald-500/10 p-4 text-emerald-800 dark:text-emerald-300 text-sm flex items-center gap-2">
            <svg class="h-5 w-5 shrink-0 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            {{ session('message') }}
        </div>
    @endif

    {{-- Filter Bar & Table Card --}}
    <x-metronic.card title="All Projects ({{ $projects->total() }})" subtitle="Portfolio overview with delivery metrics">
        {{-- Filters Form --}}
        <div class="mb-5 pb-4 border-b border-border">
            <form method="GET" action="{{ route('project-management.projects.index') }}" class="flex flex-wrap items-center gap-3">
                <div class="flex-1 min-w-[220px]">
                    <input
                        type="text"
                        name="search"
                        value="{{ $search }}"
                        placeholder="Search by project name, code, or description…"
                        class="kt-input w-full"
                    />
                </div>
                <div class="w-44">
                    <select name="status" onchange="this.form.submit()" class="kt-select w-full">
                        <option value="">All Stages / Statuses</option>
                        <option value="planned" @selected($status === 'planned')>Planned / Charter</option>
                        <option value="in-progress" @selected($status === 'in-progress')>In Progress</option>
                        <option value="on-hold" @selected($status === 'on-hold')>On Hold</option>
                        <option value="completed" @selected($status === 'completed')>Completed</option>
                        <option value="archived" @selected($status === 'archived')>Archived</option>
                    </select>
                </div>
                <div class="w-36">
                    <select name="priority" onchange="this.form.submit()" class="kt-select w-full">
                        <option value="">All Priorities</option>
                        <option value="critical" @selected($priority === 'critical')>Critical</option>
                        <option value="high" @selected($priority === 'high')>High</option>
                        <option value="medium" @selected($priority === 'medium')>Medium</option>
                        <option value="low" @selected($priority === 'low')>Low</option>
                    </select>
                </div>
                <button type="submit" class="kt-btn kt-btn-primary">Filter</button>
                @if($search !== '' || $status !== '' || $priority !== '')
                    <a href="{{ route('project-management.projects.index') }}" class="kt-btn kt-btn-outline">Clear</a>
                @endif
            </form>
        </div>

        {{-- Table --}}
        <div class="overflow-x-auto kt-scrollable-x-auto">
            <table class="kt-table table-auto kt-table-border w-full text-left">
                <thead>
                    <tr class="text-xs uppercase text-muted-foreground border-b border-border">
                        <th class="py-3 px-3">Project</th>
                        <th class="py-3 px-3">Stage / Status</th>
                        <th class="py-3 px-3">Priority</th>
                        <th class="py-3 px-3">Progress</th>
                        <th class="py-3 px-3">Lead</th>
                        <th class="py-3 px-3">Timeline</th>
                        <th class="py-3 px-3">Budget</th>
                        <th class="py-3 px-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border text-xs">
                    @forelse($projects as $project)
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
                                <p class="text-[10px] text-muted-foreground">{{ $project->members_count }} team members</p>
                            </td>
                            <td class="py-3 px-3 text-muted-foreground whitespace-nowrap">
                                @if($project->start_date && $project->end_date)
                                    {{ $project->start_date->format('d/m/Y') }} – {{ $project->end_date->format('d/m/Y') }}
                                @elseif($project->start_date)
                                    From {{ $project->start_date->format('d/m/Y') }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="py-3 px-3 font-mono text-xs">
                                @if($project->budget_amount !== null)
                                    {{ $project->currency ?: 'USD' }} {{ number_format((float) $project->budget_amount, 2) }}
                                @else
                                    <span class="text-muted-foreground">—</span>
                                @endif
                            </td>
                            <td class="py-3 px-3 text-right">
                                <a href="{{ route('project-management.projects.show', $project->uuid) }}" class="kt-btn kt-btn-outline py-1 px-2.5 text-xs">
                                    Workspace
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-8 text-center text-muted-foreground">
                                No projects matching your filter criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4 pt-3 border-t border-border">
            {{ $projects->links() }}
        </div>
    </x-metronic.card>
</div>
@endsection
