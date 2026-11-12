<form method="POST" class="kt-card" action="{{ $indicator->exists ? route('results-framework.indicators.update', $indicator->uuid) : route('results-framework.indicators.store') }}">
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
        <label>Unit <input class="kt-input" name="unit" value="{{ old('unit', $indicator->unit) }}"></label>
        <label>Source <select class="kt-select" name="source">@foreach($sources as $key => $label)<option value="{{ $key }}" @selected(old('source', $indicator->source) === $key)>{{ $label }}</option>@endforeach</select></label>
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
