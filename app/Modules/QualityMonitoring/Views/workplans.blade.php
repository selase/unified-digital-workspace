@extends('layouts.metronic.app')

@section('title', 'Workplans Register — Quality Monitoring')

@section('content')
<div class="flex flex-col gap-5">
    {{-- Page Header --}}
    <x-metronic.page-header
        title="Quality & Performance Workplans"
        description="Comprehensive register of institutional workplans, accreditation frameworks, and strategic programmes."
    >
        <x-slot:actions>
            <a href="{{ route('quality-monitoring.index') }}" class="kt-btn kt-btn-outline">
                Back to Hub
            </a>
            <a href="{{ route('quality-monitoring.alerts.index') }}" class="kt-btn kt-btn-outline">
                View Alerts
            </a>
            @can('qm.workplans.manage')
                <a href="{{ route('quality-monitoring.workplans.create') }}" class="kt-btn kt-btn-primary flex items-center gap-1.5">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    New Workplan
                </a>
            @endcan
        </x-slot:actions>
    </x-metronic.page-header>

    {{-- Filter Toolbar & Table Card --}}
    <x-metronic.card title="Workplan Register" subtitle="Filter and monitor workplans across all operational scopes">
        {{-- Toolbar --}}
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 border-b border-border pb-4">
            <form method="GET" action="{{ route('quality-monitoring.workplans.index') }}" class="flex flex-wrap items-center gap-2.5">
                <input
                    type="text"
                    name="search"
                    value="{{ $search }}"
                    placeholder="Search by title..."
                    class="kt-input min-w-[240px] text-xs"
                />
                <select name="status" onchange="this.form.submit()" class="kt-select min-w-[150px] text-xs">
                    <option value="">All Statuses</option>
                    <option value="draft" @selected($status === 'draft')>Draft</option>
                    <option value="active" @selected($status === 'active')>Active</option>
                    <option value="submitted" @selected($status === 'submitted')>Submitted</option>
                    <option value="approved" @selected($status === 'approved')>Approved</option>
                    <option value="archived" @selected($status === 'archived')>Archived</option>
                </select>
                <button type="submit" class="kt-btn kt-btn-primary py-1 px-3 text-xs">
                    Filter
                </button>
                @if($search !== '' || $status !== '')
                    <a href="{{ route('quality-monitoring.workplans.index') }}" class="kt-btn kt-btn-outline py-1 px-2.5 text-xs">
                        Reset
                    </a>
                @endif
            </form>
        </div>

        {{-- Workplan Table --}}
        <div class="overflow-x-auto kt-scrollable-x-auto">
            <table class="kt-table table-auto kt-table-border w-full text-left">
                <thead>
                    <tr class="text-xs uppercase text-muted-foreground border-b border-border">
                        <th class="py-3 px-3">Workplan Title</th>
                        <th class="py-3 px-3">Status</th>
                        <th class="py-3 px-3">Audit Period</th>
                        <th class="py-3 px-3 text-center">Objectives</th>
                        <th class="py-3 px-3 text-center">Reviews</th>
                        <th class="py-3 px-3">Updated</th>
                        <th class="py-3 px-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border text-xs">
                    @forelse($workplans as $workplan)
                        <tr class="hover:bg-muted/30 transition">
                            <td class="py-3 px-3">
                                <p class="font-semibold text-foreground">{{ $workplan->title }}</p>
                                <p class="text-[11px] text-muted-foreground">
                                    {{ $workplan->org_scope['unit'] ?? $workplan->uuid }}
                                </p>
                            </td>
                            <td class="py-3 px-3">
                                @php
                                    $wpBadge = match($workplan->status) {
                                        'active' => 'success',
                                        'approved' => 'success',
                                        'submitted' => 'primary',
                                        'draft' => 'outline',
                                        'rejected' => 'destructive',
                                        default => 'outline',
                                    };
                                @endphp
                                <x-metronic.badge :variant="$wpBadge" size="sm">
                                    {{ ucfirst($workplan->status) }}
                                </x-metronic.badge>
                            </td>
                            <td class="py-3 px-3 text-muted-foreground">
                                {{ $workplan->period_start?->format('d/m/Y') ?: '—' }} – {{ $workplan->period_end?->format('d/m/Y') ?: '—' }}
                            </td>
                            <td class="py-3 px-3 text-center font-medium text-foreground">
                                {{ $workplan->objectives_count }}
                            </td>
                            <td class="py-3 px-3 text-center text-muted-foreground">
                                {{ $workplan->reviews_count }}
                            </td>
                            <td class="py-3 px-3 text-muted-foreground">
                                {{ $workplan->updated_at?->diffForHumans() }}
                            </td>
                            <td class="py-3 px-3 text-right">
                                <a href="{{ route('quality-monitoring.workplans.show', $workplan) }}" class="kt-btn kt-btn-outline py-1 px-2.5 text-xs">
                                    Workspace
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-muted-foreground">
                                No quality workplans matching the specified criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4 pt-3 border-t border-border">
            {{ $workplans->links() }}
        </div>
    </x-metronic.card>
</div>
@endsection
