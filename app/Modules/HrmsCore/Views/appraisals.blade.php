@extends('layouts.metronic.app')

@section('title', 'Performance Appraisals & Evaluation Cycles')

@section('content')
<div class="flex flex-col gap-6">
    <x-metronic.page-header
        title="Performance Appraisals & Review Cycles"
        description="Configure appraisal periods, rating scales, and evaluation templates; initiate employee reviews and track multi-stage appraisal workflows."
    >
        <x-slot:actions>
            <a href="{{ route('hrms-core.appraisals.index', ['scope' => 'mine']) }}" class="kt-btn {{ $scope === 'mine' ? 'kt-btn-primary' : 'kt-btn-outline' }}">
                My Appraisals
            </a>
            <a href="{{ route('hrms-core.appraisals.index') }}" class="kt-btn {{ $scope !== 'mine' ? 'kt-btn-primary' : 'kt-btn-outline' }}">
                All Accessible Appraisals
            </a>
            <a href="{{ route('hrms-core.index') }}" class="kt-btn kt-btn-outline">HRMS Hub</a>
        </x-slot:actions>
    </x-metronic.page-header>

    @if(session('status'))
        <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-sm text-emerald-700 dark:text-emerald-300">
            {{ session('status') }}
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-xl border border-destructive/30 bg-destructive/10 p-4 text-sm text-destructive">
            <ul class="list-disc pl-5 space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- KPI Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        <div class="rounded-xl border border-border bg-card p-4">
            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Total Appraisals</span>
            <p class="mt-2 text-2xl font-bold text-foreground">{{ $totalAppraisals }}</p>
        </div>
        <div class="rounded-xl border border-border bg-card p-4">
            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Self-Assessment</span>
            <p class="mt-2 text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $inSelfAssessment }}</p>
        </div>
        <div class="rounded-xl border border-border bg-card p-4">
            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">In Review</span>
            <p class="mt-2 text-2xl font-bold text-amber-600 dark:text-amber-400">{{ $inReview }}</p>
        </div>
        <div class="rounded-xl border border-border bg-card p-4">
            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Completed</span>
            <p class="mt-2 text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ $completedAppraisals }}</p>
        </div>
        <div class="rounded-xl border border-border bg-card p-4">
            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Active Periods</span>
            <p class="mt-2 text-2xl font-bold text-foreground">{{ $activePeriodsCount }}</p>
        </div>
    </div>

    {{-- My Appraisals Section --}}
    @if($actorEmployee !== null)
        <x-metronic.card title="My Appraisals ({{ $myAppraisals->count() }})" subtitle="Your personal performance evaluations and self-assessment dossiers">
            <div class="overflow-x-auto">
                <table class="kt-table table-auto kt-table-border w-full text-left text-xs">
                    <thead>
                        <tr class="uppercase text-muted-foreground border-b border-border">
                            <th class="py-2.5 px-3">Period</th>
                            <th class="py-2.5 px-3">Template</th>
                            <th class="py-2.5 px-3">Supervisor</th>
                            <th class="py-2.5 px-3">Status</th>
                            <th class="py-2.5 px-3 text-center">Self Score</th>
                            <th class="py-2.5 px-3 text-center">Final Score</th>
                            <th class="py-2.5 px-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse($myAppraisals as $ownAppraisal)
                            <tr>
                                <td class="py-2.5 px-3 font-semibold text-foreground">{{ $ownAppraisal->period?->name ?: '—' }}</td>
                                <td class="py-2.5 px-3 text-muted-foreground">{{ $ownAppraisal->template?->name ?: '—' }}</td>
                                <td class="py-2.5 px-3 text-muted-foreground">{{ $ownAppraisal->supervisor?->displayName() ?: 'Unassigned' }}</td>
                                <td class="py-2.5 px-3">
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-medium {{ $ownAppraisal->status->cssClass() }}">
                                        {{ $ownAppraisal->status->label() }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-3 text-center font-mono">{{ $ownAppraisal->self_overall_score ?? '—' }}</td>
                                <td class="py-2.5 px-3 text-center font-mono">
                                    @if($ownAppraisal->status->value === 'complete' || $isHrManage)
                                        <span class="font-bold text-foreground">{{ $ownAppraisal->final_overall_score ?? '—' }}</span>
                                    @else
                                        <span class="text-muted-foreground italic">Hidden until completed</span>
                                    @endif
                                </td>
                                <td class="py-2.5 px-3 text-right">
                                    <a href="{{ route('hrms-core.appraisals.show', $ownAppraisal) }}" class="kt-btn kt-btn-xs kt-btn-primary">
                                        Open My Appraisal
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-4 text-center text-muted-foreground">No personal appraisals have been assigned to you yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-metronic.card>
    @endif

    {{-- Initiate Employee Appraisal & Cycle Configuration --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <div class="lg:col-span-8 flex flex-col gap-6">
            {{-- Filter & Appraisals Registry --}}
            <x-metronic.card title="Employee Appraisals Registry" subtitle="Search and filter evaluations you are authorized to access">
                <form method="GET" action="{{ route('hrms-core.appraisals.index') }}" class="mb-4 grid grid-cols-1 sm:grid-cols-4 gap-3">
                    @if($scope !== '')
                        <input type="hidden" name="scope" value="{{ $scope }}" />
                    @endif
                    <input type="text" name="search" value="{{ $search }}" placeholder="Search employee or staff ID..." class="kt-input text-xs" />
                    <select name="period_id" class="kt-select text-xs">
                        <option value="">All Periods</option>
                        @foreach($periods as $periodOption)
                            <option value="{{ $periodOption->id }}" @selected($periodFilter === $periodOption->id)>{{ $periodOption->name }}</option>
                        @endforeach
                    </select>
                    <select name="status" class="kt-select text-xs">
                        <option value="">All Statuses</option>
                        @foreach($statuses as $statusOption)
                            <option value="{{ $statusOption->value }}" @selected($statusFilter === $statusOption->value)>{{ $statusOption->label() }}</option>
                        @endforeach
                    </select>
                    <div class="flex items-center gap-2">
                        <button type="submit" class="kt-btn kt-btn-sm kt-btn-primary">Filter</button>
                        <a href="{{ route('hrms-core.appraisals.index') }}" class="kt-btn kt-btn-sm kt-btn-outline">Reset</a>
                    </div>
                </form>

                <div class="overflow-x-auto">
                    <table class="kt-table table-auto kt-table-border w-full text-left text-xs">
                        <thead>
                            <tr class="uppercase text-muted-foreground border-b border-border">
                                <th class="py-3 px-3">Employee</th>
                                <th class="py-3 px-3">Period / Template</th>
                                <th class="py-3 px-3">Supervisor</th>
                                <th class="py-3 px-3">Status</th>
                                <th class="py-3 px-3 text-center">Scores (Self / Sup / Final)</th>
                                <th class="py-3 px-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @forelse($appraisals as $appraisal)
                                @php
                                    $hideSupervisorOnRow = ! $isHrManage
                                        && $actorEmployee !== null
                                        && $appraisal->employee_id === $actorEmployee->id
                                        && ! in_array($actorEmployee->id, [$appraisal->supervisor_id, $appraisal->hod_id, $appraisal->hr_reviewer_id], true)
                                        && $appraisal->status->value !== 'complete';
                                @endphp
                                <tr class="hover:bg-muted/30">
                                    <td class="py-3 px-3">
                                        <a href="{{ route('hrms-core.appraisals.show', $appraisal) }}" class="font-semibold text-foreground hover:text-primary">
                                            {{ $appraisal->employee?->displayName() ?: 'Employee' }}
                                        </a>
                                        <p class="text-[11px] text-muted-foreground font-mono">{{ $appraisal->employee?->employee_staff_id ?: '—' }}</p>
                                    </td>
                                    <td class="py-3 px-3">
                                        <p class="font-medium text-foreground">{{ $appraisal->period?->name ?: '—' }}</p>
                                        <p class="text-[11px] text-muted-foreground">{{ $appraisal->template?->name ?: '—' }}</p>
                                    </td>
                                    <td class="py-3 px-3 text-muted-foreground">
                                        {{ $appraisal->supervisor?->displayName() ?: 'Unassigned' }}
                                    </td>
                                    <td class="py-3 px-3">
                                        <span class="px-2 py-0.5 rounded-full text-[11px] font-medium {{ $appraisal->status->cssClass() }}">
                                            {{ $appraisal->status->label() }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-3 text-center font-mono text-[11px]">
                                        @if($hideSupervisorOnRow)
                                            {{ $appraisal->self_overall_score ?? '—' }} / <span class="text-muted-foreground italic">Hidden</span>
                                        @else
                                            {{ $appraisal->self_overall_score ?? '—' }} / {{ $appraisal->supervisor_overall_score ?? '—' }} / <span class="font-bold text-foreground">{{ $appraisal->final_overall_score ?? '—' }}</span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-3 text-right">
                                        <a href="{{ route('hrms-core.appraisals.show', $appraisal) }}" class="kt-btn kt-btn-xs kt-btn-outline">
                                            Open Dossier
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-8 text-center text-muted-foreground">
                                        No appraisal records match your filter criteria.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $appraisals->links() }}
                </div>
            </x-metronic.card>

            @if($isHrManage)
                {{-- Periods & Rating Scales --}}
                <x-metronic.card title="Appraisal Periods & Rating Scales" subtitle="Review cycles and 1–5 performance rating scale definitions">
                    <div class="space-y-4">
                        <div>
                            <h4 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-2">Configured Periods ({{ $periods->count() }})</h4>
                            <div class="overflow-x-auto">
                                <table class="kt-table table-auto kt-table-border w-full text-left text-xs">
                                    <thead>
                                        <tr class="uppercase text-muted-foreground border-b border-border">
                                            <th class="py-2 px-3">Name</th>
                                            <th class="py-2 px-3">Cycle</th>
                                            <th class="py-2 px-3">Date Window</th>
                                            <th class="py-2 px-3 text-center">Appraisals</th>
                                            <th class="py-2 px-3 text-center">Scales</th>
                                            <th class="py-2 px-3">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border">
                                        @forelse($periods as $periodItem)
                                            <tr>
                                                <td class="py-2 px-3 font-semibold text-foreground">{{ $periodItem->name }}</td>
                                                <td class="py-2 px-3">{{ $periodItem->cycle->label() }}</td>
                                                <td class="py-2 px-3 text-muted-foreground">{{ $periodItem->start_date->format('Y-m-d') }} &rarr; {{ $periodItem->end_date->format('Y-m-d') }}</td>
                                                <td class="py-2 px-3 text-center">{{ $periodItem->appraisals_count }}</td>
                                                <td class="py-2 px-3 text-center">{{ $periodItem->rating_scales_count }}</td>
                                                <td class="py-2 px-3">
                                                    <x-metronic.badge :variant="$periodItem->is_active ? 'success' : 'warning'" size="sm">
                                                        {{ $periodItem->is_active ? 'Active' : 'Closed' }}
                                                    </x-metronic.badge>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="py-4 text-center text-muted-foreground">No appraisal periods configured yet.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="pt-3 border-t border-border">
                            <h4 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-2">Rating Scale Definitions ({{ $ratingScales->count() }})</h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2.5">
                                @forelse($ratingScales as $scale)
                                    <div class="rounded-lg border border-border p-2.5 flex items-start justify-between gap-2">
                                        <div>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold {{ $scale->getCssClass() }}">
                                                {{ $scale->value }} — {{ $scale->label }}
                                            </span>
                                            <p class="mt-1 text-[11px] text-muted-foreground">{{ $scale->description ?: 'Standard rating scale' }}</p>
                                            <p class="text-[10px] text-muted-foreground font-mono mt-0.5">{{ $scale->period?->name ?: 'Global Default' }}</p>
                                        </div>
                                        @can('hrms.appraisal.manage')
                                            <form method="POST" action="{{ route('hrms-core.appraisals.rating-scales.destroy', $scale) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-[11px] text-destructive hover:underline">Delete</button>
                                            </form>
                                        @endcan
                                    </div>
                                @empty
                                    <p class="text-xs text-muted-foreground col-span-3">No rating scales defined yet.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </x-metronic.card>
            @endif
        </div>

        {{-- Right Column: Action Forms --}}
        <div class="lg:col-span-4 flex flex-col gap-6">
            @if(auth()->user()?->can('hrms.appraisal.create') || auth()->user()?->can('hrms.appraisal.manage'))
                <x-metronic.card title="Initiate Employee Appraisal" subtitle="Start a new evaluation dossier for a staff member">
                    <form method="POST" action="{{ route('hrms-core.appraisals.store') }}" class="space-y-3 text-xs">
                        @csrf
                        <div>
                            <label class="block font-semibold text-foreground mb-1">Employee *</label>
                            <select name="employee_id" required class="kt-select w-full text-xs">
                                <option value="">Select employee...</option>
                                @foreach($employees as $emp)
                                    <option value="{{ $emp->id }}">{{ $emp->first_name }} {{ $emp->last_name }} ({{ $emp->employee_staff_id ?: 'No ID' }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block font-semibold text-foreground mb-1">Appraisal Period *</label>
                            <select name="period_id" required class="kt-select w-full text-xs">
                                <option value="">Select period...</option>
                                @foreach($periods as $periodOpt)
                                    <option value="{{ $periodOpt->id }}">{{ $periodOpt->name }} ({{ $periodOpt->cycle->label() }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block font-semibold text-foreground mb-1">Template *</label>
                            <select name="template_id" required class="kt-select w-full text-xs">
                                <option value="">Select template...</option>
                                @foreach($templates as $tplOpt)
                                    <option value="{{ $tplOpt->id }}">{{ $tplOpt->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block font-semibold text-foreground mb-1">Supervisor Reviewer</label>
                            <select name="supervisor_id" class="kt-select w-full text-xs">
                                <option value="">Optional supervisor...</option>
                                @foreach($employees as $emp)
                                    <option value="{{ $emp->id }}">{{ $emp->first_name }} {{ $emp->last_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block font-semibold text-foreground mb-1">HOD Reviewer</label>
                            <select name="hod_id" class="kt-select w-full text-xs">
                                <option value="">Optional HOD...</option>
                                @foreach($employees as $emp)
                                    <option value="{{ $emp->id }}">{{ $emp->first_name }} {{ $emp->last_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block font-semibold text-foreground mb-1">HR Reviewer</label>
                            <select name="hr_reviewer_id" class="kt-select w-full text-xs">
                                <option value="">Optional HR reviewer...</option>
                                @foreach($employees as $emp)
                                    <option value="{{ $emp->id }}">{{ $emp->first_name }} {{ $emp->last_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="kt-btn kt-btn-primary w-full justify-center">Initiate Appraisal</button>
                    </form>
                </x-metronic.card>
            @endif

            @can('hrms.appraisal.manage')
                <x-metronic.card title="Create Appraisal Period" subtitle="Define a review window and seed default 1–5 scales">
                    <form method="POST" action="{{ route('hrms-core.appraisals.periods.store') }}" class="space-y-3 text-xs">
                        @csrf
                        <div>
                            <label class="block font-semibold text-foreground mb-1">Period Name *</label>
                            <input type="text" name="name" required placeholder="e.g. Annual Review 2026" class="kt-input w-full text-xs" />
                        </div>
                        <div>
                            <label class="block font-semibold text-foreground mb-1">Cycle *</label>
                            <select name="cycle" required class="kt-select w-full text-xs">
                                @foreach($cycles as $cycleCase)
                                    <option value="{{ $cycleCase->value }}">{{ $cycleCase->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block font-semibold text-foreground mb-1">Start Date *</label>
                                <input type="date" name="start_date" required class="kt-input w-full text-xs" />
                            </div>
                            <div>
                                <label class="block font-semibold text-foreground mb-1">End Date *</label>
                                <input type="date" name="end_date" required class="kt-input w-full text-xs" />
                            </div>
                        </div>
                        <div>
                            <label class="block font-semibold text-foreground mb-1">Description</label>
                            <textarea name="description" rows="2" class="kt-textarea w-full text-xs"></textarea>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="inline-flex items-center gap-2">
                                <input type="checkbox" name="seed_default_scales" value="1" checked />
                                <span>Seed default 1–5 rating scales</span>
                            </label>
                            <label class="inline-flex items-center gap-2">
                                <input type="checkbox" name="create_default_template" value="1" checked />
                                <span>Create default template for period</span>
                            </label>
                        </div>
                        <button type="submit" class="kt-btn kt-btn-outline w-full justify-center">Create Period</button>
                    </form>
                </x-metronic.card>

                <x-metronic.card title="Add / Update Rating Scale" subtitle="Define or customize a 1–5 rating level">
                    <form method="POST" action="{{ route('hrms-core.appraisals.rating-scales.store') }}" class="space-y-3 text-xs">
                        @csrf
                        <div>
                            <label class="block font-semibold text-foreground mb-1">Period (Optional)</label>
                            <select name="period_id" class="kt-select w-full text-xs">
                                <option value="">Global Default</option>
                                @foreach($periods as $periodOpt)
                                    <option value="{{ $periodOpt->id }}">{{ $periodOpt->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="grid grid-cols-3 gap-2">
                            <div>
                                <label class="block font-semibold text-foreground mb-1">Score (1–5) *</label>
                                <input type="number" name="value" min="1" max="5" required value="3" class="kt-input w-full text-xs" />
                            </div>
                            <div class="col-span-2">
                                <label class="block font-semibold text-foreground mb-1">Label *</label>
                                <input type="text" name="label" required placeholder="Meets Expectations" class="kt-input w-full text-xs" />
                            </div>
                        </div>
                        <div>
                            <label class="block font-semibold text-foreground mb-1">Description</label>
                            <input type="text" name="description" class="kt-input w-full text-xs" />
                        </div>
                        <button type="submit" class="kt-btn kt-btn-outline w-full justify-center">Save Rating Scale</button>
                    </form>
                </x-metronic.card>
            @endcan
        </div>
    </div>
</div>
@endsection
