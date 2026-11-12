@extends('layouts.metronic.app')

@section('title', 'Results Framework')

@section('content')
@php $verdict = ['achieved' => 'Achieved', 'on_track' => 'On track', 'behind' => 'Behind', 'missed' => 'Missed']; @endphp
<div class="flex flex-col gap-6">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-foreground">Results Framework</h1>
            <p class="text-xs text-muted-foreground">Are our programmes delivering? Each indicator has a starting figure, a target and a period.</p>
        </div>
        <div class="flex gap-2">
            <a class="kt-btn kt-btn-sm kt-btn-outline" href="{{ route('results-framework.report') }}">Report (CSV)</a>
            <a class="kt-btn kt-btn-sm kt-btn-outline" href="{{ route('results-framework.report', ['format' => 'pdf']) }}">Report (PDF)</a>
        </div>
    </div>

    @include('results-framework::partials.flash')

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach($verdict as $key => $label)
            <div class="rounded-lg bg-muted/30 p-4"><div class="text-xs text-muted-foreground">{{ $label }}</div><div class="mt-1 text-lg font-semibold">{{ $counts[$key] ?? 0 }}</div></div>
        @endforeach
    </div>

    @can('results.manage')
    <form method="POST" action="{{ route('results-framework.indicators.store') }}" class="kt-card">@csrf
        <div class="kt-card-header"><h3 class="kt-card-title">New indicator</h3></div>
        <div class="kt-card-content grid grid-cols-1 md:grid-cols-4 gap-3">
            <input class="kt-input md:col-span-2" name="name" value="{{ old('name') }}" placeholder="e.g. Children given a school meal" required>
            <select class="kt-select" name="programme_id" aria-label="Programme"><option value="">Unassigned</option>@foreach($programmes as $programme)<option value="{{ $programme->id }}" @selected((string) old('programme_id') === (string) $programme->id)>{{ $programme->name }}</option>@endforeach</select>
            <select class="kt-select" name="result_id" aria-label="Result"><option value="">No result</option>@foreach($resultOptions as $result)<option value="{{ $result->id }}" @selected((string) old('result_id') === (string) $result->id)>{{ $programmes->find($result->programme_id)?->name }} — {{ $result->title }}</option>@endforeach</select>
            <input class="kt-input" name="unit" value="{{ old('unit', 'people') }}" placeholder="Unit (people, hours, GHS)">
            <select class="kt-select md:col-span-2" name="source">@foreach($sources as $key => $label)<option value="{{ $key }}" @selected(old('source', 'manual') === $key)>{{ $label }}</option>@endforeach</select>
            <input class="kt-input" type="number" step="0.01" name="baseline" value="{{ old('baseline', 0) }}" placeholder="Starting figure">
            <input class="kt-input" type="number" step="0.01" name="target" value="{{ old('target') }}" placeholder="Target" required>
            <input class="kt-input" type="date" name="starts_on" value="{{ old('starts_on', today()->startOfYear()->toDateString()) }}" title="Measuring starts" required>
            <input class="kt-input" type="date" name="ends_on" value="{{ old('ends_on', today()->endOfYear()->toDateString()) }}" title="Measuring ends" required>
            <input class="kt-input md:col-span-2" name="description" value="{{ old('description') }}" placeholder="How it is measured">
            <div><button class="kt-btn kt-btn-primary" type="submit">Create indicator</button></div>
        </div>
    </form>
    @endcan

    <div class="kt-card">
        <div class="kt-card-table kt-scrollable-x-auto"><table class="kt-table table-auto kt-table-border">
            <thead><tr><th>Indicator</th><th>Programme</th><th class="text-end">Start</th><th class="text-end">Now</th><th class="text-end">Target</th><th style="min-width:160px">Progress</th><th>Status</th></tr></thead>
            <tbody>
            @forelse($rows as $programmeName => $group)
                <tr><th colspan="7" scope="rowgroup">{{ $programmeName }}</th></tr>
                @foreach($group as $row)
                @php $i = $row['indicator']; $p = $row['progress']; @endphp
                <tr class="{{ $i->status === 'archived' ? 'text-muted-foreground' : '' }}">
                    <td><a class="text-primary" href="{{ route('results-framework.indicators.show', $i->uuid) }}">{{ $i->name }}</a><div class="text-xs text-muted-foreground">{{ $i->starts_on->toInstitutionalDate() }} to {{ $i->ends_on->toInstitutionalDate() }}</div></td>
                    <td>{{ $programmeName }}</td>
                    <td class="text-end">{{ number_format($i->baseline, 0) }}</td>
                    <td class="text-end">{{ number_format($p['current'], 0) }} <span class="text-xs text-muted-foreground">{{ $i->unit }}</span></td>
                    <td class="text-end">{{ number_format($i->target, 0) }}</td>
                    <td><div class="h-2 rounded bg-muted"><div class="h-2 rounded bg-primary" style="width: {{ min(100, $p['percent']) }}%"></div></div><div class="text-xs text-muted-foreground mt-1">{{ $p['percent'] }}% of target, {{ $p['time_percent'] }}% of time</div></td>
                    <td class="{{ in_array($p['status'], ['behind', 'missed'], true) ? 'text-destructive' : '' }}">{{ $i->status === 'archived' ? 'Archived' : $verdict[$p['status']] }}</td>
                </tr>
                @endforeach
            @empty
                <tr><td colspan="7" class="text-center text-muted-foreground py-6">No indicators yet.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>
</div>
@endsection
