@extends('layouts.metronic.app')

@section('title', "Edit Deal: {$deal->title}")

@section('content')
<div class="max-w-2xl mx-auto flex flex-col gap-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-foreground">Edit Deal: {{ $deal->title }}</h1>
            <p class="text-xs text-muted-foreground">Adjust deal valuation, commercial terms, stage, and close date.</p>
        </div>
        <a href="{{ route('crm.deals.show', $deal) }}" class="kt-btn kt-btn-sm kt-btn-outline">Cancel</a>
    </div>

    <div class="kt-card p-6">
        <form action="{{ route('crm.deals.update', $deal) }}" method="POST" class="space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label class="kt-form-label text-xs">Deal Title *</label>
                <input type="text" name="title" value="{{ old('title', $deal->title) }}" class="kt-input" required>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label class="kt-form-label text-xs">Deal Value (GHS) *</label>
                    <input type="number" step="0.01" min="0" name="amount_ghs" value="{{ old('amount_ghs', $deal->value_pesewas / 100) }}" class="kt-input" required>
                </div>
                <div>
                    <label class="kt-form-label text-xs">Stage *</label>
                    <select name="stage" class="kt-select" required>
                        @foreach($stages as $st => $label)
                            <option value="{{ $st }}" @selected(old('stage', $deal->stage) === $st)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="kt-form-label text-xs">Win % *</label>
                    <input type="number" min="0" max="100" name="probability" value="{{ old('probability', $deal->probability) }}" class="kt-input" required>
                </div>
            </div>

            <div>
                <label class="kt-form-label text-xs">Expected Close Date</label>
                <input type="date" name="expected_close_date" value="{{ old('expected_close_date', $deal->expected_close_date?->format('Y-m-d')) }}" class="kt-input">
            </div>

            <div>
                <label class="kt-form-label text-xs">Notes & Commercial Conditions</label>
                <textarea name="notes" rows="3" class="kt-textarea">{{ old('notes', $deal->notes) }}</textarea>
            </div>

            <div class="flex items-center justify-end gap-2 pt-4 border-t border-border">
                <a href="{{ route('crm.deals.show', $deal) }}" class="kt-btn kt-btn-outline">Cancel</a>
                <button type="submit" class="kt-btn kt-btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>
@endsection

