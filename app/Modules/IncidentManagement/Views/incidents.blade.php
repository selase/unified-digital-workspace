@extends('layouts.metronic.app')

@section('title', 'Incidents')

@section('content')
    <section class="grid gap-6">
        <div class="rounded-xl border border-border bg-background p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Incident Management</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Incident Register</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Operational incident queue across status, priority, and category.</p>
                </div>
                <a class="kt-btn kt-btn-outline" href="{{ route('incident-management.index') }}">Back to Hub</a>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-background p-6">
            <div class="overflow-x-auto">
                <table class="kt-table">
                    <thead>
                        <tr class="text-xs uppercase text-muted-foreground">
                            <th>Reference</th>
                            <th>Title</th>
                            <th>Status</th>
                            <th>Priority</th>
                            <th>Category</th>
                            <th>Updated</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm text-foreground">
                        @forelse($incidents as $incident)
                            <tr>
                                <td class="font-medium">{{ $incident->reference_code }}</td>
                                <td>{{ $incident->title }}</td>
                                <td>{{ $incident->status?->name ?: 'Unassigned' }}</td>
                                <td>{{ $incident->priority?->name ?: 'Unassigned' }}</td>
                                <td>{{ $incident->category?->name ?: 'Unassigned' }}</td>
                                <td>{{ $incident->updated_at?->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="py-8 text-center text-muted-foreground" colspan="6">No incidents found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $incidents->links() }}</div>
        </div>
    </section>
@endsection
