@extends('layouts.metronic.app')

@section('title', $incident->reference_code . ' - ' . $incident->title)

@section('content')
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

    <div class="grid gap-5">
        {{-- Page Header --}}
        <x-metronic.page-header
            :title="$incident->reference_code . ' — ' . $incident->title"
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Incident Management', 'url' => route('incident-management.index')],
                ['label' => 'Register', 'url' => route('incident-management.incidents.index')],
                ['label' => $incident->reference_code],
            ]"
        >
            <x-slot:actions>
                <div class="flex flex-wrap items-center gap-2">
                    @can('incidents.update')
                        <a href="{{ route('incident-management.incidents.edit', $incident) }}" class="kt-btn kt-btn-outline">
                            Edit Incident
                        </a>
                    @endcan
                    <a href="{{ route('incident-management.incidents.index') }}" class="kt-btn kt-btn-outline">
                        Back to Register
                    </a>
                </div>
            </x-slot:actions>
        </x-metronic.page-header>

        {{-- Incident Meta Ribbon --}}
        <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-3.5 rounded-xl border border-border/70 bg-card text-foreground">
            <div class="flex flex-wrap items-center gap-3">
                <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Status:</span>
                <x-metronic.badge :variant="$statusVariant" size="md">
                    {{ $incident->status?->name ?: 'Unassigned' }}
                </x-metronic.badge>

                <span class="text-border">|</span>
                <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Severity:</span>
                <x-metronic.badge :variant="$priorityVariant" size="md">
                    {{ $incident->priority?->name ?: 'Standard' }}
                </x-metronic.badge>

                @if($incident->category)
                    <span class="text-border">|</span>
                    <span class="text-xs font-medium text-muted-foreground">Category:</span>
                    <span class="text-xs font-semibold text-foreground px-2.5 py-1 rounded-md bg-muted/40">
                        {{ $incident->category->name }}
                    </span>
                @endif
            </div>

            <div class="flex items-center gap-3 text-xs text-muted-foreground">
                <span>Due: <strong class="text-foreground">{{ $incident->due_at?->format('d/m/Y H:i') ?: 'SLA Standard' }}</strong></span>
                <span class="text-border">|</span>
                <span>Ref: <code class="px-1.5 py-0.5 rounded bg-muted font-mono text-[11px] text-foreground">{{ $incident->reference_code }}</code></span>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
            {{-- Main Column: Summary, CAPA Tasks, Comments Thread --}}
            <div class="lg:col-span-8 flex flex-col gap-5">
                {{-- Incident Narrative & Impact --}}
                <x-metronic.card title="Incident Narrative & Clinical Impact">
                    <div class="space-y-4">
                        <div>
                            <h4 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1.5">Occurrence Description</h4>
                            <p class="text-sm leading-relaxed text-foreground whitespace-pre-line">{{ $incident->description }}</p>
                        </div>

                        @if($incident->impact)
                            <div class="p-3.5 rounded-xl border border-border/80 bg-muted/20">
                                <h4 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1">Observed Clinical / Operational Impact</h4>
                                <p class="text-xs leading-relaxed text-foreground">{{ $incident->impact }}</p>
                            </div>
                        @endif

                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-3 border-t border-border">
                            <div>
                                <p class="text-xs text-muted-foreground">Reported By</p>
                                <p class="text-xs font-medium text-foreground mt-0.5">
                                    {{ $incident->reporterUser ? $incident->reporterUser->first_name.' '.$incident->reporterUser->last_name : 'Staff Reporter' }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">Channel</p>
                                <p class="text-xs font-medium text-foreground mt-0.5 uppercase tracking-wide">
                                    {{ $incident->reported_via ?: 'Internal' }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">Logged At</p>
                                <p class="text-xs font-medium text-foreground mt-0.5">
                                    {{ $incident->created_at?->format('d/m/Y H:i') }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">Resolved At</p>
                                <p class="text-xs font-medium text-foreground mt-0.5">
                                    {{ $incident->resolved_at ? $incident->resolved_at->format('d/m/Y H:i') : 'In Progress' }}
                                </p>
                            </div>
                        </div>
                    </div>
                </x-metronic.card>

                {{-- Root Cause Analysis (RCA) & Corrective Action (CAPA) Tasks --}}
                <x-metronic.card
                    title="Corrective & Preventive Actions (CAPA)"
                    subtitle="Root cause mitigation tasks and accountable owners"
                >
                    <div class="space-y-4">
                        <div class="space-y-2.5">
                            @forelse($incident->tasks as $task)
                                @php
                                    $isDone = $task->status === 'completed';
                                @endphp
                                <div class="p-3.5 rounded-xl border {{ $isDone ? 'border-border/60 bg-muted/10 opacity-75' : 'border-border bg-card' }} flex flex-wrap items-center justify-between gap-3">
                                    <div class="flex items-start gap-3 min-w-0">
                                        @can('incidents.tasks.manage')
                                            <form action="{{ route('incident-management.incidents.tasks.toggle', [$incident, $task]) }}" method="POST" class="mt-0.5">
                                                @csrf
                                                <button
                                                    type="submit"
                                                    class="h-5 w-5 rounded border {{ $isDone ? 'bg-primary border-primary text-primary-foreground' : 'border-muted-foreground/50 hover:border-primary' }} flex items-center justify-center transition-colors"
                                                    title="{{ $isDone ? 'Mark as Pending' : 'Mark as Completed' }}"
                                                >
                                                    @if($isDone)
                                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                                        </svg>
                                                    @endif
                                                </button>
                                            </form>
                                        @endcan
                                        <div class="min-w-0">
                                            <p class="text-sm font-semibold text-foreground {{ $isDone ? 'line-through text-muted-foreground' : '' }}">
                                                {{ $task->title }}
                                            </p>
                                            @if($task->description)
                                                <p class="text-xs text-muted-foreground mt-0.5">{{ $task->description }}</p>
                                            @endif
                                            <div class="flex items-center gap-2 mt-1.5 text-[11px] text-muted-foreground">
                                                @if($task->assignedTo)
                                                    <span>Assignee: <strong class="text-foreground">{{ $task->assignedTo->first_name }} {{ $task->assignedTo->last_name }}</strong></span>
                                                    <span>&bull;</span>
                                                @endif
                                                <span>Due: {{ $task->due_at?->format('d/m/Y') ?: 'No deadline' }}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <x-metronic.badge :variant="$isDone ? 'success' : 'outline'" size="sm">
                                        {{ ucfirst($task->status) }}
                                    </x-metronic.badge>
                                </div>
                            @empty
                                <div class="py-6 text-center text-muted-foreground">
                                    <p class="text-xs">No CAPA action tasks recorded yet.</p>
                                </div>
                            @endforelse
                        </div>

                        {{-- Add CAPA Task Form --}}
                        @can('incidents.tasks.manage')
                            <div class="pt-4 border-t border-border">
                                <h4 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-3">Add CAPA Task</h4>
                                <form action="{{ route('incident-management.incidents.tasks.store', $incident) }}" method="POST" class="space-y-3">
                                    @csrf
                                    <div>
                                        <input
                                            type="text"
                                            name="title"
                                            required
                                            placeholder="Action title (e.g. Conduct double-check verification training for ICU nurses)..."
                                            class="kt-input w-full text-foreground text-xs"
                                        />
                                    </div>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        <select name="assigned_to_id" class="kt-select w-full text-foreground text-xs">
                                            <option value="">Assign to staff member...</option>
                                            @foreach($assignees as $assignee)
                                                <option value="{{ $assignee->uuid }}">
                                                    {{ $assignee->displayName() }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <input
                                            type="date"
                                            name="due_at"
                                            class="kt-input w-full text-foreground text-xs"
                                        />
                                    </div>
                                    <div class="flex justify-end">
                                        <button type="submit" class="kt-btn kt-btn-primary py-1.5 px-3 text-xs flex items-center gap-1.5">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                            </svg>
                                            Add Action Task
                                        </button>
                                    </div>
                                </form>
                            </div>
                        @endcan
                    </div>
                </x-metronic.card>

                {{-- Investigation Notes & Audit Findings --}}
                <x-metronic.card
                    title="Investigation Notes & Comments"
                    subtitle="Chronological witness statements, clinical audit notes, and quality deliberations"
                >
                    <div class="space-y-4">
                        <div class="space-y-3">
                            @forelse($incident->comments as $comment)
                                <div class="p-3.5 rounded-xl border border-border bg-muted/20 space-y-1.5">
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="flex items-center gap-2">
                                            <span class="font-semibold text-xs text-foreground">
                                                {{ $comment->user ? $comment->user->first_name.' '.$comment->user->last_name : 'Staff Member' }}
                                            </span>
                                            @if($comment->is_internal)
                                                <x-metronic.badge variant="outline" size="sm">Internal Note</x-metronic.badge>
                                            @endif
                                        </div>
                                        <span class="text-[11px] text-muted-foreground">{{ $comment->created_at?->diffForHumans() }}</span>
                                    </div>
                                    <p class="text-xs text-foreground leading-relaxed whitespace-pre-line">{{ $comment->body }}</p>
                                </div>
                            @empty
                                <div class="py-6 text-center text-muted-foreground">
                                    <p class="text-xs">No investigation notes posted yet.</p>
                                </div>
                            @endforelse
                        </div>

                        {{-- Post Note Form --}}
                        @can('incidents.comments.manage')
                            <div class="pt-4 border-t border-border">
                                <h4 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-2">Post Investigation Note</h4>
                                <form action="{{ route('incident-management.incidents.comments.store', $incident) }}" method="POST" class="space-y-3">
                                    @csrf
                                    <textarea
                                        name="body"
                                        rows="3"
                                        required
                                        placeholder="Record investigation findings, clinician interviews, or remediation discussions..."
                                        class="kt-textarea w-full text-foreground text-xs"
                                    ></textarea>
                                    <div class="flex items-center justify-between">
                                        <label class="flex items-center gap-2 cursor-pointer text-xs text-muted-foreground">
                                            <input type="checkbox" name="is_internal" value="1" checked class="text-primary focus:ring-primary rounded" />
                                            <span>Confidential Internal Investigation Note</span>
                                        </label>
                                        <button type="submit" class="kt-btn kt-btn-primary py-1.5 px-3 text-xs">
                                            Post Note
                                        </button>
                                    </div>
                                </form>
                            </div>
                        @endcan
                    </div>
                </x-metronic.card>
            </div>

            {{-- Right Column: Triage, Assignment, Escalation, Resolution, and Activity Log --}}
            <div class="lg:col-span-4 flex flex-col gap-5">
                {{-- Lead Investigator Card --}}
                <x-metronic.card title="Lead Investigator">
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 rounded-full bg-primary/10 text-primary font-semibold flex items-center justify-center shrink-0">
                            @if($incident->assignedToUser)
                                {{ substr($incident->assignedToUser->first_name, 0, 1) . substr($incident->assignedToUser->last_name, 0, 1) }}
                            @else
                                UN
                            @endif
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-foreground truncate">
                                @if($incident->assignedToUser)
                                    {{ $incident->assignedToUser->first_name }} {{ $incident->assignedToUser->last_name }}
                                @else
                                    Unassigned
                                @endif
                            </p>
                            <p class="text-xs text-muted-foreground truncate">
                                {{ $incident->assignedToUser?->email ?: 'Awaiting triage assignment' }}
                            </p>
                        </div>
                    </div>
                </x-metronic.card>

                {{-- Triage & Workflow Actions --}}
                <x-metronic.card title="Workflow & Remediation" subtitle="Status transitions and investigator controls">
                    <div class="space-y-4">
                        @can('incidents.assign')
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1">
                                    Reassign Investigator
                                </label>
                                <form action="{{ route('incident-management.incidents.assign', $incident) }}" method="POST" class="space-y-2">
                                    @csrf
                                    <select class="kt-select w-full text-xs" name="assigned_to_id" required>
                                        <option value="">Select investigator...</option>
                                        @foreach($assignees as $assignee)
                                            <option value="{{ $assignee->uuid }}" @selected($incident->assigned_to_id === $assignee->uuid)>
                                                {{ $assignee->displayName() }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <input class="kt-input w-full text-xs" name="note" placeholder="Assignment note (optional)" type="text">
                                    <button class="kt-btn kt-btn-outline w-full py-1.5 text-xs justify-center" type="submit">
                                        Update Assignee
                                    </button>
                                </form>
                            </div>
                            <hr class="border-border" />
                        @endcan

                        @can('incidents.escalate')
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1">
                                    Escalate Severity
                                </label>
                                <form action="{{ route('incident-management.incidents.escalate', $incident) }}" method="POST" class="space-y-2">
                                    @csrf
                                    <select class="kt-select w-full text-xs" name="to_priority_id" required>
                                        <option value="">Select target priority...</option>
                                        @foreach($priorities as $priority)
                                            <option value="{{ $priority->id }}" @selected($incident->priority_id === $priority->id)>
                                                {{ $priority->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <textarea class="kt-textarea w-full text-xs" rows="2" name="reason" placeholder="Clinical justification for escalation..."></textarea>
                                    <button class="kt-btn kt-btn-outline text-amber-600 dark:text-amber-400 w-full py-1.5 text-xs justify-center" type="submit">
                                        Escalate Priority
                                    </button>
                                </form>
                            </div>
                            <hr class="border-border" />
                        @endcan

                        @can('incidents.update')
                            <div class="space-y-2.5">
                                @if(!str_contains(mb_strtolower($incident->status?->name ?? ''), 'resolve') && !str_contains(mb_strtolower($incident->status?->name ?? ''), 'close'))
                                    <form action="{{ route('incident-management.incidents.resolve', $incident) }}" method="POST">
                                        @csrf
                                        <button
                                            class="kt-btn kt-btn-success w-full justify-center flex items-center gap-1.5"
                                            type="submit"
                                            onclick="return confirm('Mark this incident as resolved with verified CAPA?')"
                                        >
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                            </svg>
                                            Mark as Resolved
                                        </button>
                                    </form>
                                @endif

                                @if(!str_contains(mb_strtolower($incident->status?->name ?? ''), 'close'))
                                    <form action="{{ route('incident-management.incidents.close', $incident) }}" method="POST">
                                        @csrf
                                        <button
                                            class="kt-btn kt-btn-primary w-full justify-center flex items-center gap-1.5"
                                            type="submit"
                                            onclick="return confirm('Officially close this incident file?')"
                                        >
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            Close Incident
                                        </button>
                                    </form>
                                @endif
                            </div>
                        @endcan
                    </div>
                </x-metronic.card>

                {{-- Activity Audit Timeline --}}
                <x-metronic.card title="Audit & Activity Trail" subtitle="Chronological history of triage and investigation actions">
                    <div class="space-y-3 max-h-[400px] overflow-y-auto pr-1">
                        @forelse($activities as $activity)
                            <div class="flex items-start gap-2.5 text-xs">
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border shrink-0 mt-0.5 bg-muted text-muted-foreground border-border">
                                    {{ str_replace('_', ' ', (string) $activity->event) }}
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-foreground font-medium truncate">
                                        {{ $activity->causer ? $activity->causer->first_name.' '.$activity->causer->last_name : 'System Trigger' }}
                                    </p>
                                    <p class="text-[11px] text-muted-foreground">
                                        {{ $activity->created_at?->format('d/m/Y H:i') }} &bull; {{ $activity->created_at?->diffForHumans() }}
                                    </p>
                                </div>
                            </div>
                        @empty
                            <p class="text-xs text-muted-foreground italic">No activities recorded yet.</p>
                        @endforelse
                    </div>
                </x-metronic.card>
            </div>
        </div>
    </div>
@endsection
