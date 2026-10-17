@extends('layouts.metronic.app')

@section('title', 'Log New Incident')

@section('content')
    <div class="grid gap-5">
        <x-metronic.page-header
            title="Log New Incident"
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Incident Management', 'url' => route('incident-management.index')],
                ['label' => 'Register', 'url' => route('incident-management.incidents.index')],
                ['label' => 'Log Incident'],
            ]"
        >
            <x-slot:actions>
                <a href="{{ route('incident-management.incidents.index') }}" class="kt-btn kt-btn-outline">
                    Back to Register
                </a>
            </x-slot:actions>
        </x-metronic.page-header>

        <form action="{{ route('incident-management.incidents.store') }}" method="POST">
            @csrf

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
                {{-- Main Form Fields --}}
                <div class="lg:col-span-8 flex flex-col gap-5">
                    <x-metronic.card title="Incident Identification" subtitle="Essential occurrence details, description, and initial severity">
                        <div class="space-y-4">
                            <div>
                                <label for="title" class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1">
                                    Incident Title <span class="text-destructive">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="title"
                                    name="title"
                                    value="{{ old('title') }}"
                                    required
                                    placeholder="e.g. Medication Administration Discrepancy in ICU Ward B"
                                    class="kt-input w-full text-foreground"
                                />
                                @error('title')
                                    <p class="mt-1 text-xs text-destructive">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label for="category_id" class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1">
                                        Incident Category
                                    </label>
                                    <select id="category_id" name="category_id" class="kt-select w-full text-foreground">
                                        <option value="">Select Category...</option>
                                        @foreach($categories as $category)
                                            <option value="{{ $category->id }}" @selected((int) old('category_id') === $category->id)>
                                                {{ $category->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('category_id')
                                        <p class="mt-1 text-xs text-destructive">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="priority_id" class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1">
                                        Severity & Priority
                                    </label>
                                    <select id="priority_id" name="priority_id" class="kt-select w-full text-foreground">
                                        <option value="">Select Priority Level...</option>
                                        @foreach($priorities as $priority)
                                            <option value="{{ $priority->id }}" @selected((int) old('priority_id') === $priority->id)>
                                                {{ $priority->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('priority_id')
                                        <p class="mt-1 text-xs text-destructive">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <div>
                                <label for="description" class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1">
                                    Detailed Occurrence Description <span class="text-destructive">*</span>
                                </label>
                                <textarea
                                    id="description"
                                    name="description"
                                    rows="5"
                                    required
                                    placeholder="Provide a factual, objective account of what happened, patients or staff involved, immediate actions taken, and timeline..."
                                    class="kt-textarea w-full text-foreground"
                                >{{ old('description') }}</textarea>
                                @error('description')
                                    <p class="mt-1 text-xs text-destructive">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="impact" class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1">
                                    Observed Clinical / Operational Impact
                                </label>
                                <textarea
                                    id="impact"
                                    name="impact"
                                    rows="2"
                                    placeholder="e.g. Patient vital signs stable; delayed procedure by 45 minutes; no sentinel harm observed."
                                    class="kt-textarea w-full text-foreground"
                                >{{ old('impact') }}</textarea>
                                @error('impact')
                                    <p class="mt-1 text-xs text-destructive">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </x-metronic.card>
                </div>

                {{-- Triage & Ownership Sidebar --}}
                <div class="lg:col-span-4 flex flex-col gap-5">
                    <x-metronic.card title="Triage & Assignment" subtitle="Assign initial investigator and SLA resolution target">
                        <div class="space-y-4">
                            <div>
                                <label for="assigned_to_id" class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1">
                                    Lead Investigator / Assignee
                                </label>
                                <select id="assigned_to_id" name="assigned_to_id" class="kt-select w-full text-foreground">
                                    <option value="">Unassigned (Triaged by Quality Dept)</option>
                                    @foreach($assignees as $assignee)
                                        <option value="{{ $assignee->uuid }}" @selected((string) old('assigned_to_id') === (string) $assignee->uuid)>
                                            {{ $assignee->displayName() }} ({{ $assignee->email }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('assigned_to_id')
                                    <p class="mt-1 text-xs text-destructive">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="due_at" class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1">
                                    Target Resolution Deadline
                                </label>
                                <input
                                    type="datetime-local"
                                    id="due_at"
                                    name="due_at"
                                    value="{{ old('due_at') }}"
                                    class="kt-input w-full text-foreground"
                                />
                                <p class="mt-1 text-[11px] text-muted-foreground">If left empty, SLA calculation will automatically apply based on priority level.</p>
                                @error('due_at')
                                    <p class="mt-1 text-xs text-destructive">{{ $message }}</p>
                                @enderror
                            </div>

                            <hr class="border-border" />

                            <div class="flex flex-col gap-2.5">
                                <button type="submit" class="kt-btn kt-btn-primary w-full justify-center">
                                    Submit Incident Report
                                </button>
                                <a href="{{ route('incident-management.incidents.index') }}" class="kt-btn kt-btn-outline w-full justify-center">
                                    Cancel
                                </a>
                            </div>
                        </div>
                    </x-metronic.card>

                    <div class="rounded-xl border border-border/80 bg-muted/20 p-4 text-xs text-muted-foreground space-y-2">
                        <p class="font-semibold text-foreground flex items-center gap-1.5">
                            <svg class="h-4 w-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                            Confidential Non-Punitive Reporting
                        </p>
                        <p>Hospital quality management upholds Just Culture principles. Reporting safety occurrences protects patient safety and drives continuous clinical process improvements.</p>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection
