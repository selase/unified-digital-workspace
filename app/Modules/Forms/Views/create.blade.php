@extends('layouts.metronic.app')

@section('title', 'Create Form or Survey')

@section('content')
<div class="flex flex-col gap-6" x-data="{
    type: 'general',
    audience: 'public',
    fields: [
        { label: 'Full Name', field_name: 'full_name', field_type: 'text', is_required: true, options: '' },
        { label: 'Email Address', field_name: 'email', field_type: 'text', is_required: true, options: '' },
        { label: 'Inquiry Narrative', field_name: 'narrative', field_type: 'textarea', is_required: true, options: '' }
    ],
    addField() {
        this.fields.push({ label: 'New Field', field_name: '', field_type: 'text', is_required: false, options: '' });
    },
    removeField(idx) {
        if (this.fields.length > 1) {
            this.fields.splice(idx, 1);
        }
    },
    applyTemplate(template) {
        if (template === 'hr_leave') {
            this.type = 'hr_request';
            this.audience = 'internal_only';
            this.fields = [
                { label: 'Employee ID', field_name: 'employee_id', field_type: 'text', is_required: true, options: '' },
                { label: 'Leave Type', field_name: 'leave_type', field_type: 'select', is_required: true, options: 'Annual Leave\nSick Leave\nMaternity Leave\nCompassionate Leave' },
                { label: 'Start Date', field_name: 'start_date', field_type: 'date', is_required: true, options: '' },
                { label: 'End Date', field_name: 'end_date', field_type: 'date', is_required: true, options: '' },
                { label: 'Handover Notes', field_name: 'handover_notes', field_type: 'textarea', is_required: false, options: '' }
            ];
        } else if (template === 'live_poll') {
            this.type = 'live_poll';
            this.audience = 'public';
            this.fields = [
                { label: 'Select Your Preferred Option', field_name: 'poll_option', field_type: 'radio', is_required: true, options: 'Option A: In-Person Townhall\nOption B: Hybrid Virtual Webinar\nOption C: Written Committee Report' }
            ];
        } else if (template === 'satisfaction_survey') {
            this.type = 'survey';
            this.audience = 'public';
            this.fields = [
                { label: 'Service Rating (1 to 5)', field_name: 'rating', field_type: 'number', is_required: true, options: '' },
                { label: 'Key Strengths', field_name: 'strengths', field_type: 'textarea', is_required: false, options: '' },
                { label: 'Areas for Improvement', field_name: 'improvements', field_type: 'textarea', is_required: false, options: '' }
            ];
        }
    }
}">
    <x-metronic.page-header
        title="Create New Form / Requisition"
        description="Design flexible institutional input workflows with real-time field configuration."
    >
        <x-slot:actions>
            <a class="kt-btn kt-btn-outline" href="{{ route('forms.index') }}">Back to Forms</a>
        </x-slot:actions>
    </x-metronic.page-header>

    {{-- Template Quick Loader --}}
    <div class="kt-card p-4 flex flex-wrap items-center justify-between gap-4">
        <span class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Quick Start Templates:</span>
        <div class="flex flex-wrap gap-2">
            <button type="button" @click="applyTemplate('hr_leave')" class="kt-btn kt-btn-xs kt-btn-outline">
                📋 HR Leave Request
            </button>
            <button type="button" @click="applyTemplate('live_poll')" class="kt-btn kt-btn-xs kt-btn-outline">
                📊 Live Community Poll
            </button>
            <button type="button" @click="applyTemplate('satisfaction_survey')" class="kt-btn kt-btn-xs kt-btn-outline">
                ⭐ Client Feedback Survey
            </button>
        </div>
    </div>

    <form action="{{ route('forms.store') }}" method="POST">
        @csrf

        {{-- Form Configuration Card --}}
        <div class="kt-card p-6 mb-6">
            <h3 class="text-base font-bold text-foreground mb-4">1. Form Specifications</h3>

            <div class="grid gap-6 md:grid-cols-2">
                <div class="kt-form-item">
                    <label class="kt-form-label">Form Title <span class="text-destructive">*</span></label>
                    <input class="kt-input" name="title" required type="text" placeholder="e.g. IT Equipment Requisition Form" value="{{ old('title') }}">
                    @error('title') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="kt-form-item">
                    <label class="kt-form-label">URL Slug</label>
                    <input class="kt-input" name="slug" type="text" placeholder="auto-generated-if-empty" value="{{ old('slug') }}">
                    @error('slug') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
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
                        <option value="public">Public (Website visitors & clients)</option>
                        <option value="internal_only">Internal Only (Staff login required)</option>
                        <option value="departmental">Departmental (Specific department staff)</option>
                    </select>
                </div>

                <div class="kt-form-item" x-show="audience === 'departmental'">
                    <label class="kt-form-label">Designated Department</label>
                    <select class="kt-select" name="target_department_id">
                        <option value="">Select Department</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="kt-form-item">
                    <label class="kt-form-label">Expiration Date / Deadline</label>
                    <input class="kt-input" name="expires_at" type="datetime-local" value="{{ old('expires_at') }}">
                </div>

                <div class="md:col-span-2">
                    <label class="kt-form-label">Instructions / Description</label>
                    <textarea class="kt-textarea" name="description" rows="2" placeholder="Brief guidance displayed at the top of the form for responders..."></textarea>
                </div>

                <div class="flex flex-wrap gap-6 md:col-span-2 pt-2 border-t border-border">
                    <label class="flex items-center gap-2 text-xs font-medium cursor-pointer">
                        <input type="checkbox" name="allow_anonymous" value="1" class="rounded border-border text-primary">
                        <span>Allow Anonymous Submissions</span>
                    </label>

                    <label class="flex items-center gap-2 text-xs font-medium cursor-pointer">
                        <input type="checkbox" name="single_response_per_user" value="1" class="rounded border-border text-primary" checked>
                        <span>Enforce Single Submission Per User (Prevents duplicates)</span>
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
                <a href="{{ route('forms.index') }}" class="kt-btn kt-btn-outline">Cancel</a>
                <button type="submit" class="kt-btn kt-btn-primary">Publish Form</button>
            </div>
        </div>
    </form>
</div>
@endsection

