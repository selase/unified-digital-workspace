@php
    $targetStatuses = [
        'achieved' => 'Achieved', 'missed' => 'Missed', 'pending' => 'Pending', 'in_progress' => 'In progress',
        'no_reading' => 'No reading', 'restricted' => 'Restricted', 'unavailable' => 'Unavailable',
    ];
@endphp
@if($periods !== [] || auth()->user()->can('results.manage'))
    <section class="kt-card">
        <div class="kt-card-header"><h2 class="kt-card-title">Period targets</h2></div>
        @if(in_array('restricted', array_column($periods, 'status'), true))
            <p class="kt-card-content text-xs text-muted-foreground">{{ \App\Modules\ResultsFramework\Services\BreakdownService::HIDDEN_NOTE }}</p>
        @endif
        @if($periods !== [])
            <div class="kt-card-table kt-scrollable-x-auto">
                <table class="kt-table table-auto kt-table-border">
                    <thead>
                        <tr><th>Period</th><th>Target</th><th>Achieved</th><th>Progress</th><th>Status</th>@can('results.manage')<th>Adjust target</th>@endcan</tr>
                    </thead>
                    <tbody>
                        @foreach($periods as $period)
                            <tr>
                                <th scope="row">{{ $period['start']->toInstitutionalDate() }} to {{ $period['end']->toInstitutionalDate() }}</th>
                                <td>{{ $period['target'] }}</td>
                                <td>{{ $period['achieved'] ?? '—' }}</td>
                                <td>{{ $period['percent'] === null ? '—' : $period['percent'].'%' }}</td>
                                <td>{{ $targetStatuses[$period['status']] }}</td>
                                @can('results.manage')
                                    <td>
                                        <form method="POST" class="flex gap-2" action="{{ route('results-framework.indicators.targets.store', $indicator->uuid) }}">
                                            @csrf
                                            <input type="hidden" name="period_start" value="{{ $period['start']->toDateString() }}">
                                            <input type="hidden" name="period_end" value="{{ $period['end']->toDateString() }}">
                                            <input class="kt-input" aria-label="Target for {{ $period['start']->toInstitutionalDate() }}" type="number" name="target_value" step="0.01" value="{{ $period['target'] }}" required>
                                            <button class="kt-btn kt-btn-sm kt-btn-outline" type="submit">Save</button>
                                        </form>
                                    </td>
                                @endcan
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        @can('results.manage')
            <div class="kt-card-content space-y-4">
                @if($periods === [])
                    <form method="POST" class="flex flex-wrap items-end gap-2" action="{{ route('results-framework.indicators.targets.generate', $indicator->uuid) }}">
                        @csrf
                        <label>Split the overall target
                            <select class="kt-select" name="frequency">
                                <option value="monthly">Calendar months</option>
                                <option value="quarterly">Calendar quarters</option>
                            </select>
                        </label>
                        <button class="kt-btn kt-btn-outline" type="submit">Generate targets</button>
                    </form>
                @endif
                <form method="POST" class="flex flex-wrap items-end gap-2" action="{{ route('results-framework.indicators.targets.store', $indicator->uuid) }}">
                    @csrf
                    @foreach(['period_start' => 'Start date', 'period_end' => 'End date'] as $name => $label)
                        <label>{{ $label }}
                            <input class="kt-input" type="date" name="{{ $name }}" min="{{ $indicator->starts_on->toDateString() }}" max="{{ $indicator->ends_on->toDateString() }}" value="{{ is_string(old($name)) ? old($name) : '' }}" required>
                        </label>
                    @endforeach
                    <label>Target
                        <input class="kt-input" type="number" name="target_value" step="0.01" value="{{ is_scalar(old('target_value')) ? old('target_value') : '' }}" required>
                    </label>
                    <button class="kt-btn kt-btn-outline" type="submit">Save period target</button>
                </form>
            </div>
        @endcan
    </section>
@endif
