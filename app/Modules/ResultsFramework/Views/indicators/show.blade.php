@extends('layouts.metronic.app')

@section('title', $indicator->name)

@section('content')
@php $verdict = ['achieved' => 'Achieved', 'on_track' => 'On track', 'behind' => 'Behind', 'missed' => 'Missed']; @endphp
<div class="flex flex-col gap-6">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-foreground">{{ $indicator->name }}</h1>
            <p class="text-xs text-muted-foreground">{{ $indicator->programmeName() }} · {{ $sources[$indicator->source] }} · {{ $indicator->starts_on->toInstitutionalDate() }} to {{ $indicator->ends_on->toInstitutionalDate() }}</p>
        </div>
        <div class="flex gap-2">
            <a class="kt-btn kt-btn-sm kt-btn-outline" href="{{ route('results-framework.index') }}">All indicators</a>
            @can('results.manage')<form method="POST" action="{{ route('results-framework.indicators.toggle', $indicator->uuid) }}">@csrf<button class="kt-btn kt-btn-sm kt-btn-outline" type="submit">{{ $indicator->status === 'active' ? 'Archive' : 'Reactivate' }}</button></form>@endcan
        </div>
    </div>

    @can('results.manage')
        <a class="kt-btn kt-btn-outline" href="{{ route('results-framework.indicators.edit', $indicator->uuid) }}">Edit indicator</a>
    @endcan
    @if($indicator->definition || $indicator->means_of_verification)
        <div class="kt-card"><div class="kt-card-content">
            @if($indicator->definition)<p><strong>Definition:</strong> {{ $indicator->definition }}</p>@endif
            @if($indicator->means_of_verification)<p><strong>Means of verification:</strong> {{ $indicator->means_of_verification }}</p>@endif
        </div></div>
    @endif
    @include('results-framework::partials.flash')

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="rounded-lg bg-muted/30 p-4"><div class="text-xs text-muted-foreground">Starting figure</div><div class="mt-1 text-lg font-semibold">{{ number_format($indicator->baseline, 0) }}</div></div>
        <div class="rounded-lg bg-muted/30 p-4"><div class="text-xs text-muted-foreground">Now</div><div class="mt-1 text-lg font-semibold">{{ number_format($progress['current'], 0) }} {{ $indicator->unit }}</div></div>
        <div class="rounded-lg bg-muted/30 p-4"><div class="text-xs text-muted-foreground">Target</div><div class="mt-1 text-lg font-semibold">{{ number_format($indicator->target, 0) }}</div></div>
        <div class="rounded-lg bg-muted/30 p-4"><div class="text-xs text-muted-foreground">Status</div><div class="mt-1 text-lg font-semibold">{{ $verdict[$progress['status']] }}</div></div>
    </div>
    <div class="h-2 rounded bg-muted"><div class="h-2 rounded bg-primary" style="width: {{ min(100, $progress['percent']) }}%"></div></div>
    <p class="text-xs text-muted-foreground">{{ $progress['percent'] }}% of the way to the target with {{ $progress['time_percent'] }}% of the time used.@if($indicator->description) {{ $indicator->description }}@endif</p>

    @if($indicator->source === 'manual')
    @can('results.manage')
    <form method="POST" action="{{ route('results-framework.indicators.readings.store', $indicator->uuid) }}" class="kt-card">@csrf
        <div class="kt-card-header"><h3 class="kt-card-title">Add a reading</h3></div>
        <div class="kt-card-content grid grid-cols-1 md:grid-cols-4 gap-3">
            <input class="kt-input" type="number" step="0.01" name="value" value="{{ old('value') }}" placeholder="Figure now" required>
            <input class="kt-input" type="date" name="recorded_on" value="{{ old('recorded_on', today()->toDateString()) }}" required>
            <input class="kt-input" name="note" value="{{ old('note') }}" placeholder="Note (optional)">
            <div><button class="kt-btn kt-btn-primary" type="submit">Save reading</button></div>
        </div>
    </form>
    @endcan

    <div class="kt-card">
        <div class="kt-card-header"><h3 class="kt-card-title">Readings</h3></div>
        <div class="kt-card-table kt-scrollable-x-auto"><table class="kt-table table-auto kt-table-border">
            <thead><tr><th>Date</th><th class="text-end">Figure</th><th>Note</th></tr></thead>
            <tbody>
            @forelse($readings as $reading)
                <tr><td>{{ $reading->recorded_on->toInstitutionalDate() }}</td><td class="text-end">{{ number_format($reading->value, 2) }}</td><td>{{ $reading->note }}</td></tr>
            @empty
                <tr><td colspan="3" class="text-center text-muted-foreground py-6">No readings yet.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>
    @else
    <div class="rounded-lg bg-muted/30 p-4 text-sm text-muted-foreground">This figure is worked out live from {{ $sources[$indicator->source] }} between the start and end dates, so there is nothing to type in.</div>
    @endif
</div>
@endsection
