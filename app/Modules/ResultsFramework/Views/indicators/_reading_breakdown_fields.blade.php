@php
    $locationRows = collect(old('breakdowns.location', []))
        ->filter(fn ($row) => is_array($row))
        ->map(fn ($row) => [
            'bucket' => is_scalar($row['bucket'] ?? null) ? $row['bucket'] : '',
            'value' => is_scalar($row['value'] ?? null) ? $row['value'] : '',
        ])->values()->all();
    $locationRows = $locationRows ?: [['bucket' => '', 'value' => '']];
@endphp
<details class="md:col-span-4" @if(old('breakdowns')) open @endif>
    <summary class="cursor-pointer font-semibold">Add breakdowns (optional)</summary>
    <p class="text-xs text-muted-foreground mt-2">Each completed breakdown must add up to the reading.</p>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-3">
        @foreach(\App\Modules\ResultsFramework\Support\Dimensions::FIXED_BUCKETS as $dimension => $buckets)
            <fieldset class="space-y-2">
                <legend class="font-semibold">{{ \App\Modules\ResultsFramework\Support\Dimensions::LABELS[$dimension] }}</legend>
                @foreach($buckets as $bucket => $label)
                    @php $oldValue = old('breakdowns.'.$dimension.'.'.$loop->index.'.value'); @endphp
                    <input type="hidden" name="breakdowns[{{ $dimension }}][{{ $loop->index }}][bucket]" value="{{ $bucket }}">
                    <label class="block">
                        {{ $label }}
                        <input class="kt-input" type="number" min="0" step="0.01" name="breakdowns[{{ $dimension }}][{{ $loop->index }}][value]" value="{{ is_scalar($oldValue) ? $oldValue : '' }}">
                    </label>
                @endforeach
            </fieldset>
        @endforeach
        <fieldset class="space-y-2" x-data="{ locations: @js($locationRows) }">
            <legend class="font-semibold">Location</legend>
            <template x-for="(row, index) in locations" :key="index">
                <div class="space-y-2">
                    <label class="block">Location label
                        <input class="kt-input" type="text" maxlength="255" :name="'breakdowns[location][' + index + '][bucket]'" x-model="row.bucket">
                    </label>
                    <label class="block">Figure
                        <input class="kt-input" type="number" min="0" step="0.01" :name="'breakdowns[location][' + index + '][value]'" x-model="row.value">
                    </label>
                    <button class="kt-btn kt-btn-sm kt-btn-outline" type="button" @click="locations.splice(index, 1)">Remove location</button>
                </div>
            </template>
            <button class="kt-btn kt-btn-sm kt-btn-outline" type="button" x-bind:disabled="locations.length >= 50" @click="locations.push({ bucket: '', value: '' })">Add location</button>
        </fieldset>
    </div>
</details>
