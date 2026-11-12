@extends('layouts.metronic.app')

@section('title', 'Results Framework')

@section('content')
<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-xl font-bold text-foreground">Results Framework</h1>
        <div class="flex flex-wrap gap-2">
            <a class="kt-btn kt-btn-outline" href="{{ route('results-framework.indicators.index') }}">Indicators</a>
            <a class="kt-btn kt-btn-outline" href="{{ route('results-framework.programmes.index') }}">Programmes</a>
        </div>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach(['achieved' => 'Achieved', 'on_track' => 'On track', 'behind' => 'Behind', 'missed' => 'Missed'] as $status => $label)
            <div class="rounded-lg bg-muted/30 p-4">
                <div class="text-xs text-muted-foreground">{{ $label }}</div>
                <div class="mt-1 text-lg font-semibold">{{ $counts[$status] ?? 0 }}</div>
            </div>
        @endforeach
    </div>

    <section class="grid grid-cols-1 md:grid-cols-3 gap-4" aria-label="Programme progress">
        @forelse($programmes as $summary)
            <article class="kt-card">
                <div class="kt-card-header">
                    <h2 class="kt-card-title">
                        <a class="text-primary" href="{{ route('results-framework.programmes.show', $summary['programme']->uuid) }}">{{ $summary['programme']->name }}</a>
                    </h2>
                </div>
                <div class="kt-card-content">
                    @if($summary['count'] === 0)
                        <p class="text-sm text-muted-foreground">No active indicators</p>
                    @else
                        <p class="text-sm text-muted-foreground">{{ trans_choice(':count active indicator|:count active indicators', $summary['count']) }}</p>
                        @if($summary['restricted'])
                            <p class="text-sm">{{ \App\Modules\ResultsFramework\Services\BreakdownService::HIDDEN_NOTE }}</p>
                        @elseif($summary['percent'] === null)
                            <p class="text-sm text-muted-foreground">Progress unavailable</p>
                        @else
                            <p class="mt-1 text-lg font-semibold">{{ $summary['percent'] }}% average progress</p>
                        @endif
                    @endif
                </div>
            </article>
        @empty
            <p class="text-sm text-muted-foreground">No programmes have been added.</p>
        @endforelse
    </section>
</div>
@endsection
