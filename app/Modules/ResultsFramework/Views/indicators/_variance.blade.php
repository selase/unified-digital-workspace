@if($varianceNotes->isNotEmpty() || ($mayAddVariance && auth()->user()->can('results.manage')))
    <section class="kt-card">
        <div class="kt-card-header"><h2 class="kt-card-title">Variance notes</h2></div>
        <div class="kt-card-content space-y-4">
            @foreach($varianceNotes as $note)
                <article>
                    <p class="font-semibold">{{ $note->reason }}</p>
                    @if($note->corrective_action)<p>Corrective action: {{ $note->corrective_action }}</p>@endif
                    <p class="text-xs text-muted-foreground">{{ ucfirst($note->status) }} · {{ ($note->resolved_at ?? $note->created_at)->toInstitutionalDate() }}</p>
                    @can('results.manage')
                        @if($note->status === 'open')
                            <form method="POST" action="{{ route('results-framework.indicators.variance.resolve', [$indicator->uuid, $note->id]) }}">
                                @csrf
                                <button class="kt-btn kt-btn-sm kt-btn-outline" type="submit">Resolve note</button>
                            </form>
                        @endif
                    @endcan
                </article>
            @endforeach
            @can('results.manage')
                @if($mayAddVariance)
                    <form method="POST" class="flex flex-col gap-2" action="{{ route('results-framework.indicators.variance.store', $indicator->uuid) }}">
                        @csrf
                        <label>Reason <textarea class="kt-input" name="reason" required>{{ is_string(old('reason')) ? old('reason') : '' }}</textarea></label>
                        <label>Corrective action <textarea class="kt-input" name="corrective_action">{{ is_string(old('corrective_action')) ? old('corrective_action') : '' }}</textarea></label>
                        <div><button class="kt-btn kt-btn-primary" type="submit">Add variance note</button></div>
                    </form>
                @endif
            @endcan
        </div>
    </section>
@endif
