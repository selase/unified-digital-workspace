@extends('layouts.metronic.app')

@section('title', 'CRM Pipeline Kanban Board')

@section('content')
<div class="flex flex-col gap-6">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-foreground">Commercial & Deal Pipeline Kanban</h1>
            <p class="text-xs text-muted-foreground">Visual stage-by-stage progression of land, commercial, and institutional opportunities.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('crm.index') }}" class="kt-btn kt-btn-sm kt-btn-outline">CRM Hub</a>
            <a href="{{ route('crm.deals.index') }}" class="kt-btn kt-btn-sm kt-btn-outline">Deals Table</a>
        </div>
    </div>

    {{-- Kanban Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-6 gap-4 items-start">
        @foreach($stages as $stageKey => $stageLabel)
            @php
                $metric = $stageMetrics[$stageKey] ?? ['count' => 0, 'total_pesewas' => 0];
                $deals = $dealsByStage[$stageKey] ?? collect();
            @endphp
            <div class="kt-card bg-surface-200/40 rounded-xl p-3 border border-border flex flex-col gap-3 min-h-[500px]">
                {{-- Column Header --}}
                <div class="flex items-center justify-between pb-2 border-b border-border/60">
                    <div>
                        <div class="font-semibold text-xs text-foreground uppercase tracking-wider">{{ $stageLabel }}</div>
                        <div class="text-2xs text-muted-foreground font-mono">GHS {{ number_format($metric['total_pesewas'] / 100, 2) }}</div>
                    </div>
                    <span class="kt-badge kt-badge-sm kt-badge-outline">{{ $metric['count'] }}</span>
                </div>

                {{-- Column Cards --}}
                <div class="flex flex-col gap-3 overflow-y-auto max-h-[70vh]">
                    @forelse($deals as $deal)
                        <div class="kt-card p-3 shadow-xs hover:border-primary transition-colors bg-card">
                            <div class="flex items-start justify-between gap-2">
                                <a href="{{ route('crm.deals.show', $deal) }}" class="font-semibold text-xs text-foreground hover:text-primary hover:underline line-clamp-2">
                                    {{ $deal->title }}
                                </a>
                            </div>
                            <div class="text-2xs text-muted-foreground mt-1 truncate">
                                {{ $deal->account?->name ?? 'No Account' }}
                            </div>
                            <div class="mt-2 flex items-baseline justify-between pt-2 border-t border-border/40">
                                <span class="font-bold text-xs text-primary font-mono">{{ $deal->formattedValue() }}</span>
                                <span class="text-2xs font-semibold kt-badge kt-badge-xs kt-badge-outline">{{ $deal->probability }}%</span>
                            </div>

                            {{-- Stage Mover Dropdown --}}
                            @can('crm.deals.manage')
                                <div class="mt-2 pt-2 border-t border-border/30">
                                    <form action="{{ route('crm.deals.stage', $deal) }}" method="POST">
                                        @csrf
                                        <select name="stage" class="kt-select kt-select-xs text-2xs w-full" onchange="this.form.submit()">
                                            @foreach($stages as $k => $label)
                                                <option value="{{ $k }}" @selected($deal->stage === $k)>Move to: {{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </form>
                                </div>
                            @endcan
                        </div>
                    @empty
                        <div class="text-center py-8 text-2xs text-muted-foreground border border-dashed border-border/60 rounded-lg">
                            No deals in {{ $stageLabel }}
                        </div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection

