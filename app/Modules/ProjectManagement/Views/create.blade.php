@extends('layouts.metronic.app')

@section('title', 'New Project Charter')

@section('content')
<div class="flex flex-col gap-5 max-w-5xl mx-auto">
    {{-- Page Header --}}
    <x-metronic.page-header
        title="Initialize Project Charter"
        description="Establish baseline charter parameters, executive sponsorship, stage-gate governance, and financial allocation."
    >
        <x-slot:actions>
            <a href="{{ route('project-management.projects.index') }}" class="kt-btn kt-btn-outline">
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

    <form method="POST" action="{{ route('project-management.projects.store') }}" class="flex flex-col gap-5">
        @csrf

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
                        value="{{ old('name') }}"
                        required
                        placeholder="e.g. Hospital EHR Modernization & Interoperability"
                        class="kt-input w-full"
                    />
                    <p class="mt-1 text-[11px] text-muted-foreground">Formal institutional initiative name.</p>
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
                        value="{{ old('slug') }}"
                        placeholder="e.g. ehr-modernization-2026 (leave empty to auto-generate)"
                        class="kt-input w-full"
                    />
                    <p class="mt-1 text-[11px] text-muted-foreground">Unique portfolio slug identifier.</p>
                </div>

                {{-- Project Lead / Owner --}}
                <div>
                    <label for="owner_id" class="block text-xs font-semibold text-foreground mb-1">
                        Project Lead / Owner
                    </label>
                    <select id="owner_id" name="owner_id" class="kt-select w-full">
                        <option value="">Assign to Me (Current User)</option>
                        @foreach($users as $user)
                            <option value="{{ $user->uuid }}" @selected(old('owner_id') === (string) $user->uuid)>
                                {{ $user->first_name }} {{ $user->last_name }} ({{ $user->email }})
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-[11px] text-muted-foreground">Accountable project director.</p>
                </div>

                {{-- Initial Stage / Status --}}
                <div>
                    <label for="status" class="block text-xs font-semibold text-foreground mb-1">
                        Initial Stage
                    </label>
                    <select id="status" name="status" class="kt-select w-full">
                        <option value="planned" @selected(old('status', 'planned') === 'planned')>Planned / Charter Phase</option>
                        <option value="in-progress" @selected(old('status') === 'in-progress')>Active Execution</option>
                    </select>
                </div>

                {{-- Priority --}}
                <div>
                    <label for="priority" class="block text-xs font-semibold text-foreground mb-1">
                        Institutional Priority
                    </label>
                    <select id="priority" name="priority" class="kt-select w-full">
                        <option value="medium" @selected(old('priority', 'medium') === 'medium')>Medium Priority</option>
                        <option value="high" @selected(old('priority') === 'high')>High Priority</option>
                        <option value="critical" @selected(old('priority') === 'critical')>Critical / Strategic</option>
                        <option value="low" @selected(old('priority') === 'low')>Low Priority</option>
                    </select>
                </div>
            </div>
        </x-metronic.card>

        {{-- Schedule & Budget Allocation Card --}}
        <x-metronic.card title="Timeline & Financial Allocation" subtitle="Target operational schedule and capital budget">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Start Date --}}
                <div>
                    <label for="start_date" class="block text-xs font-semibold text-foreground mb-1">
                        Target Start Date
                    </label>
                    <input
                        type="date"
                        id="start_date"
                        name="start_date"
                        value="{{ old('start_date') }}"
                        class="kt-input w-full"
                    />
                </div>

                {{-- Target Completion Date --}}
                <div>
                    <label for="end_date" class="block text-xs font-semibold text-foreground mb-1">
                        Target Completion Date
                    </label>
                    <input
                        type="date"
                        id="end_date"
                        name="end_date"
                        value="{{ old('end_date') }}"
                        class="kt-input w-full"
                    />
                </div>

                {{-- Budget Currency --}}
                <div>
                    <label for="currency" class="block text-xs font-semibold text-foreground mb-1">
                        Budget Currency
                    </label>
                    <select id="currency" name="currency" class="kt-select w-full">
                        <option value="USD" @selected(old('currency', 'USD') === 'USD')>USD ($) - US Dollar</option>
                        <option value="GHS" @selected(old('currency') === 'GHS')>GHS (GH₵) - Ghana Cedi</option>
                        <option value="EUR" @selected(old('currency') === 'EUR')>EUR (€) - Euro</option>
                        <option value="GBP" @selected(old('currency') === 'GBP')>GBP (£) - British Pound</option>
                    </select>
                </div>

                {{-- Budget Amount --}}
                <div>
                    <label for="budget_amount" class="block text-xs font-semibold text-foreground mb-1">
                        Approved Budget Amount
                    </label>
                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        id="budget_amount"
                        name="budget_amount"
                        value="{{ old('budget_amount') }}"
                        placeholder="e.g. 50000.00"
                        class="kt-input w-full font-mono"
                    />
                </div>
            </div>
        </x-metronic.card>

        {{-- Scope Statement Card --}}
        <x-metronic.card title="Project Charter & Scope Statement" subtitle="Objectives, deliverables, and success criteria">
            <div>
                <label for="description" class="block text-xs font-semibold text-foreground mb-1">
                    Charter Objectives & Deliverables
                </label>
                <textarea
                    id="description"
                    name="description"
                    rows="5"
                    placeholder="Outline the institutional business case, key project deliverables, operational dependencies, and expected outcomes…"
                    class="kt-input w-full"
                >{{ old('description') }}</textarea>
            </div>
        </x-metronic.card>

        {{-- Form Actions --}}
        <div class="flex items-center justify-end gap-3 pt-2">
            <a href="{{ route('project-management.projects.index') }}" class="kt-btn kt-btn-outline">
                Cancel
            </a>
            <button type="submit" class="kt-btn kt-btn-primary flex items-center gap-1.5">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                Initialize Project Charter
            </button>
        </div>
    </form>
</div>
@endsection

