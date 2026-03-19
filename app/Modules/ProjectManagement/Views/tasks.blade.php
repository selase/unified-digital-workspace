@extends('layouts.metronic.app')

@section('title', 'Tasks')

@section('content')
    <section class="grid gap-6">
        <div class="kt-card p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Project Management</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Task Board</h1>
                    <p class="mt-2 text-sm text-muted-foreground">All tasks across projects with status, priority, and assignment tracking.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('project-management.index') }}" class="kt-btn kt-btn-outline">Back to Hub</a>
                    <a href="{{ route('project-management.projects.index') }}" class="kt-btn kt-btn-outline">View Projects</a>
                </div>
            </div>
        </div>

        <div class="kt-card kt-card-grid min-w-full">
            <div class="kt-card-header">
                <h3 class="kt-card-title">All Tasks</h3>
                <form method="GET" action="{{ route('project-management.tasks.index') }}" class="flex items-center gap-2">
                    <input type="text" name="search" value="{{ $search }}" placeholder="Search tasks…" class="kt-input min-w-[220px]" />
                    <select name="status" onchange="this.form.submit()" class="kt-select min-w-[140px]">
                        <option value="">All Statuses</option>
                        <option value="todo" @selected($status === 'todo')>Todo</option>
                        <option value="in-progress" @selected($status === 'in-progress')>In Progress</option>
                        <option value="blocked" @selected($status === 'blocked')>Blocked</option>
                        <option value="review" @selected($status === 'review')>Review</option>
                        <option value="done" @selected($status === 'done')>Done</option>
                    </select>
                    <button type="submit" class="kt-btn kt-btn-sm kt-btn-primary">Search</button>
                    @if($search !== '' || $status !== '')
                        <a href="{{ route('project-management.tasks.index') }}" class="kt-btn kt-btn-sm kt-btn-outline">Clear</a>
                    @endif
                </form>
            </div>
            <div class="kt-card-content">
                <div class="kt-scrollable-x-auto">
                    <table class="kt-table table-auto kt-table-border">
                        <thead>
                            <tr class="text-xs uppercase text-muted-foreground">
                                <th>Task</th>
                                <th>Project</th>
                                <th>Status</th>
                                <th>Priority</th>
                                <th>Assignees</th>
                                <th>Due Date</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm text-foreground">
                            @forelse($tasks as $task)
                                <tr>
                                    <td>
                                        <p class="font-medium">{{ $task->title }}</p>
                                        <p class="text-xs text-muted-foreground">{{ $task->uuid }}</p>
                                    </td>
                                    <td>{{ $task->project?->name ?: 'Unlinked' }}</td>
                                    <td>
                                        @php
                                            $taskStatusClass = match($task->status) {
                                                'todo' => 'kt-badge-muted',
                                                'in-progress' => 'kt-badge-primary',
                                                'blocked' => 'kt-badge-danger',
                                                'review' => 'kt-badge-warning',
                                                'done' => 'kt-badge-success',
                                                default => 'kt-badge-outline',
                                            };
                                        @endphp
                                        <span class="kt-badge {{ $taskStatusClass }}">{{ ucfirst($task->status) }}</span>
                                    </td>
                                    <td>
                                        @php
                                            $taskPriorityClass = match($task->priority) {
                                                'critical' => 'kt-badge-danger',
                                                'high' => 'kt-badge-warning',
                                                'medium' => 'kt-badge-primary',
                                                'low' => 'kt-badge-muted',
                                                default => 'kt-badge-outline',
                                            };
                                        @endphp
                                        <span class="kt-badge {{ $taskPriorityClass }}">{{ ucfirst($task->priority) }}</span>
                                    </td>
                                    <td>{{ $task->assignments_count }}</td>
                                    <td>{{ $task->due_date?->format('M j, Y') ?: '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="py-8 text-center text-muted-foreground" colspan="6">No tasks found.</td>
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
