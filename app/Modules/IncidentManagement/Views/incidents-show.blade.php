@extends('layouts.metronic.app')

@section('title', 'Incident Detail')

@section('content')
    <section class="grid gap-6">
        <div class="kt-card p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Incident Management</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">{{ $incident->reference_code }}</h1>
                    <p class="mt-2 text-sm text-muted-foreground">{{ $incident->title }}</p>
                </div>
                <div class="flex items-center gap-2">
                    @can('incidents.update')
                        <a class="kt-btn kt-btn-outline" href="{{ route('incident-management.incidents.edit', $incident) }}">Edit</a>
                    @endcan
                    <a class="kt-btn kt-btn-outline" href="{{ route('incident-management.incidents.index') }}">Back to Register</a>
                </div>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-12">
            <div class="kt-card p-6 xl:col-span-8">
                <h2 class="text-lg font-semibold text-foreground">Incident Summary</h2>
                <p class="mt-4 whitespace-pre-wrap text-sm text-foreground">{{ $incident->description }}</p>

                <div class="mt-6 grid gap-4 md:grid-cols-2">
                    <div class="rounded-lg bg-muted/30 p-4">
                        <p class="text-xs uppercase text-muted-foreground">Status</p>
                        <p class="mt-2 text-sm text-foreground">{{ $incident->status?->name ?: 'Unassigned' }}</p>
                    </div>
                    <div class="rounded-lg bg-muted/30 p-4">
                        <p class="text-xs uppercase text-muted-foreground">Priority</p>
                        <p class="mt-2 text-sm text-foreground">{{ $incident->priority?->name ?: 'Unassigned' }}</p>
                    </div>
                    <div class="rounded-lg bg-muted/30 p-4">
                        <p class="text-xs uppercase text-muted-foreground">Category</p>
                        <p class="mt-2 text-sm text-foreground">{{ $incident->category?->name ?: 'Unassigned' }}</p>
                    </div>
                    <div class="rounded-lg bg-muted/30 p-4">
                        <p class="text-xs uppercase text-muted-foreground">Due At</p>
                        <p class="mt-2 text-sm text-foreground">{{ $incident->due_at?->format('M d, Y H:i') ?: 'Not set' }}</p>
                    </div>
                    <div class="rounded-lg bg-muted/30 p-4">
                        <p class="text-xs uppercase text-muted-foreground">Reporter</p>
                        <p class="mt-2 text-sm text-foreground">{{ $incident->reporterUser?->displayName() ?: 'System Reporter' }}</p>
                    </div>
                    <div class="rounded-lg bg-muted/30 p-4">
                        <p class="text-xs uppercase text-muted-foreground">Assigned To</p>
                        <p class="mt-2 text-sm text-foreground">
                            @php
                                $assignedUser = $assignees->firstWhere('uuid', $incident->assigned_to_id);
                            @endphp
                            {{ $assignedUser?->displayName() ?: 'Unassigned' }}
                        </p>
                    </div>
                </div>

                <div class="mt-6 grid gap-6 lg:grid-cols-2">
                    <div class="rounded-lg bg-muted/30 p-4">
                        <h3 class="text-sm font-semibold text-foreground">Tasks ({{ $incident->tasks->count() }})</h3>
                        <ul class="mt-3 space-y-2 text-sm text-foreground">
                            @forelse($incident->tasks as $task)
                                <li class="rounded-md bg-muted/20 px-3 py-2">
                                    <p class="font-medium">{{ $task->title }}</p>
                                    <p class="text-xs text-muted-foreground">{{ ucfirst($task->status) }} · {{ $task->due_at?->format('M d, Y') ?: 'No due date' }}</p>
                                </li>
                            @empty
                                <li class="text-sm text-muted-foreground">No tasks recorded yet.</li>
                            @endforelse
                        </ul>
                    </div>

                    <div class="rounded-lg bg-muted/30 p-4">
                        <h3 class="text-sm font-semibold text-foreground">Comments ({{ $incident->comments->count() }})</h3>
                        <ul class="mt-3 space-y-2 text-sm text-foreground">
                            @forelse($incident->comments as $comment)
                                <li class="rounded-md bg-muted/20 px-3 py-2">
                                    <p>{{ $comment->body }}</p>
                                    <p class="text-xs text-muted-foreground">{{ $comment->user?->displayName() ?: 'Unknown' }} · {{ $comment->created_at?->diffForHumans() }}</p>
                                </li>
                            @empty
                                <li class="text-sm text-muted-foreground">No comments yet.</li>
                            @endforelse
                        </ul>
                    </div>
                </div>
            </div>

            <div class="kt-card p-6 xl:col-span-4">
                <h2 class="text-lg font-semibold text-foreground">Workflow Actions</h2>

                @can('incidents.assign')
                    <form action="{{ route('incident-management.incidents.assign', $incident) }}" class="mt-4 space-y-2" method="POST">
                        @csrf
                        <label class="text-xs uppercase text-muted-foreground">Assign Incident</label>
                        <select class="kt-select" name="assigned_to_id" required>
                            <option value="">Select assignee</option>
                            @foreach($assignees as $assignee)
                                <option value="{{ $assignee->uuid }}">{{ $assignee->displayName() }} ({{ $assignee->email }})</option>
                            @endforeach
                        </select>
                        <input class="kt-input" name="note" placeholder="Assignment note (optional)" type="text">
                        <button class="kt-btn kt-btn-outline w-full" type="submit">Assign</button>
                    </form>
                @endcan

                @can('incidents.escalate')
                    <form action="{{ route('incident-management.incidents.escalate', $incident) }}" class="mt-5 space-y-2" method="POST">
                        @csrf
                        <label class="text-xs uppercase text-muted-foreground">Escalate Priority</label>
                        <select class="kt-select" name="to_priority_id" required>
                            <option value="">Select new priority</option>
                            @foreach($priorities as $priority)
                                <option value="{{ $priority->id }}">{{ $priority->name }}</option>
                            @endforeach
                        </select>
                        <textarea class="kt-textarea min-h-[80px]" name="reason" placeholder="Escalation reason"></textarea>
                        <button class="kt-btn kt-btn-outline w-full" type="submit">Escalate</button>
                    </form>
                @endcan

                @can('incidents.update')
                    <form action="{{ route('incident-management.incidents.resolve', $incident) }}" class="mt-5 space-y-2" method="POST">
                        @csrf
                        <label class="text-xs uppercase text-muted-foreground">Resolve Incident</label>
                        <select class="kt-select" name="status_id">
                            <option value="">Keep current status</option>
                            @foreach($statuses as $status)
                                <option value="{{ $status->id }}">{{ $status->name }}</option>
                            @endforeach
                        </select>
                        <button class="kt-btn kt-btn-success w-full" type="submit">Mark Resolved</button>
                    </form>

                    <form action="{{ route('incident-management.incidents.close', $incident) }}" class="mt-3 space-y-2" method="POST">
                        @csrf
                        <label class="text-xs uppercase text-muted-foreground">Close Incident</label>
                        <select class="kt-select" name="status_id">
                            <option value="">Keep current status</option>
                            @foreach($statuses as $status)
                                <option value="{{ $status->id }}">{{ $status->name }}</option>
                            @endforeach
                        </select>
                        <button class="kt-btn kt-btn-primary w-full" type="submit">Close Incident</button>
                    </form>
                @endcan
            </div>
        </div>

        <div class="kt-card p-6">
            <h2 class="text-lg font-semibold text-foreground">Activity Timeline</h2>
            <div class="mt-4 space-y-3">
                @forelse($activities as $activity)
                    <div class="rounded-lg bg-muted/30 px-4 py-3">
                        <p class="text-sm font-medium text-foreground">{{ strtoupper((string) $activity->event) }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">{{ $activity->created_at?->format('M d, Y H:i') }} · {{ $activity->description }}</p>
                    </div>
                @empty
                    <p class="text-sm text-muted-foreground">No activity records yet.</p>
                @endforelse
            </div>
        </div>
    </section>
@endsection
