@extends('layouts.metronic.app')

@section('title', $project->name . ' - Project Workspace')

@section('content')
<div class="flex flex-col gap-5">
    {{-- Page Header --}}
    <x-metronic.page-header
        :title="$project->name"
        :description="'Project Code: ' . $project->slug . ' • Managed under institutional stage-gate governance.'"
    >
        <x-slot:actions>
            <a href="{{ route('project-management.projects.index') }}" class="kt-btn kt-btn-outline">
                &larr; Projects Register
            </a>
            @php
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
            <x-metronic.badge :variant="$statusVariant" size="md">
                Stage: {{ ucfirst(str_replace('-', ' ', $project->status)) }}
            </x-metronic.badge>
            <x-metronic.badge :variant="$priorityVariant" size="md">
                Priority: {{ ucfirst($project->priority) }}
            </x-metronic.badge>
        </x-slot:actions>
    </x-metronic.page-header>

    {{-- Flash Notifications --}}
    @if(session('message'))
        <div class="rounded-xl border border-emerald-500/20 bg-emerald-500/10 p-4 text-emerald-800 dark:text-emerald-300 text-sm flex items-center gap-2">
            <svg class="h-5 w-5 shrink-0 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            {{ session('message') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="rounded-xl border border-destructive/20 bg-destructive/10 p-4 text-destructive text-sm space-y-1">
            <p class="font-semibold">Action could not be completed:</p>
            <ul class="list-disc list-inside text-xs">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Project KPI Overview: 4 Metric Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Completion Progress --}}
        <div class="rounded-xl border border-border bg-card p-4 flex flex-col justify-between">
            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Deliverable Progress</span>
            <div class="mt-2">
                <div class="flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-foreground">{{ $progressPercent }}%</span>
                    <span class="text-[11px] text-muted-foreground">{{ $completedTasks }} / {{ $totalTasks }} Tasks</span>
                </div>
                <div class="w-full bg-muted rounded-full h-2 mt-2 overflow-hidden">
                    <div class="bg-primary h-2 rounded-full transition-all duration-300" style="width: {{ $progressPercent }}%"></div>
                </div>
            </div>
        </div>

        {{-- Stage-Gate Milestones --}}
        <div class="rounded-xl border border-border bg-card p-4 flex flex-col justify-between">
            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Gate Milestones</span>
            <div class="mt-2 flex items-baseline justify-between">
                <span class="text-2xl font-bold text-foreground">{{ $completedMilestones }} <span class="text-sm font-normal text-muted-foreground">/ {{ $totalMilestones }}</span></span>
                <span class="text-[11px] text-muted-foreground">Key checkpoints</span>
            </div>
        </div>

        {{-- Target Schedule --}}
        <div class="rounded-xl border border-border bg-card p-4 flex flex-col justify-between">
            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Target Timeline</span>
            <div class="mt-2">
                <p class="text-sm font-semibold text-foreground">
                    @if($project->start_date && $project->end_date)
                        {{ $project->start_date->format('d/m/Y') }} – {{ $project->end_date->format('d/m/Y') }}
                    @elseif($project->start_date)
                        Started {{ $project->start_date->format('d/m/Y') }}
                    @else
                        Schedule uncommitted
                    @endif
                </p>
                <p class="text-[11px] text-muted-foreground mt-0.5">
                    @if($project->completed_at)
                        Completed on {{ $project->completed_at->format('d/m/Y') }}
                    @elseif($project->end_date)
                        Target due in {{ now()->diffInDays($project->end_date, false) }} days
                    @else
                        Active lifecycle
                    @endif
                </p>
            </div>
        </div>

        {{-- Financial Allocation --}}
        <div class="rounded-xl border border-border bg-card p-4 flex flex-col justify-between">
            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Financial Budget</span>
            <div class="mt-2 flex items-baseline justify-between">
                <span class="text-2xl font-bold text-foreground">
                    @if($project->budget_amount !== null)
                        <span class="text-xs font-normal text-muted-foreground">{{ $project->currency ?: 'USD' }}</span>
                        {{ number_format((float) $project->budget_amount, 2) }}
                    @else
                        <span class="text-muted-foreground text-sm">Unfunded</span>
                    @endif
                </span>
                <span class="text-[11px] text-muted-foreground">Capital allocation</span>
            </div>
        </div>
    </div>

    {{-- Main Workspace Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
        {{-- Left 8 cols: Milestones, Work Breakdown (Tasks), and Charter Scope --}}
        <div class="lg:col-span-8 flex flex-col gap-5">
            {{-- Milestones & Stage Gates Card --}}
            <x-metronic.card
                title="Stage-Gate Milestones ({{ $project->milestones->count() }})"
                subtitle="Formal phase gates and governance approval checkpoints"
            >
                <div class="space-y-3">
                    @forelse($project->milestones as $milestone)
                        <div class="p-3 rounded-xl border border-border bg-card flex flex-wrap items-center justify-between gap-3 hover:border-primary/30 transition">
                            <div class="flex items-start gap-3">
                                <form method="POST" action="{{ route('project-management.projects.milestones.toggle', ['project' => $project->uuid, 'milestone' => $milestone->id]) }}">
                                    @csrf
                                    <button
                                        type="submit"
                                        title="{{ $milestone->completed_at ? 'Mark as Pending' : 'Mark as Completed' }}"
                                        class="mt-0.5 h-5 w-5 rounded border {{ $milestone->completed_at ? 'bg-emerald-600 border-emerald-600 text-white' : 'border-input hover:border-primary' }} flex items-center justify-center transition"
                                    >
                                        @if($milestone->completed_at)
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                            </svg>
                                        @endif
                                    </button>
                                </form>
                                <div>
                                    <p class="text-xs font-semibold {{ $milestone->completed_at ? 'line-through text-muted-foreground' : 'text-foreground' }}">
                                        {{ $milestone->name }}
                                    </p>
                                    @if($milestone->description)
                                        <p class="text-[11px] text-muted-foreground mt-0.5">{{ $milestone->description }}</p>
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-center gap-3 text-xs">
                                @if($milestone->due_date)
                                    <span class="text-[11px] text-muted-foreground font-mono">
                                        Target: {{ $milestone->due_date->format('d/m/Y') }}
                                    </span>
                                @endif
                                <span class="text-[11px] px-2 py-0.5 rounded-full bg-muted text-muted-foreground">
                                    {{ $milestone->tasks_count }} tasks
                                </span>
                                @if($milestone->completed_at)
                                    <x-metronic.badge variant="success" size="sm">Completed</x-metronic.badge>
                                @else
                                    <x-metronic.badge variant="outline" size="sm">Pending Gate</x-metronic.badge>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="py-6 text-center text-muted-foreground">
                            <p class="text-xs">No stage-gate milestones defined yet.</p>
                        </div>
                    @endforelse
                </div>

                {{-- Add Milestone Accordion / Form --}}
                @can('projects.update')
                    <div class="mt-4 pt-3 border-t border-border">
                        <details class="group">
                            <summary class="cursor-pointer text-xs font-semibold text-primary hover:underline flex items-center gap-1.5 list-none">
                                <svg class="h-3.5 w-3.5 transition group-open:rotate-90" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                                Add Stage-Gate Milestone
                            </summary>
                            <form method="POST" action="{{ route('project-management.projects.milestones.store', $project->uuid) }}" class="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-3 p-3 bg-muted/20 rounded-xl border border-border">
                                @csrf
                                <div class="sm:col-span-2">
                                    <label class="block text-[11px] font-semibold text-foreground mb-1">Milestone Name *</label>
                                    <input type="text" name="name" required placeholder="e.g. Gate 2: Technical Architecture Sign-off" class="kt-input w-full" />
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-foreground mb-1">Target Date</label>
                                    <input type="date" name="due_date" class="kt-input w-full" />
                                </div>
                                <div class="sm:col-span-3">
                                    <label class="block text-[11px] font-semibold text-foreground mb-1">Gate Description / Criteria</label>
                                    <input type="text" name="description" placeholder="Requirements necessary to pass this stage gate…" class="kt-input w-full" />
                                </div>
                                <div class="sm:col-span-3 flex justify-end">
                                    <button type="submit" class="kt-btn kt-btn-sm kt-btn-primary">
                                        Save Milestone
                                    </button>
                                </div>
                            </form>
                        </details>
                    </div>
                @endcan
            </x-metronic.card>

            {{-- Work Breakdown Structure / Tasks Card --}}
            <x-metronic.card
                title="Work Breakdown Structure & Deliverables ({{ $project->tasks->count() }})"
                subtitle="Executable deliverables, assignments, and status transitions"
            >
                <div class="overflow-x-auto">
                    <table class="kt-table table-auto kt-table-border w-full text-left">
                        <thead>
                            <tr class="text-xs uppercase text-muted-foreground border-b border-border">
                                <th class="py-3 px-3">Deliverable / Task</th>
                                <th class="py-3 px-3">Milestone</th>
                                <th class="py-3 px-3">Status</th>
                                <th class="py-3 px-3">Priority</th>
                                <th class="py-3 px-3">Assignee</th>
                                <th class="py-3 px-3">Due Date</th>
                                <th class="py-3 px-3 text-right">Transition</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border text-xs">
                            @forelse($project->tasks as $task)
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
                                    $assignedUser = $task->assignments->first()?->user;
                                @endphp
                                <tr class="hover:bg-muted/30 transition">
                                    <td class="py-3 px-3">
                                        <p class="font-semibold text-foreground">{{ $task->title }}</p>
                                        @if($task->estimated_minutes)
                                            <p class="text-[10px] text-muted-foreground">Est: {{ round($task->estimated_minutes / 60, 1) }}h</p>
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
                                        @if($assignedUser)
                                            <span class="text-xs font-medium text-foreground">
                                                {{ $assignedUser->first_name }} {{ $assignedUser->last_name }}
                                            </span>
                                        @else
                                            <span class="text-muted-foreground italic">Unassigned</span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-3 font-mono text-[11px] text-muted-foreground">
                                        {{ $task->due_date?->format('d/m/Y') ?: '—' }}
                                    </td>
                                    <td class="py-3 px-3 text-right">
                                        <form method="POST" action="{{ route('project-management.projects.tasks.status', ['project' => $project->uuid, 'task' => $task->uuid]) }}" class="inline-flex items-center gap-1">
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
                                    <td colspan="7" class="py-8 text-center text-muted-foreground">
                                        No deliverables assigned yet. Add tasks below to build the work breakdown.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Add Task Accordion / Form --}}
                @can('projects.update')
                    <div class="mt-4 pt-3 border-t border-border">
                        <details class="group">
                            <summary class="cursor-pointer text-xs font-semibold text-primary hover:underline flex items-center gap-1.5 list-none">
                                <svg class="h-3.5 w-3.5 transition group-open:rotate-90" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                                Add Deliverable / Task
                            </summary>
                            <form method="POST" action="{{ route('project-management.projects.tasks.store', $project->uuid) }}" class="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-3 p-3 bg-muted/20 rounded-xl border border-border">
                                @csrf
                                <div class="sm:col-span-2">
                                    <label class="block text-[11px] font-semibold text-foreground mb-1">Deliverable Title *</label>
                                    <input type="text" name="title" required placeholder="e.g. Configure HL7 FHIR Gateway endpoints" class="kt-input w-full" />
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-foreground mb-1">Linked Milestone</label>
                                    <select name="milestone_id" class="kt-select w-full">
                                        <option value="">No Milestone Linked</option>
                                        @foreach($project->milestones as $ms)
                                            <option value="{{ $ms->id }}">{{ $ms->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-foreground mb-1">Priority</label>
                                    <select name="priority" class="kt-select w-full">
                                        <option value="medium">Medium Priority</option>
                                        <option value="high">High Priority</option>
                                        <option value="critical">Critical</option>
                                        <option value="low">Low Priority</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-foreground mb-1">Assign Deliverable To</label>
                                    <select name="assigned_to" class="kt-select w-full">
                                        <option value="">Unassigned</option>
                                        @foreach($users as $u)
                                            <option value="{{ $u->uuid }}">{{ $u->first_name }} {{ $u->last_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-foreground mb-1">Target Due Date</label>
                                    <input type="date" name="due_date" class="kt-input w-full" />
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-foreground mb-1">Est. Effort (Hours)</label>
                                    <input type="number" step="0.5" min="0" name="estimated_hours" placeholder="e.g. 16" class="kt-input w-full" />
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="block text-[11px] font-semibold text-foreground mb-1">Specification / Deliverable Scope</label>
                                    <input type="text" name="description" placeholder="Acceptance criteria and deliverables…" class="kt-input w-full" />
                                </div>
                                <div class="sm:col-span-3 flex justify-end">
                                    <button type="submit" class="kt-btn kt-btn-sm kt-btn-primary">
                                        Create Deliverable
                                    </button>
                                </div>
                            </form>
                        </details>
                    </div>
                @endcan
            </x-metronic.card>

            {{-- Project Charter & Scope Statement Card --}}
            <x-metronic.card title="Charter Objectives & Governance History" subtitle="Formal business case and stage-gate transition audit log">
                <div class="prose dark:prose-invert max-w-none text-xs text-foreground leading-relaxed whitespace-pre-wrap bg-muted/20 p-4 rounded-xl border border-border">
                    {{ $project->description ?: 'No formal charter narrative recorded for this project.' }}
                </div>

                {{-- Stage Gate Review History --}}
                @php
                    $stageHistory = $project->metadata['stage_gate_history'] ?? [];
                @endphp
                @if(count($stageHistory) > 0)
                    <div class="mt-4 pt-3 border-t border-border space-y-2">
                        <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Governance Audit Trail</p>
                        <div class="space-y-2">
                            @foreach(array_reverse($stageHistory) as $log)
                                <div class="p-2.5 rounded-lg bg-muted/30 border border-border text-xs flex items-center justify-between">
                                    <div>
                                        <span class="font-medium text-foreground">
                                            Stage transitioned: <span class="capitalize">{{ $log['from_status'] ?? '—' }}</span> &rarr; <span class="font-semibold text-primary capitalize">{{ $log['to_status'] ?? '—' }}</span>
                                        </span>
                                        @if(!empty($log['note']))
                                            <p class="text-[11px] text-muted-foreground mt-0.5">"{{ $log['note'] }}"</p>
                                        @endif
                                    </div>
                                    <span class="text-[10px] text-muted-foreground font-mono">
                                        {{ isset($log['timestamp']) ? \Carbon\Carbon::parse($log['timestamp'])->diffForHumans() : '—' }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </x-metronic.card>
        </div>

        {{-- Right 4 cols: Stage Gate Governance, Team Members, and Financials --}}
        <div class="lg:col-span-4 flex flex-col gap-5">
            {{-- Stage-Gate Governance Review Card --}}
            <x-metronic.card title="Stage-Gate Review" subtitle="Promote or update lifecycle state">
                <form method="POST" action="{{ route('project-management.projects.stage-gate', $project->uuid) }}" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold text-foreground mb-1">Target Lifecycle Stage</label>
                        <select name="status" class="kt-select w-full">
                            <option value="planned" @selected($project->status === 'planned')>Planned / Charter Phase</option>
                            <option value="in-progress" @selected($project->status === 'in-progress')>Active Execution</option>
                            <option value="on-hold" @selected($project->status === 'on-hold')>On Hold / Pending Steering</option>
                            <option value="completed" @selected($project->status === 'completed')>Completed / Gate Passed</option>
                            <option value="archived" @selected($project->status === 'archived')>Archived / Closed</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-foreground mb-1">Gate Reviewer Note</label>
                        <textarea
                            name="stage_note"
                            rows="2"
                            placeholder="Enter steering committee decision note or justification…"
                            class="kt-input w-full text-xs"
                        ></textarea>
                    </div>

                    <button type="submit" class="kt-btn kt-btn-primary w-full text-xs flex items-center justify-center gap-1.5">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Execute Stage-Gate Transition
                    </button>
                </form>
            </x-metronic.card>

            {{-- Project Leadership & Team Card --}}
            <x-metronic.card title="Project Team ({{ $project->members->count() }})" subtitle="Accountable director and contributors">
                <div class="space-y-3">
                    {{-- Owner / Project Lead --}}
                    <div class="p-3 rounded-xl border border-primary/20 bg-primary/5 flex items-center justify-between">
                        <div>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-primary">Executive Sponsor / Lead</span>
                            <p class="text-xs font-semibold text-foreground mt-0.5">
                                {{ $project->owner ? $project->owner->first_name . ' ' . $project->owner->last_name : 'Unassigned' }}
                            </p>
                            @if($project->owner)
                                <p class="text-[11px] text-muted-foreground">{{ $project->owner->email }}</p>
                            @endif
                        </div>
                    </div>

                    {{-- Members List --}}
                    @foreach($project->members as $member)
                        @if($member->user && (!$project->owner || $member->user->uuid !== $project->owner->uuid))
                            <div class="p-2.5 rounded-lg border border-border bg-card flex items-center justify-between text-xs">
                                <div>
                                    <p class="font-medium text-foreground">{{ $member->user->first_name }} {{ $member->user->last_name }}</p>
                                    <p class="text-[10px] text-muted-foreground">{{ $member->role ?: 'Contributor' }}</p>
                                </div>
                                <span class="text-[10px] text-muted-foreground font-mono">
                                    {{ $member->joined_at?->format('M Y') }}
                                </span>
                            </div>
                        @endif
                    @endforeach
                </div>

                {{-- Add Member Form --}}
                @can('projects.update')
                    <div class="mt-4 pt-3 border-t border-border">
                        <details class="group">
                            <summary class="cursor-pointer text-xs font-semibold text-primary hover:underline flex items-center gap-1.5 list-none">
                                <svg class="h-3.5 w-3.5 transition group-open:rotate-90" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                                Add Team Member
                            </summary>
                            <form method="POST" action="{{ route('project-management.projects.members.store', $project->uuid) }}" class="mt-3 space-y-2 p-3 bg-muted/20 rounded-xl border border-border">
                                @csrf
                                <div>
                                    <label class="block text-[11px] font-semibold text-foreground mb-1">Select User</label>
                                    <select name="user_id" required class="kt-select w-full text-xs">
                                        @foreach($users as $user)
                                            <option value="{{ $user->uuid }}">{{ $user->first_name }} {{ $user->last_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-foreground mb-1">Role / Designation</label>
                                    <input type="text" name="role" placeholder="e.g. Lead Architect, QA Analyst" class="kt-input w-full text-xs" />
                                </div>
                                <button type="submit" class="kt-btn kt-btn-sm kt-btn-primary w-full">
                                    Add Member
                                </button>
                            </form>
                        </details>
                    </div>
                @endcan
            </x-metronic.card>
        </div>
    </div>
</div>
@endsection

