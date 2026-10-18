@extends('layouts.metronic.app')

@section('title', $project->name . ' - Project Workspace')

@section('content')
<div class="flex flex-col gap-5">
    {{-- Page Header --}}
    <x-metronic.page-header
        :title="$project->name"
        :description="'Project Code: ' . $project->slug . ' • Managed under institutional stage-gate governance.'"
    >
        <x-slot:actions>
            <a href="{{ route('project-management.projects.index') }}" class="kt-btn kt-btn-outline">
                &larr; Projects Register
            </a>
            @can('projects.update')
                <a href="{{ route('project-management.projects.edit', $project->uuid) }}" class="kt-btn kt-btn-outline">
                    <svg class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    Edit Charter
                </a>
            @endcan
            @php
                $statusVariant = match($project->status) {
                    'in-progress' => 'primary',
                    'completed' => 'success',
                    'on-hold' => 'warning',
                    'archived' => 'outline',
                    default => 'muted',
                };
                $priorityVariant = match($project->priority) {
                    'critical' => 'destructive',
                    'high' => 'warning',
                    'medium' => 'primary',
                    default => 'muted',
                };
            @endphp
            <x-metronic.badge :variant="$statusVariant" size="md">
                Stage: {{ ucfirst(str_replace('-', ' ', $project->status)) }}
            </x-metronic.badge>
            <x-metronic.badge :variant="$priorityVariant" size="md">
                Priority: {{ ucfirst($project->priority) }}
            </x-metronic.badge>
        </x-slot:actions>
    </x-metronic.page-header>

    {{-- Flash Notifications --}}
    @if(session('message'))
        <div class="rounded-xl border border-emerald-500/20 bg-emerald-500/10 p-4 text-emerald-800 dark:text-emerald-300 text-sm flex items-center gap-2">
            <svg class="h-5 w-5 shrink-0 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            {{ session('message') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="rounded-xl border border-destructive/20 bg-destructive/10 p-4 text-destructive text-sm space-y-1">
            <p class="font-semibold">Action could not be completed:</p>
            <ul class="list-disc list-inside text-xs">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Project KPI Overview: 4 Metric Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Completion Progress --}}
        <div class="rounded-xl border border-border bg-card p-4 flex flex-col justify-between">
            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Deliverable Progress</span>
            <div class="mt-2">
                <div class="flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-foreground">{{ $progressPercent }}%</span>
                    <span class="text-[11px] text-muted-foreground">{{ $completedTasks }} / {{ $totalTasks }} Tasks</span>
                </div>
                <div class="w-full bg-muted rounded-full h-2 mt-2 overflow-hidden">
                    <div class="bg-primary h-2 rounded-full transition-all duration-300" style="width: {{ $progressPercent }}%"></div>
                </div>
            </div>
        </div>

        {{-- Stage-Gate Milestones --}}
        <div class="rounded-xl border border-border bg-card p-4 flex flex-col justify-between">
            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Gate Milestones</span>
            <div class="mt-2 flex items-baseline justify-between">
                <span class="text-2xl font-bold text-foreground">{{ $completedMilestones }} <span class="text-sm font-normal text-muted-foreground">/ {{ $totalMilestones }}</span></span>
                <span class="text-[11px] text-muted-foreground">Key checkpoints</span>
            </div>
        </div>

        {{-- Target Schedule --}}
        <div class="rounded-xl border border-border bg-card p-4 flex flex-col justify-between">
            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Target Timeline</span>
            <div class="mt-2">
                <p class="text-sm font-semibold text-foreground">
                    @if($project->start_date && $project->end_date)
                        {{ $project->start_date->format('d/m/Y') }} – {{ $project->end_date->format('d/m/Y') }}
                    @elseif($project->start_date)
                        Started {{ $project->start_date->format('d/m/Y') }}
                    @else
                        Schedule uncommitted
                    @endif
                </p>
                <p class="text-[11px] text-muted-foreground mt-0.5">
                    @if($project->completed_at)
                        Completed on {{ $project->completed_at->format('d/m/Y') }}
                    @elseif($project->end_date)
                        Target due in {{ now()->diffInDays($project->end_date, false) }} days
                    @else
                        Active lifecycle
                    @endif
                </p>
            </div>
        </div>

        {{-- Budget Execution --}}
        @php
            $variance = $project->budgetVariance();
            $executionRate = $project->budgetExecutionPercent();
            $currency = $project->currency ?: 'USD';
        @endphp
        <div class="rounded-xl border border-border bg-card p-4 flex flex-col justify-between">
            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Budget Execution</span>
            <div class="mt-2">
                <div class="flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-foreground">
                        <span class="text-xs font-normal text-muted-foreground">{{ $currency }}</span>
                        {{ number_format((float) $project->actual_cost_amount, 2) }}
                    </span>
                    <span class="text-[11px] font-medium {{ $variance >= 0 ? 'text-emerald-600' : 'text-destructive font-bold' }}">
                        {{ $variance >= 0 ? '+' : '' }}{{ number_format($variance, 2) }} var
                    </span>
                </div>
                <div class="w-full bg-muted rounded-full h-2 mt-2 overflow-hidden">
                    <div class="h-2 rounded-full transition-all duration-300 {{ $executionRate > 100 ? 'bg-destructive' : 'bg-primary' }}" style="width: {{ min(100, $executionRate) }}%"></div>
                </div>
                <div class="flex justify-between items-center mt-1 text-[10px] text-muted-foreground">
                    <span>Budget: {{ number_format((float) ($project->budget_amount ?? 0), 2) }}</span>
                    <span>{{ $executionRate }}% executed</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Main Workspace Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
        {{-- Left 8 cols: 5x5 Risk Matrix, Milestones, Work Breakdown (Tasks), and Scope --}}
        <div class="lg:col-span-8 flex flex-col gap-5">
            {{-- 5x5 Likelihood x Impact Risk Governance Matrix & Register --}}
            <x-metronic.card
                title="Risk Governance Matrix (5×5 Likelihood × Impact)"
                subtitle="Institutional risk identification, scoring, and mitigation register"
            >
                {{-- Visual 5x5 Risk Heatmap --}}
                <div class="p-4 bg-muted/10 rounded-xl border border-border">
                    <div class="flex items-center justify-between mb-3 text-xs">
                        <span class="font-semibold text-foreground">Likelihood vs Impact Heatmap</span>
                        <div class="flex items-center gap-3 text-[11px]">
                            <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span> Low (1-4)</span>
                            <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-yellow-500"></span> Med (5-9)</span>
                            <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span> High (10-14)</span>
                            <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-rose-500"></span> Critical (15-25)</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-6 gap-1.5 text-center text-[11px]">
                        {{-- Top header row: Y-axis label spacer + 5 Impact columns --}}
                        <div class="p-1 font-semibold text-[10px] text-muted-foreground flex items-center justify-center">L \ I</div>
                        <div class="p-1 font-semibold text-muted-foreground">1 Negligible</div>
                        <div class="p-1 font-semibold text-muted-foreground">2 Minor</div>
                        <div class="p-1 font-semibold text-muted-foreground">3 Moderate</div>
                        <div class="p-1 font-semibold text-muted-foreground">4 Major</div>
                        <div class="p-1 font-semibold text-muted-foreground">5 Catastrophic</div>

                        @php
                            $likelihoodLabels = [
                                5 => '5 Almost Certain',
                                4 => '4 Likely',
                                3 => '3 Possible',
                                2 => '2 Unlikely',
                                1 => '1 Rare',
                            ];
                        @endphp

                        @foreach([5, 4, 3, 2, 1] as $lh)
                            <div class="p-1 font-semibold text-[10px] text-muted-foreground text-left flex items-center">
                                {{ $likelihoodLabels[$lh] }}
                            </div>
                            @foreach([1, 2, 3, 4, 5] as $imp)
                                @php
                                    $cellScore = $lh * $imp;
                                    $cellBg = match(true) {
                                        $cellScore >= 15 => 'bg-rose-500/20 text-rose-800 dark:text-rose-200 border-rose-500/40',
                                        $cellScore >= 10 => 'bg-amber-500/20 text-amber-800 dark:text-amber-200 border-amber-500/40',
                                        $cellScore >= 5 => 'bg-yellow-500/20 text-yellow-800 dark:text-yellow-200 border-yellow-500/40',
                                        default => 'bg-emerald-500/20 text-emerald-800 dark:text-emerald-200 border-emerald-500/40',
                                    };
                                    $cellRisks = $riskMatrix[$lh][$imp] ?? [];
                                    $count = count($cellRisks);
                                @endphp
                                <div class="p-2 rounded-lg border {{ $cellBg }} flex flex-col items-center justify-center min-h-[48px] relative transition hover:scale-105">
                                    <span class="text-[9px] opacity-60 font-mono">{{ $cellScore }}</span>
                                    @if($count > 0)
                                        <span class="text-xs font-bold px-1.5 py-0.5 rounded-full bg-card/80 shadow-xs border border-border mt-0.5" title="{{ $count }} risk(s) identified at score {{ $cellScore }}">
                                            {{ $count }}
                                        </span>
                                    @else
                                        <span class="text-[10px] opacity-30">—</span>
                                    @endif
                                </div>
                            @endforeach
                        @endforeach
                    </div>
                </div>

                {{-- Risk Register Table --}}
                <div class="mt-4 overflow-x-auto">
                    <table class="kt-table table-auto kt-table-border w-full text-left">
                        <thead>
                            <tr class="text-xs uppercase text-muted-foreground border-b border-border">
                                <th class="py-3 px-3">Risk Title & Category</th>
                                <th class="py-3 px-3 text-center">L × I</th>
                                <th class="py-3 px-3">Score & Severity</th>
                                <th class="py-3 px-3">Status</th>
                                <th class="py-3 px-3">Mitigation Strategy</th>
                                <th class="py-3 px-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border text-xs">
                            @forelse($project->risks as $risk)
                                <tr class="hover:bg-muted/30 transition">
                                    <td class="py-3 px-3">
                                        <p class="font-semibold text-foreground">{{ $risk->title }}</p>
                                        <p class="text-[10px] text-muted-foreground uppercase tracking-wider">
                                            {{ \App\Modules\ProjectManagement\Models\ProjectRisk::CATEGORIES[$risk->category] ?? ucfirst($risk->category) }}
                                        </p>
                                        @if($risk->description)
                                            <p class="text-[11px] text-muted-foreground mt-0.5">{{ $risk->description }}</p>
                                        @endif
                                    </td>
                                    <td class="py-3 px-3 text-center font-mono">
                                        {{ $risk->likelihood }} × {{ $risk->impact }}
                                    </td>
                                    <td class="py-3 px-3">
                                        <x-metronic.badge :variant="$risk->severityVariant()" size="sm">
                                            {{ $risk->risk_score }} • {{ ucfirst($risk->severityLevel()) }}
                                        </x-metronic.badge>
                                    </td>
                                    <td class="py-3 px-3">
                                        <x-metronic.badge :variant="$risk->statusVariant()" size="sm">
                                            {{ ucfirst($risk->status) }}
                                        </x-metronic.badge>
                                    </td>
                                    <td class="py-3 px-3 text-muted-foreground max-w-xs truncate">
                                        {{ $risk->mitigation_strategy ?: 'None recorded' }}
                                    </td>
                                    <td class="py-3 px-3 text-right">
                                        <div class="inline-flex items-center gap-1.5">
                                            {{-- Quick Edit Toggle Details --}}
                                            <details class="group relative">
                                                <summary class="kt-btn kt-btn-xs kt-btn-outline cursor-pointer list-none">
                                                    Edit
                                                </summary>
                                                <div class="absolute right-0 top-7 z-20 w-80 p-4 rounded-xl border border-border bg-card shadow-lg text-left">
                                                    <p class="font-semibold text-xs text-foreground mb-2">Update Risk</p>
                                                    <form method="POST" action="{{ route('project-management.projects.risks.update', ['project' => $project->uuid, 'risk' => $risk->uuid]) }}" class="space-y-2">
                                                        @csrf
                                                        <div>
                                                            <label class="block text-[10px] font-semibold mb-0.5">Title</label>
                                                            <input type="text" name="title" value="{{ $risk->title }}" required class="kt-input w-full text-xs" />
                                                        </div>
                                                        <div class="grid grid-cols-2 gap-2">
                                                            <div>
                                                                <label class="block text-[10px] font-semibold mb-0.5">Likelihood (1-5)</label>
                                                                <input type="number" name="likelihood" min="1" max="5" value="{{ $risk->likelihood }}" required class="kt-input w-full text-xs" />
                                                            </div>
                                                            <div>
                                                                <label class="block text-[10px] font-semibold mb-0.5">Impact (1-5)</label>
                                                                <input type="number" name="impact" min="1" max="5" value="{{ $risk->impact }}" required class="kt-input w-full text-xs" />
                                                            </div>
                                                        </div>
                                                        <div class="grid grid-cols-2 gap-2">
                                                            <div>
                                                                <label class="block text-[10px] font-semibold mb-0.5">Category</label>
                                                                <select name="category" class="kt-select w-full text-xs">
                                                                    @foreach(\App\Modules\ProjectManagement\Models\ProjectRisk::CATEGORIES as $ck => $cv)
                                                                        <option value="{{ $ck }}" @selected($risk->category === $ck)>{{ $cv }}</option>
                                                                    @endforeach
                                                                </select>
                                                            </div>
                                                            <div>
                                                                <label class="block text-[10px] font-semibold mb-0.5">Status</label>
                                                                <select name="status" class="kt-select w-full text-xs">
                                                                    @foreach(\App\Modules\ProjectManagement\Models\ProjectRisk::STATUSES as $sk => $sv)
                                                                        <option value="{{ $sk }}" @selected($risk->status === $sk)>{{ $sv }}</option>
                                                                    @endforeach
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div>
                                                            <label class="block text-[10px] font-semibold mb-0.5">Mitigation Strategy</label>
                                                            <textarea name="mitigation_strategy" rows="2" class="kt-input w-full text-xs">{{ $risk->mitigation_strategy }}</textarea>
                                                        </div>
                                                        <div class="flex justify-end gap-1.5 pt-1">
                                                            <button type="submit" class="kt-btn kt-btn-xs kt-btn-primary">Save</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </details>

                                            {{-- Delete Risk --}}
                                            <form method="POST" action="{{ route('project-management.projects.risks.destroy', ['project' => $project->uuid, 'risk' => $risk->uuid]) }}" onsubmit="return confirm('Remove risk?');">
                                                @csrf
                                                <button type="submit" class="kt-btn kt-btn-xs kt-btn-outline text-destructive hover:bg-destructive/10">
                                                    &times;
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-6 text-center text-muted-foreground text-xs">
                                        No risks recorded in matrix yet. Use the form below to register governance risks.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Add Risk Accordion / Form --}}
                @can('projects.update')
                    <div class="mt-4 pt-3 border-t border-border">
                        <details class="group">
                            <summary class="cursor-pointer text-xs font-semibold text-primary hover:underline flex items-center gap-1.5 list-none">
                                <svg class="h-3.5 w-3.5 transition group-open:rotate-90" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                                Register New Risk in Matrix
                            </summary>
                            <form method="POST" action="{{ route('project-management.projects.risks.store', $project->uuid) }}" class="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-3 p-3 bg-muted/20 rounded-xl border border-border">
                                @csrf
                                <div class="sm:col-span-2">
                                    <label class="block text-[11px] font-semibold text-foreground mb-1">Risk Title *</label>
                                    <input type="text" name="title" required placeholder="e.g. Vendor delay in EHR FHIR endpoint delivery" class="kt-input w-full" />
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-foreground mb-1">Category *</label>
                                    <select name="category" required class="kt-select w-full">
                                        @foreach(\App\Modules\ProjectManagement\Models\ProjectRisk::CATEGORIES as $catKey => $catLabel)
                                            <option value="{{ $catKey }}">{{ $catLabel }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-foreground mb-1">Likelihood (1: Rare &rarr; 5: Certain) *</label>
                                    <select name="likelihood" required class="kt-select w-full">
                                        <option value="1">1 - Rare</option>
                                        <option value="2">2 - Unlikely</option>
                                        <option value="3" selected>3 - Possible</option>
                                        <option value="4">4 - Likely</option>
                                        <option value="5">5 - Almost Certain</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-foreground mb-1">Impact (1: Negligible &rarr; 5: Catastrophic) *</label>
                                    <select name="impact" required class="kt-select w-full">
                                        <option value="1">1 - Negligible</option>
                                        <option value="2">2 - Minor</option>
                                        <option value="3" selected>3 - Moderate</option>
                                        <option value="4">4 - Major</option>
                                        <option value="5">5 - Catastrophic</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-foreground mb-1">Status *</label>
                                    <select name="status" required class="kt-select w-full">
                                        <option value="identified" selected>Identified</option>
                                        <option value="analyzed">Analyzed</option>
                                        <option value="mitigated">Mitigated</option>
                                        <option value="closed">Closed</option>
                                    </select>
                                </div>
                                <div class="sm:col-span-3">
                                    <label class="block text-[11px] font-semibold text-foreground mb-1">Mitigation Strategy / Response Plan</label>
                                    <input type="text" name="mitigation_strategy" placeholder="Actionable steps taken to reduce probability or impact…" class="kt-input w-full" />
                                </div>
                                <div class="sm:col-span-3 flex justify-end">
                                    <button type="submit" class="kt-btn kt-btn-sm kt-btn-primary">
                                        Register Risk
                                    </button>
                                </div>
                            </form>
                        </details>
                    </div>
                @endcan
            </x-metronic.card>

            {{-- Milestones & Stage Gates Card --}}
            <x-metronic.card
                title="Stage-Gate Milestones ({{ $project->milestones->count() }})"
                subtitle="Formal phase gates and governance approval checkpoints"
            >
                <div class="space-y-3">
                    @forelse($project->milestones as $milestone)
                        <div class="p-3 rounded-xl border border-border bg-card flex flex-wrap items-center justify-between gap-3 hover:border-primary/30 transition">
                            <div class="flex items-start gap-3">
                                <form method="POST" action="{{ route('project-management.projects.milestones.toggle', ['project' => $project->uuid, 'milestone' => $milestone->id]) }}">
                                    @csrf
                                    <button
                                        type="submit"
                                        title="{{ $milestone->completed_at ? 'Mark as Pending' : 'Mark as Completed' }}"
                                        class="mt-0.5 h-5 w-5 rounded border {{ $milestone->completed_at ? 'bg-emerald-600 border-emerald-600 text-white' : 'border-input hover:border-primary' }} flex items-center justify-center transition"
                                    >
                                        @if($milestone->completed_at)
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                            </svg>
                                        @endif
                                    </button>
                                </form>
                                <div>
                                    <p class="text-xs font-semibold {{ $milestone->completed_at ? 'line-through text-muted-foreground' : 'text-foreground' }}">
                                        {{ $milestone->name }}
                                    </p>
                                    @if($milestone->description)
                                        <p class="text-[11px] text-muted-foreground mt-0.5">{{ $milestone->description }}</p>
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-center gap-3 text-xs">
                                @if($milestone->due_date)
                                    <span class="text-[11px] text-muted-foreground font-mono">
                                        Target: {{ $milestone->due_date->format('d/m/Y') }}
                                    </span>
                                @endif
                                <span class="text-[11px] px-2 py-0.5 rounded-full bg-muted text-muted-foreground">
                                    {{ $milestone->tasks_count }} tasks
                                </span>
                                @if($milestone->completed_at)
                                    <x-metronic.badge variant="success" size="sm">Completed</x-metronic.badge>
                                @else
                                    <x-metronic.badge variant="outline" size="sm">Pending Gate</x-metronic.badge>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="py-6 text-center text-muted-foreground">
                            <p class="text-xs">No stage-gate milestones defined yet.</p>
                        </div>
                    @endforelse
                </div>

                {{-- Add Milestone Accordion / Form --}}
                @can('projects.update')
                    <div class="mt-4 pt-3 border-t border-border">
                        <details class="group">
                            <summary class="cursor-pointer text-xs font-semibold text-primary hover:underline flex items-center gap-1.5 list-none">
                                <svg class="h-3.5 w-3.5 transition group-open:rotate-90" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                                Add Stage-Gate Milestone
                            </summary>
                            <form method="POST" action="{{ route('project-management.projects.milestones.store', $project->uuid) }}" class="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-3 p-3 bg-muted/20 rounded-xl border border-border">
                                @csrf
                                <div class="sm:col-span-2">
                                    <label class="block text-[11px] font-semibold text-foreground mb-1">Milestone Name *</label>
                                    <input type="text" name="name" required placeholder="e.g. Gate 2: Technical Architecture Sign-off" class="kt-input w-full" />
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-foreground mb-1">Target Date</label>
                                    <input type="date" name="due_date" class="kt-input w-full" />
                                </div>
                                <div class="sm:col-span-3">
                                    <label class="block text-[11px] font-semibold text-foreground mb-1">Gate Description / Criteria</label>
                                    <input type="text" name="description" placeholder="Requirements necessary to pass this stage gate…" class="kt-input w-full" />
                                </div>
                                <div class="sm:col-span-3 flex justify-end">
                                    <button type="submit" class="kt-btn kt-btn-sm kt-btn-primary">
                                        Save Milestone
                                    </button>
                                </div>
                            </form>
                        </details>
                    </div>
                @endcan
            </x-metronic.card>

            {{-- Work Breakdown Structure / Tasks Card with Edit & Delete Actions --}}
            <x-metronic.card
                title="Work Breakdown Structure & Deliverables ({{ $project->tasks->count() }})"
                subtitle="Executable deliverables, assignments, time tracking, and full lifecycle edits"
            >
                <div class="overflow-x-auto">
                    <table class="kt-table table-auto kt-table-border w-full text-left">
                        <thead>
                            <tr class="text-xs uppercase text-muted-foreground border-b border-border">
                                <th class="py-3 px-3">Deliverable / Task</th>
                                <th class="py-3 px-3">Milestone</th>
                                <th class="py-3 px-3">Status</th>
                                <th class="py-3 px-3">Priority</th>
                                <th class="py-3 px-3">Assignee</th>
                                <th class="py-3 px-3">Due Date</th>
                                <th class="py-3 px-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border text-xs">
                            @forelse($project->tasks as $task)
                                @php
                                    $taskStatusVariant = match($task->status) {
                                        'done' => 'success',
                                        'in-progress' => 'primary',
                                        'blocked' => 'destructive',
                                        'review' => 'warning',
                                        default => 'outline',
                                    };
                                    $taskPriorityVariant = match($task->priority) {
                                        'critical' => 'destructive',
                                        'high' => 'warning',
                                        'medium' => 'primary',
                                        default => 'muted',
                                    };
                                    $assignedUser = $task->assignments->first()?->user;
                                    $taskLoggedMinutes = $task->timeEntries->sum('minutes');
                                    $taskLoggedHours = round($taskLoggedMinutes / 60, 1);
                                @endphp
                                <tr class="hover:bg-muted/30 transition">
                                    <td class="py-3 px-3">
                                        <p class="font-semibold text-foreground">{{ $task->title }}</p>
                                        <div class="flex items-center gap-2 mt-0.5 text-[10px] text-muted-foreground">
                                            @if($task->estimated_minutes)
                                                <span>Est: {{ round($task->estimated_minutes / 60, 1) }}h</span>
                                            @endif
                                            <span>•</span>
                                            <span class="font-medium text-primary">Logged: {{ $taskLoggedHours }}h</span>
                                        </div>
                                    </td>
                                    <td class="py-3 px-3 text-muted-foreground">
                                        {{ $task->milestone?->name ?: '—' }}
                                    </td>
                                    <td class="py-3 px-3">
                                        <x-metronic.badge :variant="$taskStatusVariant" size="sm">
                                            {{ ucfirst(str_replace('-', ' ', $task->status)) }}
                                        </x-metronic.badge>
                                    </td>
                                    <td class="py-3 px-3">
                                        <x-metronic.badge :variant="$taskPriorityVariant" size="sm">
                                            {{ ucfirst($task->priority) }}
                                        </x-metronic.badge>
                                    </td>
                                    <td class="py-3 px-3">
                                        @if($assignedUser)
                                            <span class="text-xs font-medium text-foreground">
                                                {{ $assignedUser->first_name }} {{ $assignedUser->last_name }}
                                            </span>
                                        @else
                                            <span class="text-muted-foreground italic">Unassigned</span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-3 font-mono text-[11px] text-muted-foreground">
                                        {{ $task->due_date?->format('d/m/Y') ?: '—' }}
                                    </td>
                                    <td class="py-3 px-3 text-right">
                                        <div class="inline-flex items-center gap-1.5">
                                            {{-- Status Quick Select --}}
                                            <form method="POST" action="{{ route('project-management.projects.tasks.status', ['project' => $project->uuid, 'task' => $task->uuid]) }}" class="inline">
                                                @csrf
                                                <select name="status" onchange="this.form.submit()" class="kt-select text-xs py-0.5 px-1.5 h-7">
                                                    <option value="todo" @selected($task->status === 'todo')>Todo</option>
                                                    <option value="in-progress" @selected($task->status === 'in-progress')>In Progress</option>
                                                    <option value="review" @selected($task->status === 'review')>Review</option>
                                                    <option value="blocked" @selected($task->status === 'blocked')>Blocked</option>
                                                    <option value="done" @selected($task->status === 'done')>Done</option>
                                                </select>
                                            </form>

                                            {{-- Edit Deliverable Dropdown Details --}}
                                            <details class="group relative inline-block">
                                                <summary class="kt-btn kt-btn-xs kt-btn-outline cursor-pointer list-none">
                                                    Edit
                                                </summary>
                                                <div class="absolute right-0 top-7 z-20 w-80 p-4 rounded-xl border border-border bg-card shadow-lg text-left">
                                                    <p class="font-semibold text-xs text-foreground mb-2">Edit Deliverable</p>
                                                    <form method="POST" action="{{ route('project-management.projects.tasks.update', ['project' => $project->uuid, 'task' => $task->uuid]) }}" class="space-y-2">
                                                        @csrf
                                                        <div>
                                                            <label class="block text-[10px] font-semibold mb-0.5">Title</label>
                                                            <input type="text" name="title" value="{{ $task->title }}" required class="kt-input w-full text-xs" />
                                                        </div>
                                                        <div class="grid grid-cols-2 gap-2">
                                                            <div>
                                                                <label class="block text-[10px] font-semibold mb-0.5">Priority</label>
                                                                <select name="priority" class="kt-select w-full text-xs">
                                                                    <option value="low" @selected($task->priority === 'low')>Low</option>
                                                                    <option value="medium" @selected($task->priority === 'medium')>Medium</option>
                                                                    <option value="high" @selected($task->priority === 'high')>High</option>
                                                                    <option value="critical" @selected($task->priority === 'critical')>Critical</option>
                                                                </select>
                                                            </div>
                                                            <div>
                                                                <label class="block text-[10px] font-semibold mb-0.5">Status</label>
                                                                <select name="status" class="kt-select w-full text-xs">
                                                                    <option value="todo" @selected($task->status === 'todo')>Todo</option>
                                                                    <option value="in-progress" @selected($task->status === 'in-progress')>In Progress</option>
                                                                    <option value="review" @selected($task->status === 'review')>Review</option>
                                                                    <option value="blocked" @selected($task->status === 'blocked')>Blocked</option>
                                                                    <option value="done" @selected($task->status === 'done')>Done</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="grid grid-cols-2 gap-2">
                                                            <div>
                                                                <label class="block text-[10px] font-semibold mb-0.5">Due Date</label>
                                                                <input type="date" name="due_date" value="{{ $task->due_date?->format('Y-m-d') }}" class="kt-input w-full text-xs" />
                                                            </div>
                                                            <div>
                                                                <label class="block text-[10px] font-semibold mb-0.5">Est. Hours</label>
                                                                <input type="number" step="0.5" name="estimated_hours" value="{{ $task->estimated_minutes ? round($task->estimated_minutes / 60, 1) : '' }}" class="kt-input w-full text-xs" />
                                                            </div>
                                                        </div>
                                                        <div>
                                                            <label class="block text-[10px] font-semibold mb-0.5">Assignee</label>
                                                            <select name="assigned_to" class="kt-select w-full text-xs">
                                                                <option value="">Unassigned</option>
                                                                @foreach($users as $u)
                                                                    <option value="{{ $u->uuid }}" @selected($assignedUser && $assignedUser->uuid === $u->uuid)>
                                                                        {{ $u->first_name }} {{ $u->last_name }}
                                                                    </option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                        <div class="flex justify-end gap-1.5 pt-1">
                                                            <button type="submit" class="kt-btn kt-btn-xs kt-btn-primary">Save Changes</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </details>

                                            {{-- Delete Deliverable --}}
                                            <form method="POST" action="{{ route('project-management.projects.tasks.destroy', ['project' => $project->uuid, 'task' => $task->uuid]) }}" onsubmit="return confirm('Remove deliverable?');">
                                                @csrf
                                                <button type="submit" class="kt-btn kt-btn-xs kt-btn-outline text-destructive hover:bg-destructive/10" title="Delete Task">
                                                    &times;
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="py-8 text-center text-muted-foreground">
                                        No deliverables assigned yet. Add tasks below to build the work breakdown.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Add Task Accordion / Form --}}
                @can('projects.update')
                    <div class="mt-4 pt-3 border-t border-border">
                        <details class="group">
                            <summary class="cursor-pointer text-xs font-semibold text-primary hover:underline flex items-center gap-1.5 list-none">
                                <svg class="h-3.5 w-3.5 transition group-open:rotate-90" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                                Add Deliverable / Task
                            </summary>
                            <form method="POST" action="{{ route('project-management.projects.tasks.store', $project->uuid) }}" class="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-3 p-3 bg-muted/20 rounded-xl border border-border">
                                @csrf
                                <div class="sm:col-span-2">
                                    <label class="block text-[11px] font-semibold text-foreground mb-1">Deliverable Title *</label>
                                    <input type="text" name="title" required placeholder="e.g. Configure HL7 FHIR Gateway endpoints" class="kt-input w-full" />
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-foreground mb-1">Linked Milestone</label>
                                    <select name="milestone_id" class="kt-select w-full">
                                        <option value="">No Milestone Linked</option>
                                        @foreach($project->milestones as $ms)
                                            <option value="{{ $ms->id }}">{{ $ms->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-foreground mb-1">Priority</label>
                                    <select name="priority" class="kt-select w-full">
                                        <option value="medium">Medium Priority</option>
                                        <option value="high">High Priority</option>
                                        <option value="critical">Critical</option>
                                        <option value="low">Low Priority</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-foreground mb-1">Assign Deliverable To</label>
                                    <select name="assigned_to" class="kt-select w-full">
                                        <option value="">Unassigned</option>
                                        @foreach($users as $u)
                                            <option value="{{ $u->uuid }}">{{ $u->first_name }} {{ $u->last_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-foreground mb-1">Target Due Date</label>
                                    <input type="date" name="due_date" class="kt-input w-full" />
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-foreground mb-1">Est. Effort (Hours)</label>
                                    <input type="number" step="0.5" min="0" name="estimated_hours" placeholder="e.g. 16" class="kt-input w-full" />
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="block text-[11px] font-semibold text-foreground mb-1">Specification / Deliverable Scope</label>
                                    <input type="text" name="description" placeholder="Acceptance criteria and deliverables…" class="kt-input w-full" />
                                </div>
                                <div class="sm:col-span-3 flex justify-end">
                                    <button type="submit" class="kt-btn kt-btn-sm kt-btn-primary">
                                        Create Deliverable
                                    </button>
                                </div>
                            </form>
                        </details>
                    </div>
                @endcan
            </x-metronic.card>

            {{-- Project Charter & Scope Statement Card --}}
            <x-metronic.card title="Charter Objectives & Governance History" subtitle="Formal business case and stage-gate transition audit log">
                <div class="prose dark:prose-invert max-w-none text-xs text-foreground leading-relaxed whitespace-pre-wrap bg-muted/20 p-4 rounded-xl border border-border">
                    {{ $project->description ?: 'No formal charter narrative recorded for this project.' }}
                </div>

                {{-- Stage Gate Review History --}}
                @php
                    $stageHistory = $project->metadata['stage_gate_history'] ?? [];
                @endphp
                @if(count($stageHistory) > 0)
                    <div class="mt-4 pt-3 border-t border-border space-y-2">
                        <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Governance Audit Trail</p>
                        <div class="space-y-2">
                            @foreach(array_reverse($stageHistory) as $log)
                                <div class="p-2.5 rounded-lg bg-muted/30 border border-border text-xs flex items-center justify-between">
                                    <div>
                                        <span class="font-medium text-foreground">
                                            Stage transitioned: <span class="capitalize">{{ $log['from_status'] ?? '—' }}</span> &rarr; <span class="font-semibold text-primary capitalize">{{ $log['to_status'] ?? '—' }}</span>
                                        </span>
                                        @if(!empty($log['note']))
                                            <p class="text-[11px] text-muted-foreground mt-0.5">"{{ $log['note'] }}"</p>
                                        @endif
                                    </div>
                                    <span class="text-[10px] text-muted-foreground font-mono">
                                        {{ isset($log['timestamp']) ? \Carbon\Carbon::parse($log['timestamp'])->diffForHumans() : '—' }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </x-metronic.card>
        </div>

        {{-- Right 4 cols: Financial Tracking, Time Logging, Stage Gate, Team --}}
        <div class="lg:col-span-4 flex flex-col gap-5">
            {{-- Capital Budget Execution & Actual Spend Tracking --}}
            <x-metronic.card title="Budget Execution & Spend" subtitle="Capital allocation, actual cost and variance tracking">
                <div class="space-y-3">
                    <div class="grid grid-cols-2 gap-3 p-3 rounded-xl bg-muted/20 border border-border text-xs">
                        <div>
                            <span class="text-[10px] uppercase text-muted-foreground block font-semibold">Allocated Budget</span>
                            <span class="text-sm font-bold text-foreground">
                                {{ $currency }} {{ number_format((float) ($project->budget_amount ?? 0), 2) }}
                            </span>
                        </div>
                        <div>
                            <span class="text-[10px] uppercase text-muted-foreground block font-semibold">Actual Spend</span>
                            <span class="text-sm font-bold text-foreground">
                                {{ $currency }} {{ number_format((float) $project->actual_cost_amount, 2) }}
                            </span>
                        </div>
                        <div class="col-span-2 pt-2 border-t border-border flex justify-between items-center">
                            <span class="text-[10px] uppercase text-muted-foreground font-semibold">Net Variance</span>
                            <span class="text-xs font-bold {{ $variance >= 0 ? 'text-emerald-600' : 'text-destructive font-black' }}">
                                {{ $variance >= 0 ? '+' : '' }}{{ $currency }} {{ number_format($variance, 2) }}
                                ({{ $executionRate }}%)
                            </span>
                        </div>
                    </div>

                    {{-- Quick Spend Adjustment Form --}}
                    @can('projects.update')
                        <details class="group">
                            <summary class="cursor-pointer text-xs font-semibold text-primary hover:underline flex items-center gap-1 list-none">
                                <svg class="h-3.5 w-3.5 transition group-open:rotate-90" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                                Update Actual Spend / Budget
                            </summary>
                            <form method="POST" action="{{ route('project-management.projects.budget.update', $project->uuid) }}" class="mt-2.5 p-3 rounded-xl border border-border bg-card space-y-2">
                                @csrf
                                <div>
                                    <label class="block text-[10px] font-semibold mb-0.5">Capital Budget ({{ $currency }})</label>
                                    <input type="number" step="0.01" min="0" name="budget_amount" value="{{ $project->budget_amount }}" class="kt-input w-full text-xs" />
                                </div>
                                <div>
                                    <label class="block text-[10px] font-semibold mb-0.5">Actual Incurred Spend ({{ $currency }}) *</label>
                                    <input type="number" step="0.01" min="0" name="actual_cost_amount" value="{{ $project->actual_cost_amount }}" required class="kt-input w-full text-xs" />
                                    <p class="text-[9px] text-muted-foreground mt-0.5">Audit-logged with actor & variance details.</p>
                                </div>
                                <button type="submit" class="kt-btn kt-btn-xs kt-btn-primary w-full">
                                    Record Spend Update
                                </button>
                            </form>
                        </details>
                    @endcan
                </div>
            </x-metronic.card>

            {{-- Timesheet & Time Tracking Card --}}
            <x-metronic.card title="Time Tracking & Timesheet ({{ $totalTimeHours }}h)" subtitle="Work hour allocations and logged time entries">
                <div class="space-y-3">
                    {{-- Log Time Quick Form --}}
                    @can('projects.update')
                        <form method="POST" action="{{ route('project-management.projects.time-entries.store', $project->uuid) }}" class="p-3 bg-muted/20 rounded-xl border border-border space-y-2">
                            @csrf
                            <p class="text-[11px] font-bold text-foreground">Log Work Hours</p>
                            <div>
                                <label class="block text-[10px] font-semibold text-muted-foreground mb-0.5">Deliverable *</label>
                                <select name="task_id" required class="kt-select w-full text-xs">
                                    <option value="">Select Deliverable</option>
                                    @foreach($project->tasks as $t)
                                        <option value="{{ $t->id }}">{{ $t->title }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-[10px] font-semibold text-muted-foreground mb-0.5">Date *</label>
                                    <input type="date" name="entry_date" value="{{ date('Y-m-d') }}" required class="kt-input w-full text-xs" />
                                </div>
                                <div>
                                    <label class="block text-[10px] font-semibold text-muted-foreground mb-0.5">Hours *</label>
                                    <input type="number" step="0.25" min="0.1" max="24" name="hours" placeholder="e.g. 3.5" required class="kt-input w-full text-xs" />
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] font-semibold text-muted-foreground mb-0.5">Activity Note</label>
                                <input type="text" name="note" placeholder="Description of work executed…" class="kt-input w-full text-xs" />
                            </div>
                            <button type="submit" class="kt-btn kt-btn-xs kt-btn-primary w-full">
                                Record Time Entry
                            </button>
                        </form>
                    @endcan

                    {{-- Recent Timesheet Entries --}}
                    <div class="space-y-1.5 max-h-56 overflow-y-auto">
                        @forelse($allTimeEntries as $entry)
                            <div class="p-2 rounded-lg border border-border bg-card flex items-center justify-between text-xs">
                                <div>
                                    <div class="flex items-center gap-1.5">
                                        <span class="font-bold text-primary">{{ round($entry->minutes / 60, 2) }}h</span>
                                        <span class="text-muted-foreground font-mono text-[10px]">{{ $entry->entry_date?->format('d/m') }}</span>
                                        <span class="font-medium text-foreground truncate max-w-[120px]">{{ $entry->user ? $entry->user->first_name : 'Staff' }}</span>
                                    </div>
                                    @if($entry->note)
                                        <p class="text-[10px] text-muted-foreground truncate max-w-[200px] mt-0.5">{{ $entry->note }}</p>
                                    @endif
                                </div>
                                <form method="POST" action="{{ route('project-management.projects.time-entries.destroy', ['project' => $project->uuid, 'timeEntry' => $entry->id]) }}" onsubmit="return confirm('Delete time entry?');">
                                    @csrf
                                    <button type="submit" class="text-muted-foreground hover:text-destructive text-sm px-1" title="Delete entry">
                                        &times;
                                    </button>
                                </form>
                            </div>
                        @empty
                            <p class="text-center py-3 text-muted-foreground text-xs italic">No time recorded on project deliverables yet.</p>
                        @endforelse
                    </div>
                </div>
            </x-metronic.card>

            {{-- Stage-Gate Governance Review Card --}}
            <x-metronic.card title="Stage-Gate Review" subtitle="Promote or update lifecycle state">
                <form method="POST" action="{{ route('project-management.projects.stage-gate', $project->uuid) }}" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold text-foreground mb-1">Target Lifecycle Stage</label>
                        <select name="status" class="kt-select w-full">
                            <option value="planned" @selected($project->status === 'planned')>Planned / Charter Phase</option>
                            <option value="in-progress" @selected($project->status === 'in-progress')>Active Execution</option>
                            <option value="on-hold" @selected($project->status === 'on-hold')>On Hold / Pending Steering</option>
                            <option value="completed" @selected($project->status === 'completed')>Completed / Gate Passed</option>
                            <option value="archived" @selected($project->status === 'archived')>Archived / Closed</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-foreground mb-1">Gate Reviewer Note</label>
                        <textarea
                            name="stage_note"
                            rows="2"
                            placeholder="Enter steering committee decision note or justification…"
                            class="kt-input w-full text-xs"
                        ></textarea>
                    </div>

                    <button type="submit" class="kt-btn kt-btn-primary w-full text-xs flex items-center justify-center gap-1.5">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Execute Stage-Gate Transition
                    </button>
                </form>
            </x-metronic.card>

            {{-- Project Leadership & Team Card --}}
            <x-metronic.card title="Project Team ({{ $project->members->count() }})" subtitle="Accountable director and contributors">
                <div class="space-y-3">
                    {{-- Owner / Project Lead --}}
                    <div class="p-3 rounded-xl border border-primary/20 bg-primary/5 flex items-center justify-between">
                        <div>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-primary">Executive Sponsor / Lead</span>
                            <p class="text-xs font-semibold text-foreground mt-0.5">
                                {{ $project->owner ? $project->owner->first_name . ' ' . $project->owner->last_name : 'Unassigned' }}
                            </p>
                            @if($project->owner)
                                <p class="text-[11px] text-muted-foreground">{{ $project->owner->email }}</p>
                            @endif
                        </div>
                    </div>

                    {{-- Members List --}}
                    @foreach($project->members as $member)
                        @if($member->user && (!$project->owner || $member->user->uuid !== $project->owner->uuid))
                            <div class="p-2.5 rounded-lg border border-border bg-card flex items-center justify-between text-xs">
                                <div>
                                    <p class="font-medium text-foreground">{{ $member->user->first_name }} {{ $member->user->last_name }}</p>
                                    <p class="text-[10px] text-muted-foreground">{{ $member->role ?: 'Contributor' }}</p>
                                </div>
                                <span class="text-[10px] text-muted-foreground font-mono">
                                    {{ $member->joined_at?->format('M Y') }}
                                </span>
                            </div>
                        @endif
                    @endforeach
                </div>

                {{-- Add Member Form --}}
                @can('projects.update')
                    <div class="mt-4 pt-3 border-t border-border">
                        <details class="group">
                            <summary class="cursor-pointer text-xs font-semibold text-primary hover:underline flex items-center gap-1.5 list-none">
                                <svg class="h-3.5 w-3.5 transition group-open:rotate-90" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                                Add Team Member
                            </summary>
                            <form method="POST" action="{{ route('project-management.projects.members.store', $project->uuid) }}" class="mt-3 space-y-2 p-3 bg-muted/20 rounded-xl border border-border">
                                @csrf
                                <div>
                                    <label class="block text-[11px] font-semibold text-foreground mb-1">Select User</label>
                                    <select name="user_id" required class="kt-select w-full text-xs">
                                        @foreach($users as $user)
                                            <option value="{{ $user->uuid }}">{{ $user->first_name }} {{ $user->last_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-foreground mb-1">Role / Designation</label>
                                    <input type="text" name="role" placeholder="e.g. Lead Architect, QA Analyst" class="kt-input w-full text-xs" />
                                </div>
                                <button type="submit" class="kt-btn kt-btn-sm kt-btn-primary w-full">
                                    Add Member
                                </button>
                            </form>
                        </details>
                    </div>
                @endcan
            </x-metronic.card>
        </div>
    </div>
</div>
@endsection
