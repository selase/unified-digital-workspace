@extends('layouts.metronic.app')

@section('title', 'Projects')

@section('content')
    <section class="grid gap-6">
        <div class="kt-card p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Project Management</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Projects</h1>
                    <p class="mt-2 text-sm text-muted-foreground">All projects with team size, task counts, and lifecycle status.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('project-management.index') }}" class="kt-btn kt-btn-outline">Back to Hub</a>
                    <a href="{{ route('project-management.tasks.index') }}" class="kt-btn kt-btn-outline">View Tasks</a>
                </div>
            </div>
        </div>

        <div class="kt-card kt-card-grid min-w-full">
            <div class="kt-card-header">
                <h3 class="kt-card-title">All Projects</h3>
                <form method="GET" action="{{ route('project-management.projects.index') }}" class="flex items-center gap-2">
                    <input type="text" name="search" value="{{ $search }}" placeholder="Search projects…" class="kt-input min-w-[220px]" />
                    <select name="status" onchange="this.form.submit()" class="kt-select min-w-[140px]">
                        <option value="">All Statuses</option>
                        <option value="planned" @selected($status === 'planned')>Planned</option>
                        <option value="in-progress" @selected($status === 'in-progress')>In Progress</option>
                        <option value="on-hold" @selected($status === 'on-hold')>On Hold</option>
                        <option value="completed" @selected($status === 'completed')>Completed</option>
                        <option value="archived" @selected($status === 'archived')>Archived</option>
                    </select>
                    <button type="submit" class="kt-btn kt-btn-sm kt-btn-primary">Search</button>
                    @if($search !== '' || $status !== '')
                        <a href="{{ route('project-management.projects.index') }}" class="kt-btn kt-btn-sm kt-btn-outline">Clear</a>
                    @endif
                </form>
            </div>
            <div class="kt-card-content">
                <div class="kt-scrollable-x-auto">
                    <table class="kt-table table-auto kt-table-border">
                        <thead>
                            <tr class="text-xs uppercase text-muted-foreground">
                                <th>Project</th>
                                <th>Status</th>
                                <th>Priority</th>
                                <th>Tasks</th>
                                <th>Members</th>
                                <th>Updated</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm text-foreground">
                            @forelse($projects as $project)
                                <tr>
                                    <td>
                                        <p class="font-medium">{{ $project->name }}</p>
                                        <p class="text-xs text-muted-foreground">{{ $project->slug }}</p>
                                    </td>
                                    <td>
                                        @php
                                            $projectStatusClass = match($project->status) {
                                                'planned' => 'kt-badge-muted',
                                                'in-progress' => 'kt-badge-primary',
                                                'on-hold' => 'kt-badge-warning',
                                                'completed' => 'kt-badge-success',
                                                'archived' => 'kt-badge-outline',
                                                default => 'kt-badge-outline',
                                            };
                                        @endphp
                                        <span class="kt-badge {{ $projectStatusClass }}">{{ ucfirst($project->status) }}</span>
                                    </td>
                                    <td>
                                        @php
                                            $priorityClass = match($project->priority) {
                                                'critical' => 'kt-badge-danger',
                                                'high' => 'kt-badge-warning',
                                                'medium' => 'kt-badge-primary',
                                                'low' => 'kt-badge-muted',
                                                default => 'kt-badge-outline',
                                            };
                                        @endphp
                                        <span class="kt-badge {{ $priorityClass }}">{{ ucfirst($project->priority) }}</span>
                                    </td>
                                    <td>{{ $project->tasks_count }}</td>
                                    <td>{{ $project->members_count }}</td>
                                    <td>{{ $project->updated_at?->diffForHumans() }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="py-8 text-center text-muted-foreground" colspan="6">No projects found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $projects->links() }}</div>
            </div>
        </div>
    </section>
@endsection
