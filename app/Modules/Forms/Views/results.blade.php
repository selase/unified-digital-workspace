@extends('layouts.metronic.app')

@section('title', 'Results & Analytics: ' . $form->title)

@section('content')
<div class="flex flex-col gap-6">
    <x-metronic.page-header
        title="Results & Analytics: {{ $form->title }}"
        description="Tabulated response aggregates, statistical distributions, and raw dataset export."
    >
        <x-slot:actions>
            <div class="flex items-center gap-2">
                <a href="{{ route('forms.export-csv', $form) }}" class="kt-btn kt-btn-primary">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Export CSV
                </a>
                <a href="{{ route('forms.show', $form) }}" class="kt-btn kt-btn-outline">
                    Back to Form
                </a>
            </div>
        </x-slot:actions>
    </x-metronic.page-header>

    {{-- Overview Metrics Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="kt-card p-4">
            <div class="text-xs uppercase font-semibold text-muted-foreground">Total Responses</div>
            <div class="text-2xl font-extrabold text-foreground mt-1">
                {{ number_format($form->type === 'live_poll' ? $form->pollVotes()->count() : $totalSubmissions) }}
            </div>
        </div>
        <div class="kt-card p-4">
            <div class="text-xs uppercase font-semibold text-muted-foreground">Form Status</div>
            <div class="text-lg font-bold mt-1">
                <span class="kt-badge kt-badge-sm {{ $form->status === 'published' ? 'kt-badge-success' : ($form->status === 'draft' ? 'kt-badge-warning' : 'kt-badge-destructive') }}">
                    {{ ucfirst($form->status) }}
                </span>
            </div>
        </div>
        <div class="kt-card p-4">
            <div class="text-xs uppercase font-semibold text-muted-foreground">Classification</div>
            <div class="text-sm font-semibold text-foreground mt-1 uppercase">
                {{ str_replace('_', ' ', $form->type) }}
            </div>
        </div>
        <div class="kt-card p-4">
            <div class="text-xs uppercase font-semibold text-muted-foreground">Access Scope</div>
            <div class="text-sm font-semibold text-foreground mt-1 uppercase">
                {{ str_replace('_', ' ', $form->target_audience) }}
            </div>
        </div>
    </div>

    {{-- Live Poll Results --}}
    @if($form->type === 'live_poll')
        <div class="kt-card p-6">
            <h3 class="text-base font-bold text-foreground mb-6">Live Poll Vote Distribution</h3>
            <div class="space-y-6">
                @forelse($pollTally as $option => $data)
                    <div>
                        <div class="flex justify-between items-center text-sm font-semibold mb-2">
                            <span class="text-foreground">{{ $option }}</span>
                            <span class="text-primary font-bold text-base">
                                {{ $data['percentage'] }}% 
                                <span class="text-xs font-normal text-muted-foreground">({{ $data['count'] }} votes)</span>
                            </span>
                        </div>
                        <div class="w-full bg-muted rounded-full h-3.5 overflow-hidden">
                            <div class="bg-primary h-3.5 rounded-full transition-all duration-500" style="width: {{ $data['percentage'] }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-muted-foreground">No votes cast yet in this live poll.</p>
                @endforelse
            </div>
        </div>
    @else
        {{-- Standard Form / Survey / HR Request Aggregates --}}
        <div class="space-y-6">
            @forelse($aggregates as $fieldName => $stat)
                <div class="kt-card p-6">
                    <div class="flex items-center justify-between mb-4 border-b border-border/50 pb-3">
                        <div>
                            <h4 class="text-base font-bold text-foreground">{{ $stat['label'] }}</h4>
                            <span class="text-xs text-muted-foreground uppercase font-mono">Field: {{ $fieldName }} ({{ $stat['type'] }})</span>
                        </div>
                        <span class="kt-badge kt-badge-sm kt-badge-outline uppercase text-[10px]">
                            {{ $stat['type'] }}
                        </span>
                    </div>

                    {{-- Choice Fields (Select, Radio, Checkbox) --}}
                    @if(in_array($stat['type'], ['select', 'radio', 'checkbox'], true))
                        <div class="space-y-4">
                            @foreach($stat['options'] as $optName => $optData)
                                <div>
                                    <div class="flex justify-between items-center text-sm font-medium mb-1.5">
                                        <span>{{ $optName }}</span>
                                        <span class="font-bold text-primary">
                                            {{ $optData['percentage'] }}% 
                                            <span class="text-xs font-normal text-muted-foreground">({{ $optData['count'] }} responses)</span>
                                        </span>
                                    </div>
                                    <div class="w-full bg-muted rounded-full h-2.5 overflow-hidden">
                                        <div class="bg-primary h-2.5 rounded-full transition-all duration-500" style="width: {{ $optData['percentage'] }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                    {{-- Numeric Fields --}}
                    @elseif($stat['type'] === 'number')
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div class="p-3 bg-muted/30 rounded-lg border border-border/50">
                                <div class="text-xs text-muted-foreground uppercase font-semibold">Response Count</div>
                                <div class="text-xl font-extrabold text-foreground mt-1">{{ $stat['count'] }}</div>
                            </div>
                            <div class="p-3 bg-muted/30 rounded-lg border border-border/50">
                                <div class="text-xs text-muted-foreground uppercase font-semibold">Mean Average</div>
                                <div class="text-xl font-extrabold text-primary mt-1">{{ $stat['avg'] ?? 'N/A' }}</div>
                            </div>
                            <div class="p-3 bg-muted/30 rounded-lg border border-border/50">
                                <div class="text-xs text-muted-foreground uppercase font-semibold">Minimum Value</div>
                                <div class="text-xl font-extrabold text-foreground mt-1">{{ $stat['min'] ?? 'N/A' }}</div>
                            </div>
                            <div class="p-3 bg-muted/30 rounded-lg border border-border/50">
                                <div class="text-xs text-muted-foreground uppercase font-semibold">Maximum Value</div>
                                <div class="text-xl font-extrabold text-foreground mt-1">{{ $stat['max'] ?? 'N/A' }}</div>
                            </div>
                        </div>

                    {{-- Text / Textarea / Date Fields --}}
                    @else
                        <div>
                            <div class="text-xs font-semibold text-muted-foreground mb-2">
                                Total entries: <span class="text-foreground font-bold">{{ $stat['count'] }}</span> (showing up to 20 recent samples)
                            </div>
                            @if(!empty($stat['samples']))
                                <div class="space-y-2 max-h-60 overflow-y-auto pr-2">
                                    @foreach($stat['samples'] as $sample)
                                        <div class="p-2.5 bg-muted/30 border border-border/50 rounded text-xs text-foreground">
                                            {{ $sample }}
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-xs text-muted-foreground italic">No text responses collected yet.</p>
                            @endif
                        </div>
                    @endif
                </div>
            @empty
                <div class="kt-card p-8 text-center text-muted-foreground">
                    No fields configured for this form.
                </div>
            @endforelse
        </div>
    @endif
</div>
@endsection

