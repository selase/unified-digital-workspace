@extends('layouts.metronic.app')

@section('title', 'Submit Leave Request')

@section('content')
<div class="flex flex-col gap-5 max-w-4xl mx-auto">
    {{-- Page Header --}}
    <x-metronic.page-header
        title="Submit Leave Application"
        description="Formal leave request with statutory category selection, absence period, and relieving officer assignment."
    >
        <x-slot:actions>
            <a href="{{ route('hrms-core.leave-requests.index') }}" class="kt-btn kt-btn-outline">
                Cancel
            </a>
        </x-slot:actions>
    </x-metronic.page-header>

    @if ($errors->any())
        <div class="rounded-xl border border-destructive/20 bg-destructive/10 p-4 text-destructive text-sm space-y-1">
            <p class="font-semibold">Please correct the errors below:</p>
            <ul class="list-disc list-inside text-xs">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('hrms-core.leave-requests.store') }}" class="flex flex-col gap-5">
        @csrf

        <x-metronic.card title="Leave Application Details" subtitle="Employee information and absence schedule">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Employee Selection --}}
                <div>
                    <label for="employee_id" class="block text-xs font-semibold text-foreground mb-1">
                        Employee <span class="text-destructive">*</span>
                    </label>
                    <select id="employee_id" name="employee_id" required class="kt-select w-full">
                        <option value="">Select Employee</option>
                        @foreach($employees as $emp)
                            <option value="{{ $emp->id }}" @selected(old('employee_id') == $emp->id)>
                                {{ $emp->first_name }} {{ $emp->last_name }} ({{ $emp->employee_staff_id ?: 'No ID' }})
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Leave Category --}}
                <div>
                    <label for="leave_category_id" class="block text-xs font-semibold text-foreground mb-1">
                        Leave Category <span class="text-destructive">*</span>
                    </label>
                    <select id="leave_category_id" name="leave_category_id" required class="kt-select w-full">
                        <option value="">Select Leave Category</option>
                        @foreach($leaveCategories as $cat)
                            <option value="{{ $cat->id }}" @selected(old('leave_category_id') == $cat->id)>
                                {{ $cat->name }} ({{ $cat->default_days }} days entitled)
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Proposed Start Date --}}
                <div>
                    <label for="proposed_start_date" class="block text-xs font-semibold text-foreground mb-1">
                        Proposed Start Date <span class="text-destructive">*</span>
                    </label>
                    <input
                        type="date"
                        id="proposed_start_date"
                        name="proposed_start_date"
                        value="{{ old('proposed_start_date', now()->addDays(2)->toDateString()) }}"
                        required
                        class="kt-input w-full"
                    />
                </div>

                {{-- Proposed End Date --}}
                <div>
                    <label for="proposed_end_date" class="block text-xs font-semibold text-foreground mb-1">
                        Proposed End Date <span class="text-destructive">*</span>
                    </label>
                    <input
                        type="date"
                        id="proposed_end_date"
                        name="proposed_end_date"
                        value="{{ old('proposed_end_date', now()->addDays(7)->toDateString()) }}"
                        required
                        class="kt-input w-full"
                    />
                </div>

                {{-- Relieving Officer --}}
                <div>
                    <label for="relieving_officer_id" class="block text-xs font-semibold text-foreground mb-1">
                        Relieving Officer
                    </label>
                    <select id="relieving_officer_id" name="relieving_officer_id" class="kt-select w-full">
                        <option value="">Select Handover / Relieving Colleague</option>
                        @foreach($employees as $emp)
                            <option value="{{ $emp->id }}" @selected(old('relieving_officer_id') == $emp->id)>
                                {{ $emp->first_name }} {{ $emp->last_name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Contact When Away --}}
                <div>
                    <label for="contact_when_away" class="block text-xs font-semibold text-foreground mb-1">
                        Emergency Contact Phone During Leave
                    </label>
                    <input
                        type="text"
                        id="contact_when_away"
                        name="contact_when_away"
                        value="{{ old('contact_when_away') }}"
                        placeholder="e.g. +233 24 000 0000"
                        class="kt-input w-full"
                    />
                </div>

                {{-- Leave Reason / Remarks --}}
                <div class="md:col-span-2">
                    <label for="leave_reasons" class="block text-xs font-semibold text-foreground mb-1">
                        Leave Reason / Narrative
                    </label>
                    <textarea
                        id="leave_reasons"
                        name="leave_reasons"
                        rows="3"
                        placeholder="State reason for absence or additional handoff notes…"
                        class="kt-input w-full"
                    >{{ old('leave_reasons') }}</textarea>
                </div>
            </div>
        </x-metronic.card>

        {{-- Form Actions --}}
        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('hrms-core.leave-requests.index') }}" class="kt-btn kt-btn-outline">
                Cancel
            </a>
            <button type="submit" class="kt-btn kt-btn-primary flex items-center gap-1.5">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                Submit Leave Application
            </button>
        </div>
    </form>
</div>
@endsection

