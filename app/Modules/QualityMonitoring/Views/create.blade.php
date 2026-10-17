@extends('layouts.metronic.app')

@section('title', 'Initialize Quality Workplan — Quality Monitoring')

@section('content')
<div class="flex flex-col gap-5">
    {{-- Page Header --}}
    <x-metronic.page-header
        title="Initialize Quality Workplan"
        description="Establish a new institutional quality programme, clinical indicator workplan, or accreditation roadmap."
    >
        <x-slot:actions>
            <a href="{{ route('quality-monitoring.workplans.index') }}" class="kt-btn kt-btn-outline">
                Back to Register
            </a>
        </x-slot:actions>
    </x-metronic.page-header>

    {{-- Main Form Container --}}
    <form action="{{ route('quality-monitoring.workplans.store') }}" method="POST">
        @csrf
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
            {{-- Left Column: Core Workplan Details --}}
            <div class="lg:col-span-8 flex flex-col gap-5">
                <x-metronic.card title="Workplan Definition" subtitle="Programme identification and operational coverage">
                    <div class="space-y-4">
                        <div>
                            <label for="title" class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1">
                                Workplan Title <span class="text-destructive">*</span>
                            </label>
                            <input
                                type="text"
                                id="title"
                                name="title"
                                value="{{ old('title') }}"
                                required
                                placeholder="e.g. Hospital-Wide Clinical Quality & Patient Safety Plan (JCI / ISO 9001)"
                                class="kt-input w-full text-foreground"
                            />
                            @error('title')
                                <p class="mt-1 text-xs text-destructive">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="period_start" class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1">
                                    Audit Period Start <span class="text-destructive">*</span>
                                </label>
                                <input
                                    type="date"
                                    id="period_start"
                                    name="period_start"
                                    value="{{ old('period_start', now()->startOfYear()->toDateString()) }}"
                                    required
                                    class="kt-input w-full text-foreground"
                                />
                                @error('period_start')
                                    <p class="mt-1 text-xs text-destructive">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="period_end" class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1">
                                    Audit Period End <span class="text-destructive">*</span>
                                </label>
                                <input
                                    type="date"
                                    id="period_end"
                                    name="period_end"
                                    value="{{ old('period_end', now()->endOfYear()->toDateString()) }}"
                                    required
                                    class="kt-input w-full text-foreground"
                                />
                                @error('period_end')
                                    <p class="mt-1 text-xs text-destructive">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div>
                            <label for="summary" class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1">
                                Programme Summary & Objectives
                            </label>
                            <textarea
                                id="summary"
                                name="metadata[summary]"
                                rows="4"
                                placeholder="State high-level institutional quality goals, target accreditation milestones, and key department stakeholders..."
                                class="kt-textarea w-full text-foreground"
                            >{{ old('metadata.summary') }}</textarea>
                            @error('metadata.summary')
                                <p class="mt-1 text-xs text-destructive">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </x-metronic.card>
            </div>

            {{-- Right Column: Governance, Owner & Accreditation Scope --}}
            <div class="lg:col-span-4 flex flex-col gap-5">
                <x-metronic.card title="Governance & Alignment" subtitle="Accreditation scope and committee lead">
                    <div class="space-y-4">
                        <div>
                            <label for="owner_id" class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1">
                                Lead Quality Officer / Owner
                            </label>
                            <select id="owner_id" name="owner_id" class="kt-select w-full text-foreground">
                                <option value="">Select Quality Lead...</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->uuid }}" @selected(old('owner_id') === (string) $user->uuid || auth()->user()?->uuid === $user->uuid)>
                                        {{ $user->first_name }} {{ $user->last_name }} ({{ $user->email }})
                                    </option>
                                @endforeach
                            </select>
                            @error('owner_id')
                                <p class="mt-1 text-xs text-destructive">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="org_unit" class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1">
                                Operational Scope / Unit
                            </label>
                            <input
                                type="text"
                                id="org_unit"
                                name="org_scope[unit]"
                                value="{{ old('org_scope.unit', 'Hospital-Wide / Clinical Governance') }}"
                                placeholder="e.g. Clinical Governance, Nursing, Surgical Services"
                                class="kt-input w-full text-foreground"
                            />
                        </div>

                        <div>
                            <label for="accreditation_standard" class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1">
                                Accreditation Standard Reference
                            </label>
                            <input
                                type="text"
                                id="accreditation_standard"
                                name="metadata[standard]"
                                value="{{ old('metadata.standard', 'Joint Commission International (JCI) Standards') }}"
                                placeholder="e.g. JCI Hospital 8th Ed, ISO 9001:2015"
                                class="kt-input w-full text-foreground"
                            />
                        </div>

                        <hr class="border-border" />

                        <div class="flex flex-col gap-2.5">
                            <button type="submit" class="kt-btn kt-btn-primary w-full justify-center">
                                Initialize Workplan
                            </button>
                            <a href="{{ route('quality-monitoring.workplans.index') }}" class="kt-btn kt-btn-outline w-full justify-center">
                                Cancel
                            </a>
                        </div>
                    </div>
                </x-metronic.card>

                <div class="rounded-xl border border-border/80 bg-muted/20 p-4 text-xs text-muted-foreground space-y-2">
                    <p class="font-semibold text-foreground flex items-center gap-1.5">
                        <svg class="h-4 w-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Stage-Gate Review Lifecycle
                    </p>
                    <p>New workplans start in Draft status. Once strategic objectives, KPIs, and activities are structured, submit the workplan to the Quality Committee for formal approval.</p>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

