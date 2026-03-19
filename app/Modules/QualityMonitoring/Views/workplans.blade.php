@extends('layouts.metronic.app')

@section('title', 'Workplans')

@section('content')
    <section class="grid gap-6">
        <div class="kt-card p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Quality Monitoring</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Workplans</h1>
                    <p class="mt-2 text-sm text-muted-foreground">All performance workplans with objectives, reviews, and approval status.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('quality-monitoring.index') }}" class="kt-btn kt-btn-outline">Back to Hub</a>
                    <a href="{{ route('quality-monitoring.alerts.index') }}" class="kt-btn kt-btn-outline">View Alerts</a>
                </div>
            </div>
        </div>

        <div class="kt-card kt-card-grid min-w-full">
            <div class="kt-card-header">
                <h3 class="kt-card-title">All Workplans</h3>
                <form method="GET" action="{{ route('quality-monitoring.workplans.index') }}" class="flex items-center gap-2">
                    <input type="text" name="search" value="{{ $search }}" placeholder="Search workplans…" class="kt-input min-w-[220px]" />
                    <select name="status" onchange="this.form.submit()" class="kt-select min-w-[140px]">
                        <option value="">All Statuses</option>
                        <option value="draft" @selected($status === 'draft')>Draft</option>
                        <option value="active" @selected($status === 'active')>Active</option>
                        <option value="submitted" @selected($status === 'submitted')>Submitted</option>
                        <option value="approved" @selected($status === 'approved')>Approved</option>
                        <option value="archived" @selected($status === 'archived')>Archived</option>
                    </select>
                    <button type="submit" class="kt-btn kt-btn-sm kt-btn-primary">Search</button>
                    @if($search !== '' || $status !== '')
                        <a href="{{ route('quality-monitoring.workplans.index') }}" class="kt-btn kt-btn-sm kt-btn-outline">Clear</a>
                    @endif
                </form>
            </div>
            <div class="kt-card-content">
                <div class="kt-scrollable-x-auto">
                    <table class="kt-table table-auto kt-table-border">
                        <thead>
                            <tr class="text-xs uppercase text-muted-foreground">
                                <th>Workplan</th>
                                <th>Status</th>
                                <th>Period</th>
                                <th>Objectives</th>
                                <th>Reviews</th>
                                <th>Updated</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm text-foreground">
                            @forelse($workplans as $workplan)
                                <tr>
                                    <td>
                                        <p class="font-medium">{{ $workplan->title }}</p>
                                        <p class="text-xs text-muted-foreground">{{ $workplan->uuid }}</p>
                                    </td>
                                    <td>
                                        @php
                                            $wpStatusClass = match($workplan->status) {
                                                'draft' => 'kt-badge-muted',
                                                'active' => 'kt-badge-success',
                                                'submitted' => 'kt-badge-primary',
                                                'approved' => 'kt-badge-success',
                                                'archived' => 'kt-badge-outline',
                                                default => 'kt-badge-outline',
                                            };
                                        @endphp
                                        <span class="kt-badge {{ $wpStatusClass }}">{{ ucfirst($workplan->status) }}</span>
                                    </td>
                                    <td>
                                        <span class="text-xs">
                                            {{ $workplan->period_start?->format('M j, Y') ?: '—' }}
                                            – {{ $workplan->period_end?->format('M j, Y') ?: '—' }}
                                        </span>
                                    </td>
                                    <td>{{ $workplan->objectives_count }}</td>
                                    <td>{{ $workplan->reviews_count }}</td>
                                    <td>{{ $workplan->updated_at?->diffForHumans() }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="py-8 text-center text-muted-foreground" colspan="6">No workplans found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $workplans->links() }}</div>
            </div>
        </div>
    </section>
@endsection
