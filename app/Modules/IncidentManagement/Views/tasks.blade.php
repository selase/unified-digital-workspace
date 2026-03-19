@extends('layouts.metronic.app')

@section('title', 'Incident Tasks')

@section('content')
    <section class="grid gap-6">
        <div class="kt-card p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Incident Management</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Task Board</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Execution tasks created from incident response workflows.</p>
                </div>
                <a class="kt-btn kt-btn-outline" href="{{ route('incident-management.index') }}">Back to Hub</a>
            </div>
            <div class="mt-5 grid gap-3 md:grid-cols-3">
                <div class="rounded-lg bg-muted/30 p-4">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Open</p>
                    <p class="mt-2 text-xl font-semibold text-foreground">{{ (int) ($statusCounts['open'] ?? 0) }}</p>
                </div>
                <div class="rounded-lg bg-muted/30 p-4">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">In Progress</p>
                    <p class="mt-2 text-xl font-semibold text-foreground">{{ (int) ($statusCounts['in_progress'] ?? 0) }}</p>
                </div>
                <div class="rounded-lg bg-muted/30 p-4">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Completed</p>
                    <p class="mt-2 text-xl font-semibold text-foreground">{{ (int) ($statusCounts['completed'] ?? 0) }}</p>
                </div>
            </div>
        </div>

        <div class="kt-card kt-card-grid min-w-full">
            <div class="kt-card-header">
                <h3 class="kt-card-title text-sm">Task Queue</h3>
            </div>
            <div class="kt-card-content">
                <div class="kt-scrollable-x-auto">
                    <table class="kt-table table-auto kt-table-border">
                        <thead>
                            <tr class="text-xs uppercase text-muted-foreground">
                                <th>Task</th>
                                <th>Incident</th>
                                <th>Assignee</th>
                                <th>Status</th>
                                <th>Due</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm text-foreground">
                            @forelse($tasks as $task)
                                <tr>
                                    <td>
                                        <p class="font-medium">{{ $task->title }}</p>
                                        <p class="text-xs text-muted-foreground">{{ $task->description ?: 'No description' }}</p>
                                    </td>
                                    <td>{{ $task->incident?->reference_code }} &ndash; {{ $task->incident?->title }}</td>
                                    <td>{{ $task->assignedTo?->displayName() ?: 'Unassigned' }}</td>
                                    <td>
                                        @php
                                            $taskBadge = match ($task->status) {
                                                'completed' => 'kt-badge-success',
                                                'in_progress' => 'kt-badge-primary',
                                                default => 'kt-badge-outline',
                                            };
                                        @endphp
                                        <span class="kt-badge {{ $taskBadge }}">{{ str_replace('_', ' ', $task->status) }}</span>
                                    </td>
                                    <td>{{ $task->due_at?->toDayDateTimeString() ?: '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="py-8 text-center text-muted-foreground" colspan="5">No tasks found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $tasks->links() }}</div>
            </div>
        </div>
    </section>
@endsection
