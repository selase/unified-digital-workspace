@extends('layouts.metronic.app')

@section('title', 'Project Deliverables & Task Board')

@section('content')
<div class="flex flex-col gap-5">
    {{-- Page Header --}}
    <x-metronic.page-header
        title="Project Deliverables & Task Board"
        description="Cross-portfolio task tracking, deliverable lifecycle states, and resource assignments."
    >
        <x-slot:actions>
            <a href="{{ route('project-management.index') }}" class="kt-btn kt-btn-outline">
                Back to Hub
            </a>
            <a href="{{ route('project-management.projects.index') }}" class="kt-btn kt-btn-outline">
                Projects Register
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
    <x-metronic.card title="All Project Deliverables ({{ $tasks->total() }})" subtitle="Filter and monitor work breakdown items">
        <div class="mb-5 pb-4 border-b border-border">
            <form method="GET" action="{{ route('project-management.tasks.index') }}" class="flex flex-wrap items-center gap-3">
                <div class="flex-1 min-w-[220px]">
                    <input
                        type="text"
                        name="search"
                        value="{{ $search }}"
                        placeholder="Search deliverables by title…"
                        class="kt-input w-full"
                    />
                </div>
                <div class="w-44">
                    <select name="status" onchange="this.form.submit()" class="kt-select w-full">
                        <option value="">All Statuses</option>
                        <option value="todo" @selected($status === 'todo')>Todo</option>
                        <option value="in-progress" @selected($status === 'in-progress')>In Progress</option>
                        <option value="review" @selected($status === 'review')>Review</option>
                        <option value="blocked" @selected($status === 'blocked')>Blocked</option>
                        <option value="done" @selected($status === 'done')>Done</option>
                    </select>
                </div>
                <button type="submit" class="kt-btn kt-btn-primary">Filter</button>
                @if($search !== '' || $status !== '')
                    <a href="{{ route('project-management.tasks.index') }}" class="kt-btn kt-btn-outline">Clear</a>
                @endif
            </form>
        </div>

        {{-- Tasks Table --}}
        <div class="overflow-x-auto kt-scrollable-x-auto">
            <table class="kt-table table-auto kt-table-border w-full text-left">
                <thead>
                    <tr class="text-xs uppercase text-muted-foreground border-b border-border">
                        <th class="py-3 px-3">Deliverable / Task</th>
                        <th class="py-3 px-3">Project</th>
                        <th class="py-3 px-3">Milestone</th>
                        <th class="py-3 px-3">Status</th>
                        <th class="py-3 px-3">Priority</th>
                        <th class="py-3 px-3">Assignee</th>
                        <th class="py-3 px-3">Due Date</th>
                        <th class="py-3 px-3 text-right">Quick Transition</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border text-xs">
                    @forelse($tasks as $task)
                        @php
                            $taskStatusVariant = match($task->status) {
                                'done' => 'success',
                                'in-progress' => 'primary',
                                'blocked' => 'destructive',
                                'review' => 'warning',
                                default => 'outline',
                            };
                            $taskPriorityVariant = match($task->priority) {
                                'critical' => 'destructive',
                                'high' => 'warning',
                                'medium' => 'primary',
                                default => 'muted',
                            };
                            $assignee = $task->assignments->first()?->user;
                        @endphp
                        <tr class="hover:bg-muted/30 transition">
                            <td class="py-3 px-3 font-semibold text-foreground">
                                {{ $task->title }}
                            </td>
                            <td class="py-3 px-3">
                                @if($task->project)
                                    <a href="{{ route('project-management.projects.show', $task->project->uuid) }}" class="text-primary hover:underline font-medium">
                                        {{ $task->project->name }}
                                    </a>
                                @else
                                    <span class="text-muted-foreground italic">Unlinked</span>
                                @endif
                            </td>
                            <td class="py-3 px-3 text-muted-foreground">
                                {{ $task->milestone?->name ?: '—' }}
                            </td>
                            <td class="py-3 px-3">
                                <x-metronic.badge :variant="$taskStatusVariant" size="sm">
                                    {{ ucfirst(str_replace('-', ' ', $task->status)) }}
                                </x-metronic.badge>
                            </td>
                            <td class="py-3 px-3">
                                <x-metronic.badge :variant="$taskPriorityVariant" size="sm">
                                    {{ ucfirst($task->priority) }}
                                </x-metronic.badge>
                            </td>
                            <td class="py-3 px-3">
                                @if($assignee)
                                    <span class="text-xs font-medium text-foreground">
                                        {{ $assignee->first_name }} {{ $assignee->last_name }}
                                    </span>
                                @else
                                    <span class="text-muted-foreground italic">Unassigned</span>
                                @endif
                            </td>
                            <td class="py-3 px-3 font-mono text-[11px] text-muted-foreground">
                                {{ $task->due_date?->format('d/m/Y') ?: '—' }}
                            </td>
                            <td class="py-3 px-3 text-right">
                                <form method="POST" action="{{ route('project-management.tasks.status', $task->uuid) }}" class="inline-flex items-center gap-1">
                                    @csrf
                                    <select name="status" onchange="this.form.submit()" class="kt-select text-xs py-0.5 px-1.5 h-7">
                                        <option value="todo" @selected($task->status === 'todo')>Todo</option>
                                        <option value="in-progress" @selected($task->status === 'in-progress')>In Progress</option>
                                        <option value="review" @selected($task->status === 'review')>Review</option>
                                        <option value="blocked" @selected($task->status === 'blocked')>Blocked</option>
                                        <option value="done" @selected($task->status === 'done')>Done</option>
                                    </select>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-8 text-center text-muted-foreground">
                                No deliverables matching your query.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4 pt-3 border-t border-border">
            {{ $tasks->links() }}
        </div>
    </x-metronic.card>
</div>
@endsection
