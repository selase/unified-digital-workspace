@php
    $oldMeasure = old('measure_key', $indicator->measure_key);
    $selectedMeasure = is_string($oldMeasure) ? $oldMeasure : '';
    $oldMeasureFilters = old('measure_filters', []);
    $filterLabels = ['service_type' => 'Service type', 'programme' => 'Beneficiary programme', 'campaign_id' => 'Campaign', 'form_id' => 'Form'];
@endphp
<form x-data="{ measureKey: @js($selectedMeasure) }" method="POST" class="kt-card" action="{{ $indicator->exists ? route('results-framework.indicators.update', $indicator->uuid) : route('results-framework.indicators.store') }}">
    @csrf
    @if($indicator->exists) @method('PUT') @endif
    <div class="kt-card-header"><h2 class="kt-card-title">{{ $indicator->exists ? 'Edit indicator' : 'New indicator' }}</h2></div>
    <div class="kt-card-content grid grid-cols-1 md:grid-cols-4 gap-3">
        @foreach($errors->all() as $error)
            <p class="text-destructive" role="alert">{{ $error }}</p>
        @endforeach
        <label class="md:col-span-2">Name <input class="kt-input" name="name" value="{{ old('name', $indicator->name) }}" required></label>
        <label>Programme <select class="kt-select" name="programme_id"><option value="">Unassigned</option>@foreach($programmes as $programme)<option value="{{ $programme->id }}" @selected((string) old('programme_id', $indicator->programme_id) === (string) $programme->id)>{{ $programme->name }}</option>@endforeach</select></label>
        <label>Result <select class="kt-select" name="result_id"><option value="">No result</option>@foreach($resultOptions as $result)<option value="{{ $result->id }}" @selected((string) old('result_id', $indicator->result_id) === (string) $result->id)>{{ $programmes->find($result->programme_id)?->name }} — {{ $result->title }}</option>@endforeach</select></label>
        <label>Unit <input class="kt-input" name="unit" value="{{ old('unit', $indicator->exists ? $indicator->unit : '') }}" placeholder="Defaults to the source unit"></label>
        <label>Source
            <select class="kt-select" name="measure_key" x-model="measureKey">
                <option value="" @selected($selectedMeasure === '')>Enter readings by hand</option>
                @if($indicator->measure_key && ! isset($measures[$indicator->measure_key]))
                    <option value="{{ $indicator->measure_key }}" @selected($selectedMeasure === $indicator->measure_key)>{{ $measureLabel }} (Unavailable)</option>
                @endif
                @foreach($measures as $key => $measure)
                    <option value="{{ $key }}" @selected($selectedMeasure === $key)>{{ $measure->label }}</option>
                @endforeach
            </select>
        </label>
        @foreach($measures as $key => $measure)
            @foreach($measure->filters as $filter => $options)
                @php
                    $savedValue = $indicator->measure_key === $key ? ($indicator->measure_filters[$filter] ?? '') : '';
                    $oldOptions = $oldMeasureFilters[$key] ?? [];
                    $oldValue = is_array($oldOptions) && array_key_exists($filter, $oldOptions) ? $oldOptions[$filter] : $savedValue;
                    $selectedValue = is_scalar($oldValue) ? (string) $oldValue : '';
                @endphp
                <label x-show="measureKey === @js($key)">
                    {{ $filterLabels[$filter] ?? ucfirst(str_replace('_', ' ', $filter)) }}
                    <select class="kt-select" name="measure_filters[{{ $key }}][{{ $filter }}]" x-bind:disabled="measureKey !== @js($key)">
                        <option value="" @selected($selectedValue === '')>All</option>
                        @if($savedValue !== '' && ! array_key_exists($savedValue, $options))
                            <option value="{{ $savedValue }}" @selected($selectedValue === (string) $savedValue)>Previously selected option (Unavailable)</option>
                        @endif
                        @foreach($options as $value => $label)
                            <option value="{{ $value }}" @selected($selectedValue === (string) $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
            @endforeach
        @endforeach
        <label>Baseline <input class="kt-input" type="number" step="0.01" name="baseline" value="{{ old('baseline', $indicator->baseline) }}"></label>
        <label>Target <input class="kt-input" type="number" step="0.01" name="target" value="{{ old('target', $indicator->target) }}" required></label>
        <label>Start date <input class="kt-input" type="date" name="starts_on" value="{{ old('starts_on', $indicator->starts_on?->toDateString() ?? today()->startOfYear()->toDateString()) }}" required></label>
        <label>End date <input class="kt-input" type="date" name="ends_on" value="{{ old('ends_on', $indicator->ends_on?->toDateString() ?? today()->endOfYear()->toDateString()) }}" required></label>
        <label class="md:col-span-2">Description <textarea class="kt-input" name="description">{{ old('description', $indicator->description) }}</textarea></label>
        <label class="md:col-span-2">Definition <textarea class="kt-input" name="definition">{{ old('definition', $indicator->definition) }}</textarea></label>
        <label class="md:col-span-2">Means of verification <textarea class="kt-input" name="means_of_verification">{{ old('means_of_verification', $indicator->means_of_verification) }}</textarea></label>
        @if($indicator->exists)
            <label class="md:col-span-2"><input class="kt-checkbox" type="checkbox" name="confirm_source_change" value="1" @checked(old('confirm_source_change'))> Confirm source change if readings already exist</label>
        @endif
        <div><button class="kt-btn kt-btn-primary" type="submit">{{ $indicator->exists ? 'Save indicator' : 'Create indicator' }}</button></div>
    </div>
</form>
