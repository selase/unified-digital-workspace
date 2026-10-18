@extends('layouts.metronic.app')

@section('title', 'Edit Draft Form: ' . $form->title)

@section('content')
@php
$initialFields = $form->fields->map(fn($f) => [
    'label' => $f->label,
    'field_name' => $f->field_name,
    'field_type' => $f->field_type,
    'is_required' => (bool) $f->is_required,
    'options' => is_array($f->options_json) ? implode("\n", $f->options_json) : '',
])->values()->all();
if (empty($initialFields)) {
    $initialFields = [
        ['label' => 'Prompt Question', 'field_name' => '', 'field_type' => 'text', 'is_required' => true, 'options' => '']
    ];
}
@endphp

<div class="flex flex-col gap-6" x-data="{
    type: '{{ $form->type }}',
    audience: '{{ $form->target_audience }}',
    fields: {{ \Illuminate\Support\Js::from($initialFields) }},
    addField() {
        this.fields.push({ label: 'New Field', field_name: '', field_type: 'text', is_required: false, options: '' });
    },
    removeField(idx) {
        if (this.fields.length > 1) {
            this.fields.splice(idx, 1);
        }
    }
}">
    <x-metronic.page-header
        title="Edit Draft Form: {{ $form->title }}"
        description="Update form specifications, target audience, and fields before publishing."
    >
        <x-slot:actions>
            <div class="flex items-center gap-2">
                <a class="kt-btn kt-btn-outline" href="{{ route('forms.show', $form) }}">Cancel & Back</a>
            </div>
        </x-slot:actions>
    </x-metronic.page-header>

    <form action="{{ route('forms.update', $form) }}" method="POST">
        @csrf
        @method('PUT')

        {{-- Form Configuration Card --}}
        <div class="kt-card p-6 mb-6">
            <h3 class="text-base font-bold text-foreground mb-4">1. Form Specifications</h3>

            <div class="grid gap-6 md:grid-cols-2">
                <div class="kt-form-item">
                    <label class="kt-form-label">Form Title <span class="text-destructive">*</span></label>
                    <input class="kt-input" name="title" required type="text" value="{{ old('title', $form->title) }}">
                    @error('title') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="kt-form-item">
                    <label class="kt-form-label">URL Slug</label>
                    <input class="kt-input font-mono bg-muted/40 cursor-not-allowed" type="text" value="{{ $form->slug }}" readonly disabled>
                    <span class="text-xs text-muted-foreground mt-1">Slug is locked to maintain external link integrity.</span>
                </div>

                <div class="kt-form-item">
                    <label class="kt-form-label">Form Classification <span class="text-destructive">*</span></label>
                    <select class="kt-select" name="type" x-model="type" required>
                        <option value="general">General Inquiry Form</option>
                        <option value="hr_request">HR Employee Requisition</option>
                        <option value="survey">Feedback Survey</option>
                        <option value="live_poll">Live Community Poll</option>
                    </select>
                </div>

                <div class="kt-form-item">
                    <label class="kt-form-label">Target Audience <span class="text-destructive">*</span></label>
                    <select class="kt-select" name="target_audience" x-model="audience" required>
                        <option value="public">Public (Open Link Access)</option>
                        <option value="internal_only">Internal Staff Only</option>
                        <option value="departmental">Departmental Specific</option>
                    </select>
                </div>

                <div class="kt-form-item" x-show="audience === 'departmental'">
                    <label class="kt-form-label">Designated Department</label>
                    <select class="kt-select" name="target_department_id">
                        <option value="">-- Select Target Department --</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept->id }}" {{ (int) old('target_department_id', $form->target_department_id) === (int) $dept->id ? 'selected' : '' }}>
                                {{ $dept->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="kt-form-item">
                    <label class="kt-form-label">Expiration Date (Optional)</label>
                    <input class="kt-input" name="expires_at" type="datetime-local" value="{{ old('expires_at', $form->expires_at?->format('Y-m-d\TH:i')) }}">
                </div>

                <div class="md:col-span-2 kt-form-item">
                    <label class="kt-form-label">Description / Instructions</label>
                    <textarea class="kt-textarea" name="description" rows="3">{{ old('description', $form->description) }}</textarea>
                </div>

                <div class="md:col-span-2 flex flex-wrap gap-6 pt-2">
                    <label class="flex items-center gap-2 text-sm font-medium cursor-pointer">
                        <input type="checkbox" name="allow_anonymous" value="1" {{ old('allow_anonymous', $form->allow_anonymous) ? 'checked' : '' }} class="rounded border-border text-primary">
                        <span>Allow Anonymous Submissions</span>
                    </label>

                    <label class="flex items-center gap-2 text-sm font-medium cursor-pointer">
                        <input type="checkbox" name="single_response_per_user" value="1" {{ old('single_response_per_user', $form->single_response_per_user) ? 'checked' : '' }} class="rounded border-border text-primary">
                        <span>Enforce Single Response Rule (One Submission per User / Device)</span>
                    </label>
                </div>
            </div>
        </div>

        {{-- Dynamic Fields Builder --}}
        <div class="kt-card p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-base font-bold text-foreground">2. Form Fields & Schema</h3>
                    <p class="text-xs text-muted-foreground">Define inputs, question prompts, and response choices.</p>
                </div>
                <button type="button" @click="addField()" class="kt-btn kt-btn-xs kt-btn-primary">
                    + Add Question / Field
                </button>
            </div>

            <div class="space-y-4">
                <template x-for="(field, idx) in fields" :key="idx">
                    <div class="p-4 bg-muted/30 border border-border rounded-xl">
                        <div class="flex items-center justify-between mb-3 border-b border-border/50 pb-2">
                            <span class="text-xs font-bold uppercase tracking-wider text-primary" x-text="'Field #' + (idx + 1)"></span>
                            <button type="button" @click="removeField(idx)" class="text-xs text-destructive hover:underline" x-show="fields.length > 1">Remove Field</button>
                        </div>

                        <div class="grid gap-4 md:grid-cols-3">
                            <div class="md:col-span-2">
                                <label class="text-xs font-medium text-foreground">Question Label / Prompt *</label>
                                <input class="kt-input mt-1" type="text" :name="'fields[' + idx + '][label]'" x-model="field.label" required>
                            </div>

                            <div>
                                <label class="text-xs font-medium text-foreground">Field Type *</label>
                                <select class="kt-select mt-1" :name="'fields[' + idx + '][field_type]'" x-model="field.field_type" required>
                                    <option value="text">Single Line Text</option>
                                    <option value="textarea">Multi-line Paragraph</option>
                                    <option value="number">Numeric Input</option>
                                    <option value="select">Dropdown Menu</option>
                                    <option value="radio">Radio Choice (Single)</option>
                                    <option value="checkbox">Checkbox (Multiple)</option>
                                    <option value="date">Date Picker</option>
                                </select>
                            </div>

                            <div class="md:col-span-2" x-show="['select', 'radio', 'checkbox'].includes(field.field_type)">
                                <label class="text-xs font-medium text-foreground">Options List (One option per line)</label>
                                <textarea class="kt-textarea mt-1 text-xs font-mono" rows="3" :name="'fields[' + idx + '][options]'" x-model="field.options" placeholder="Option 1&#10;Option 2&#10;Option 3"></textarea>
                            </div>

                            <div class="flex items-center gap-2 pt-6">
                                <label class="flex items-center gap-2 text-xs font-medium cursor-pointer">
                                    <input type="checkbox" :name="'fields[' + idx + '][is_required]'" value="1" x-model="field.is_required" class="rounded border-border text-primary">
                                    <span>Mandatory Field</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <div class="mt-8 flex justify-end gap-3 pt-4 border-t border-border">
                <a href="{{ route('forms.show', $form) }}" class="kt-btn kt-btn-outline">Cancel</a>
                <button type="submit" name="status" value="draft" class="kt-btn kt-btn-outline">Save Changes (Stay Draft)</button>
                <button type="submit" name="status" value="published" class="kt-btn kt-btn-primary">Save & Publish</button>
            </div>
        </div>
    </form>
</div>
@endsection

