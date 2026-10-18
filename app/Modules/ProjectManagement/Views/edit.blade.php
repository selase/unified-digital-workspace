@extends('layouts.metronic.app')

@section('title', 'Edit ' . $project->name . ' - Project Charter')

@section('content')
<div class="flex flex-col gap-5 max-w-5xl mx-auto">
    {{-- Page Header --}}
    <x-metronic.page-header
        title="Edit Project Charter: {{ $project->name }}"
        description="Update charter specifications, stage-gate status, leadership ownership, and financial expenditure allocations."
    >
        <x-slot:actions>
            <a href="{{ route('project-management.projects.show', $project->uuid) }}" class="kt-btn kt-btn-outline">
                &larr; Back to Workspace
            </a>
            @can('projects.delete')
                <form method="POST" action="{{ route('project-management.projects.destroy', $project->uuid) }}" onsubmit="return confirm('Are you sure you want to archive/delete this project?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="kt-btn kt-btn-destructive">
                        Archive Project
                    </button>
                </form>
            @endcan
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

    <form method="POST" action="{{ route('project-management.projects.update', $project->uuid) }}" class="flex flex-col gap-5">
        @csrf
        @method('PUT')

        {{-- Charter Core Parameters Card --}}
        <x-metronic.card title="Project Charter & Identity" subtitle="Core project identification and classification">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Project Name --}}
                <div class="md:col-span-2">
                    <label for="name" class="block text-xs font-semibold text-foreground mb-1">
                        Project Title <span class="text-destructive">*</span>
                    </label>
                    <input
                        type="text"
                        id="name"
                        name="name"
                        value="{{ old('name', $project->name) }}"
                        required
                        class="kt-input w-full"
                    />
                </div>

                {{-- Slug / Code --}}
                <div>
                    <label for="slug" class="block text-xs font-semibold text-foreground mb-1">
                        Project Slug / Code
                    </label>
                    <input
                        type="text"
                        id="slug"
                        name="slug"
                        value="{{ old('slug', $project->slug) }}"
                        class="kt-input w-full"
                    />
                </div>

                {{-- Project Lead / Owner --}}
                <div>
                    <label for="owner_id" class="block text-xs font-semibold text-foreground mb-1">
                        Project Lead / Owner
                    </label>
                    <select id="owner_id" name="owner_id" class="kt-select w-full">
                        <option value="">Unassigned</option>
                        @foreach($users as $user)
                            <option value="{{ $user->uuid }}" @selected(old('owner_id', $project->owner_id) === (string) $user->uuid)>
                                {{ $user->first_name }} {{ $user->last_name }} ({{ $user->email }})
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Stage / Status --}}
                <div>
                    <label for="status" class="block text-xs font-semibold text-foreground mb-1">
                        Lifecycle Stage
                    </label>
                    <select id="status" name="status" class="kt-select w-full">
                        <option value="planned" @selected(old('status', $project->status) === 'planned')>Planned / Charter Phase</option>
                        <option value="in-progress" @selected(old('status', $project->status) === 'in-progress')>Active Execution</option>
                        <option value="on-hold" @selected(old('status', $project->status) === 'on-hold')>On Hold / Pending Steering</option>
                        <option value="completed" @selected(old('status', $project->status) === 'completed')>Completed / Gate Passed</option>
                        <option value="archived" @selected(old('status', $project->status) === 'archived')>Archived / Closed</option>
                    </select>
                </div>

                {{-- Priority --}}
                <div>
                    <label for="priority" class="block text-xs font-semibold text-foreground mb-1">
                        Institutional Priority
                    </label>
                    <select id="priority" name="priority" class="kt-select w-full">
                        <option value="low" @selected(old('priority', $project->priority) === 'low')>Low Priority</option>
                        <option value="medium" @selected(old('priority', $project->priority) === 'medium')>Medium Priority</option>
                        <option value="high" @selected(old('priority', $project->priority) === 'high')>High Priority</option>
                        <option value="critical" @selected(old('priority', $project->priority) === 'critical')>Critical / Board Mandated</option>
                    </select>
                </div>
            </div>
        </x-metronic.card>

        {{-- Schedule & Financial Allocation Card --}}
        <x-metronic.card title="Schedule & Financial Execution" subtitle="Budget planning, actual spend, and target delivery windows">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Start Date --}}
                <div>
                    <label for="start_date" class="block text-xs font-semibold text-foreground mb-1">
                        Commencement Date
                    </label>
                    <input
                        type="date"
                        id="start_date"
                        name="start_date"
                        value="{{ old('start_date', $project->start_date?->format('Y-m-d')) }}"
                        class="kt-input w-full"
                    />
                </div>

                {{-- Target End Date --}}
                <div>
                    <label for="end_date" class="block text-xs font-semibold text-foreground mb-1">
                        Target Completion Date
                    </label>
                    <input
                        type="date"
                        id="end_date"
                        name="end_date"
                        value="{{ old('end_date', $project->end_date?->format('Y-m-d')) }}"
                        class="kt-input w-full"
                    />
                </div>

                {{-- Planned Budget --}}
                <div>
                    <label for="budget_amount" class="block text-xs font-semibold text-foreground mb-1">
                        Allocated Capital Budget
                    </label>
                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        id="budget_amount"
                        name="budget_amount"
                        value="{{ old('budget_amount', $project->budget_amount) }}"
                        placeholder="0.00"
                        class="kt-input w-full"
                    />
                </div>

                {{-- Actual Cost / Spend --}}
                <div>
                    <label for="actual_cost_amount" class="block text-xs font-semibold text-foreground mb-1">
                        Actual Incurred Spend
                    </label>
                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        id="actual_cost_amount"
                        name="actual_cost_amount"
                        value="{{ old('actual_cost_amount', $project->actual_cost_amount) }}"
                        placeholder="0.00"
                        class="kt-input w-full"
                    />
                    <p class="mt-1 text-[11px] text-muted-foreground">Changes to actual cost are audit logged.</p>
                </div>

                {{-- Currency --}}
                <div class="md:col-span-2">
                    <label for="currency" class="block text-xs font-semibold text-foreground mb-1">
                        Currency (3-letter ISO)
                    </label>
                    <input
                        type="text"
                        id="currency"
                        name="currency"
                        maxlength="3"
                        value="{{ old('currency', $project->currency ?? 'USD') }}"
                        class="kt-input w-36 uppercase font-mono"
                    />
                </div>
            </div>
        </x-metronic.card>

        {{-- Charter Scope Statement Card --}}
        <x-metronic.card title="Charter Scope & Objectives" subtitle="Business case and deliverable objectives">
            <div>
                <label for="description" class="block text-xs font-semibold text-foreground mb-1">
                    Charter Narrative / Scope Statement
                </label>
                <textarea
                    id="description"
                    name="description"
                    rows="6"
                    class="kt-input w-full leading-relaxed"
                >{{ old('description', $project->description) }}</textarea>
            </div>
        </x-metronic.card>

        {{-- Form Actions --}}
        <div class="flex items-center justify-end gap-3 pt-2">
            <a href="{{ route('project-management.projects.show', $project->uuid) }}" class="kt-btn kt-btn-outline">
                Cancel
            </a>
            <button type="submit" class="kt-btn kt-btn-primary">
                Save Charter Changes
            </button>
        </div>
    </form>
</div>
@endsection

