@extends('layouts.metronic.app')

@section('title', 'Incidents')

@section('content')
    <section class="grid gap-6">
        <div class="kt-card p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Incident Management</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Incident Register</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Operational queue across classification, ownership, SLA, and resolution state.</p>
                </div>
                <div class="flex items-center gap-2">
                    <a class="kt-btn kt-btn-outline" href="{{ route('incident-management.index') }}">Back to Hub</a>
                    @can('incidents.create')
                        <a class="kt-btn kt-btn-primary" href="{{ route('incident-management.incidents.create') }}">
                            <i class="ki-filled ki-plus text-base"></i>
                            New Incident
                        </a>
                    @endcan
                </div>
            </div>
        </div>

        <div class="kt-card kt-card-grid min-w-full">
            <div class="kt-card-header flex-wrap gap-3">
                <h3 class="kt-card-title text-sm">Incident Queue</h3>
                <form class="flex flex-wrap items-center gap-2" method="GET" action="{{ route('incident-management.incidents.index') }}">
                    <input
                        class="kt-input w-full min-w-[220px] lg:w-[280px]"
                        name="search"
                        placeholder="Search title, reference, description"
                        type="text"
                        value="{{ $search }}"
                    />
                    <select class="kt-select min-w-[170px]" name="status_id">
                        <option value="">All statuses</option>
                        @foreach($statuses as $statusOption)
                            <option value="{{ $statusOption->id }}" @selected($statusId === $statusOption->id)>{{ $statusOption->name }}</option>
                        @endforeach
                    </select>
                    <select class="kt-select min-w-[170px]" name="priority_id">
                        <option value="">All priorities</option>
                        @foreach($priorities as $priorityOption)
                            <option value="{{ $priorityOption->id }}" @selected($priorityId === $priorityOption->id)>{{ $priorityOption->name }}</option>
                        @endforeach
                    </select>
                    <select class="kt-select min-w-[170px]" name="category_id">
                        <option value="">All categories</option>
                        @foreach($categories as $categoryOption)
                            <option value="{{ $categoryOption->id }}" @selected($categoryId === $categoryOption->id)>{{ $categoryOption->name }}</option>
                        @endforeach
                    </select>
                    <button class="kt-btn kt-btn-outline" type="submit">Filter</button>
                </form>
            </div>
            <div class="kt-card-content">
                <div class="kt-scrollable-x-auto">
                    <table class="kt-table table-auto kt-table-border">
                        <thead>
                            <tr class="text-xs uppercase text-muted-foreground">
                                <th>Reference</th>
                                <th>Title</th>
                                <th>Status</th>
                                <th>Priority</th>
                                <th>Category</th>
                                <th>Updated</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm text-foreground">
                            @forelse($incidents as $incident)
                                <tr>
                                    <td class="font-medium">{{ $incident->reference_code }}</td>
                                    <td>{{ $incident->title }}</td>
                                    <td><span class="kt-badge kt-badge-outline">{{ $incident->status?->name ?: 'Unassigned' }}</span></td>
                                    <td><span class="kt-badge kt-badge-outline">{{ $incident->priority?->name ?: 'Unassigned' }}</span></td>
                                    <td>{{ $incident->category?->name ?: 'Unassigned' }}</td>
                                    <td>{{ $incident->updated_at?->diffForHumans() }}</td>
                                    <td>
                                        <div class="flex items-center justify-end gap-2">
                                            <a class="kt-btn kt-btn-sm kt-btn-outline" href="{{ route('incident-management.incidents.show', $incident) }}">View</a>
                                            @can('incidents.update')
                                                <a class="kt-btn kt-btn-sm kt-btn-outline" href="{{ route('incident-management.incidents.edit', $incident) }}">Edit</a>
                                            @endcan
                                            @can('incidents.delete')
                                                <form action="{{ route('incident-management.incidents.destroy', $incident) }}" method="POST">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="kt-btn kt-btn-sm kt-btn-danger" onclick="return confirm('Archive this incident?');" type="submit">Delete</button>
                                                </form>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="py-8 text-center text-muted-foreground" colspan="7">No incidents found for this filter.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $incidents->links() }}</div>
            </div>
        </div>
    </section>
@endsection
