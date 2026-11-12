@extends('layouts.metronic.app')

@section('title', 'Results Framework')

@section('content')
@php $verdict = ['achieved' => 'Achieved', 'on_track' => 'On track', 'behind' => 'Behind', 'missed' => 'Missed', 'unavailable' => 'Unavailable', 'restricted' => 'Restricted']; @endphp
<div class="flex flex-col gap-6">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-foreground">Results Framework</h1>
            <p class="text-xs text-muted-foreground">Are our programmes delivering? Each indicator has a starting figure, a target and a period.</p>
        </div>
        <div class="flex gap-2">
            <a class="kt-btn kt-btn-sm kt-btn-outline" href="{{ route('results-framework.report') }}">Report (CSV)</a>
            <a class="kt-btn kt-btn-sm kt-btn-outline" href="{{ route('results-framework.report', ['format' => 'print']) }}">Print report</a>
            <a class="kt-btn kt-btn-sm kt-btn-outline" href="{{ route('results-framework.report', ['format' => 'pdf']) }}">Report (PDF)</a>
        </div>
    </div>

    @include('results-framework::partials.flash')

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach(\Illuminate\Support\Arr::except($verdict, ['unavailable', 'restricted']) as $key => $label)
            <div class="rounded-lg bg-muted/30 p-4"><div class="text-xs text-muted-foreground">{{ $label }}</div><div class="mt-1 text-lg font-semibold">{{ $counts[$key] ?? 0 }}</div></div>
        @endforeach
    </div>

    @can('results.manage')
    @include('results-framework::indicators.form', ['indicator' => new \App\Modules\ResultsFramework\Models\ResultIndicator])
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
                    <td><a class="text-primary" href="{{ route('results-framework.indicators.show', $i->uuid) }}">{{ $i->name }}</a><div class="text-xs text-muted-foreground">{{ $i->starts_on->toInstitutionalDate() }} to {{ $i->ends_on->toInstitutionalDate() }}</div>@if($row['hiddenBreakdowns'])<div class="text-xs text-muted-foreground">{{ \App\Modules\ResultsFramework\Services\BreakdownService::HIDDEN_NOTE }}</div>@endif</td>
                    <td>{{ $programmeName }}</td>
                    <td class="text-end">{{ number_format($i->baseline, 0) }}</td>
                    <td class="text-end">@if($p['current'] === null) Unavailable @elseif(is_string($p['current'])) {{ $p['current'] }} @else {{ number_format($p['current'], floor($p['current']) === $p['current'] ? 0 : 2) }} <span class="text-xs text-muted-foreground">{{ $i->unit }}</span> @endif</td>
                    <td class="text-end">{{ number_format($i->target, 0) }}</td>
                    <td>@if($p['percent'] !== null)<div class="h-2 rounded bg-muted"><div class="h-2 rounded bg-primary" style="width: {{ min(100, $p['percent']) }}%"></div></div><div class="text-xs text-muted-foreground mt-1">{{ $p['percent'] }}% of target, {{ $p['time_percent'] }}% of time</div>@endif</td>
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
