@extends('layouts.metronic.app')

@section('title', 'Incident Register')

@section('content')
    <div class="grid gap-5">
        <x-metronic.page-header
            title="Incident Register"
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Incident Management', 'url' => route('incident-management.index')],
                ['label' => 'Register'],
            ]"
        >
            <x-slot:actions>
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('incident-management.index') }}" class="kt-btn kt-btn-outline">
                        Back to Hub
                    </a>
                    @can('incidents.create')
                        <a href="{{ route('incident-management.incidents.create') }}" class="kt-btn kt-btn-primary flex items-center gap-1.5">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            Log Incident
                        </a>
                    @endcan
                </div>
            </x-slot:actions>
        </x-metronic.page-header>

        <x-metronic.card title="Incident Queue" subtitle="Active clinical, operational, and occupational safety occurrences">
            <x-slot:headerActions>
                <form class="flex flex-wrap items-center gap-2" method="GET" action="{{ route('incident-management.incidents.index') }}">
                    <input
                        class="kt-input w-full min-w-[180px] sm:w-[220px]"
                        name="search"
                        placeholder="Search ref, title, description..."
                        type="text"
                        value="{{ $search }}"
                    />
                    <select class="kt-select min-w-[140px]" name="status_id">
                        <option value="">All statuses</option>
                        @foreach($statuses as $statusOption)
                            <option value="{{ $statusOption->id }}" @selected($statusId === $statusOption->id)>
                                {{ $statusOption->name }}
                            </option>
                        @endforeach
                    </select>
                    <select class="kt-select min-w-[140px]" name="priority_id">
                        <option value="">All priorities</option>
                        @foreach($priorities as $priorityOption)
                            <option value="{{ $priorityOption->id }}" @selected($priorityId === $priorityOption->id)>
                                {{ $priorityOption->name }}
                            </option>
                        @endforeach
                    </select>
                    <select class="kt-select min-w-[140px]" name="category_id">
                        <option value="">All categories</option>
                        @foreach($categories as $categoryOption)
                            <option value="{{ $categoryOption->id }}" @selected($categoryId === $categoryOption->id)>
                                {{ $categoryOption->name }}
                            </option>
                        @endforeach
                    </select>
                    <button class="kt-btn kt-btn-outline" type="submit">Filter</button>
                    @if($search || $statusId || $priorityId || $categoryId)
                        <a href="{{ route('incident-management.incidents.index') }}" class="kt-btn kt-btn-ghost text-xs">Reset</a>
                    @endif
                </form>
            </x-slot:headerActions>

            <div class="overflow-x-auto">
                <x-metronic.table>
                    <thead>
                        <tr class="text-xs uppercase text-muted-foreground">
                            <th>Incident</th>
                            <th>Status</th>
                            <th>Severity</th>
                            <th>Category</th>
                            <th>Due At</th>
                            <th>Updated</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="text-xs text-foreground divide-y divide-border/60">
                        @forelse($incidents as $incident)
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
                            <tr class="hover:bg-muted/30 transition-colors">
                                <td>
                                    <div class="flex flex-col">
                                        <a
                                            href="{{ route('incident-management.incidents.show', $incident) }}"
                                            class="font-semibold text-foreground hover:text-primary transition-colors text-sm"
                                        >
                                            {{ $incident->title }}
                                        </a>
                                        <span class="text-[11px] text-muted-foreground font-mono">{{ $incident->reference_code }}</span>
                                    </div>
                                </td>
                                <td>
                                    <x-metronic.badge :variant="$statusVariant" size="sm">
                                        {{ $incident->status?->name ?: 'Unassigned' }}
                                    </x-metronic.badge>
                                </td>
                                <td>
                                    <x-metronic.badge :variant="$priorityVariant" size="sm">
                                        {{ $incident->priority?->name ?: 'Standard' }}
                                    </x-metronic.badge>
                                </td>
                                <td>
                                    <span class="px-2 py-0.5 rounded bg-muted/40 font-medium text-foreground">
                                        {{ $incident->category?->name ?: 'Uncategorized' }}
                                    </span>
                                </td>
                                <td class="text-muted-foreground whitespace-nowrap">
                                    {{ $incident->due_at?->format('d/m/Y H:i') ?: '—' }}
                                </td>
                                <td class="text-muted-foreground whitespace-nowrap">
                                    {{ $incident->updated_at?->diffForHumans() }}
                                </td>
                                <td class="text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <a
                                            href="{{ route('incident-management.incidents.show', $incident) }}"
                                            class="kt-btn kt-btn-outline py-1 px-2.5 text-xs"
                                        >
                                            View
                                        </a>
                                        @can('incidents.update')
                                            <a
                                                href="{{ route('incident-management.incidents.edit', $incident) }}"
                                                class="kt-btn kt-btn-ghost py-1 px-2 text-xs"
                                                title="Edit incident details"
                                            >
                                                Edit
                                            </a>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-10 text-center text-muted-foreground">
                                    <p class="font-medium text-sm text-foreground">No incidents found.</p>
                                    <p class="text-xs text-muted-foreground mt-1">Try adjusting your filters or log a new incident.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-metronic.table>
            </div>

            @if($incidents->hasPages())
                <div class="mt-4 pt-4 border-t border-border">
                    {{ $incidents->links() }}
                </div>
            @endif
        </x-metronic.card>
    </div>
@endsection
