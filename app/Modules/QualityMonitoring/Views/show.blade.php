@extends('layouts.metronic.app')

@section('title', $workplan->title . ' — Quality Workspace')

@section('content')
<div class="flex flex-col gap-5">
    {{-- Page Header --}}
    <x-metronic.page-header
        :title="$workplan->title"
        description="Institutional Quality Programme Workspace • Period: {{ $workplan->period_start?->format('d/m/Y') }} – {{ $workplan->period_end?->format('d/m/Y') }}"
    >
        <x-slot:actions>
            <a href="{{ route('quality-monitoring.workplans.index') }}" class="kt-btn kt-btn-outline">
                Back to Register
            </a>

            @if($workplan->status === 'draft')
                @can('qm.workplans.manage')
                    <form action="{{ route('quality-monitoring.workplans.submit', $workplan) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="kt-btn kt-btn-primary flex items-center gap-1.5" onclick="return confirm('Submit workplan to Quality Committee for review?')">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Submit for Review
                        </button>
                    </form>
                @endcan
            @elseif($workplan->status === 'submitted')
                @can('qm.approvals.manage')
                    <button type="button" onclick="document.getElementById('committee-review-section').scrollIntoView({behavior: 'smooth'})" class="kt-btn kt-btn-success flex items-center gap-1.5">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        Committee Review
                    </button>
                @endcan
            @endif
        </x-slot:actions>
    </x-metronic.page-header>

    {{-- Top Governance Summary Banner --}}
    <div class="rounded-xl border border-border bg-card p-4">
        <div class="flex flex-wrap items-center justify-between gap-4 text-xs">
            <div class="flex flex-wrap items-center gap-4">
                <div class="flex items-center gap-2">
                    <span class="text-muted-foreground uppercase tracking-wider font-semibold">Status:</span>
                    @php
                        $statusVariant = match($workplan->status) {
                            'active', 'approved' => 'success',
                            'submitted' => 'primary',
                            'draft' => 'outline',
                            'rejected' => 'destructive',
                            default => 'outline',
                        };
                    @endphp
                    <x-metronic.badge :variant="$statusVariant" size="sm">
                        {{ ucfirst($workplan->status) }}
                    </x-metronic.badge>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-muted-foreground uppercase tracking-wider font-semibold">Version:</span>
                    <span class="font-mono font-semibold text-foreground">
                        v{{ $workplan->versions->first()?->version_no ?? 1 }}
                    </span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-muted-foreground uppercase tracking-wider font-semibold">Scope:</span>
                    <span class="text-foreground font-medium">
                        {{ $workplan->org_scope['unit'] ?? 'Hospital-Wide' }}
                    </span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-muted-foreground uppercase tracking-wider font-semibold">Owner:</span>
                    <span class="text-foreground font-medium">
                        {{ $workplan->owner ? $workplan->owner->first_name.' '.$workplan->owner->last_name : 'Quality Directorate' }}
                    </span>
                </div>
            </div>
            <div class="text-muted-foreground text-[11px]">
                UUID: <span class="font-mono text-foreground">{{ $workplan->uuid }}</span>
            </div>
        </div>
    </div>

    {{-- Main Workspace Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
        {{-- Left 8 cols: Strategic Objectives, KPIs & Variances --}}
        <div class="lg:col-span-8 flex flex-col gap-5">
            {{-- Objectives & Monitored KPIs --}}
            <x-metronic.card
                title="Strategic Objectives & Performance Indicators"
                subtitle="Clinical and operational target metrics under continuous evaluation"
            >
                <div class="space-y-6">
                    @forelse($workplan->objectives as $objective)
                        <div class="rounded-xl border border-border p-4 bg-muted/10 space-y-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-bold uppercase tracking-wider text-primary">Objective {{ $loop->iteration }}</span>
                                        <x-metronic.badge variant="outline" size="sm">Weight: {{ $objective->weight }}%</x-metronic.badge>
                                    </div>
                                    <h4 class="mt-1 text-sm font-semibold text-foreground">{{ $objective->title }}</h4>
                                    @if($objective->description)
                                        <p class="mt-1 text-xs text-muted-foreground">{{ $objective->description }}</p>
                                    @endif
                                </div>
                            </div>

                            {{-- Child Activities & KPIs --}}
                            <div class="space-y-4 pt-2 border-t border-border/80">
                                @forelse($objective->activities as $activity)
                                    <div class="p-3 rounded-lg border border-border bg-card space-y-3">
                                        <div class="flex items-center justify-between text-xs">
                                            <span class="font-semibold text-foreground">{{ $activity->title }}</span>
                                            <div class="flex items-center gap-2">
                                                <span class="text-[11px] text-muted-foreground">
                                                    Lead: {{ $activity->responsible ? $activity->responsible->first_name.' '.$activity->responsible->last_name : 'Assigned Staff' }}
                                                </span>
                                                <x-metronic.badge variant="outline" size="sm">{{ ucfirst($activity->status) }}</x-metronic.badge>
                                            </div>
                                        </div>

                                        {{-- KPIs --}}
                                        <div class="space-y-2.5">
                                            @forelse($activity->kpis as $kpi)
                                                <div class="p-3 rounded-md border border-border/60 bg-muted/20 text-xs space-y-2">
                                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                                        <div>
                                                            <span class="font-semibold text-foreground">{{ $kpi->name }}</span>
                                                            <span class="text-muted-foreground text-[11px] ml-1">({{ $kpi->unit ?? 'Rate/Count' }})</span>
                                                        </div>
                                                        <div class="flex items-center gap-3">
                                                            <span class="text-muted-foreground text-[11px]">
                                                                Target: <strong class="text-foreground">{{ $kpi->target_value ?? 'N/A' }}</strong>
                                                            </span>
                                                            <span class="text-muted-foreground text-[11px]">
                                                                Baseline: <strong class="text-foreground">{{ $kpi->baseline_value ?? 'N/A' }}</strong>
                                                            </span>
                                                            @can('qm.kpis.manage')
                                                                <button
                                                                    type="button"
                                                                    onclick="document.getElementById('kpi-form-{{ $kpi->id }}').classList.toggle('hidden')"
                                                                    class="kt-btn kt-btn-outline py-0.5 px-2 text-[11px]"
                                                                >
                                                                    + Log Measurement
                                                                </button>
                                                            @endcan
                                                        </div>
                                                    </div>

                                                    {{-- Latest Measurement Value --}}
                                                    @php
                                                        $latestUpdate = $kpi->updates->last();
                                                    @endphp
                                                    @if($latestUpdate)
                                                        <div class="flex items-center justify-between pt-1 border-t border-border/40 text-[11px]">
                                                            <div class="flex items-center gap-2">
                                                                <span class="text-muted-foreground">Latest Value:</span>
                                                                <span class="font-bold text-foreground">{{ $latestUpdate->value }} {{ $kpi->unit }}</span>
                                                                @if($latestUpdate->note)
                                                                    <span class="text-muted-foreground italic">"{{ $latestUpdate->note }}"</span>
                                                                @endif
                                                            </div>
                                                            <span class="text-muted-foreground">
                                                                {{ $latestUpdate->captured_at?->format('d/m/Y H:i') }} by {{ $latestUpdate->capturedBy?->first_name ?? 'Auditor' }}
                                                            </span>
                                                        </div>
                                                    @endif

                                                    {{-- Log Measurement Form (Collapsible) --}}
                                                    <form
                                                        id="kpi-form-{{ $kpi->id }}"
                                                        action="{{ route('quality-monitoring.workplans.kpi-update', [$workplan, $kpi]) }}"
                                                        method="POST"
                                                        class="hidden pt-2 border-t border-border/60 space-y-2"
                                                    >
                                                        @csrf
                                                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                                            <div>
                                                                <input
                                                                    type="number"
                                                                    step="any"
                                                                    name="value"
                                                                    required
                                                                    placeholder="Value (e.g. 98.5)"
                                                                    class="kt-input w-full text-xs"
                                                                />
                                                            </div>
                                                            <div>
                                                                <input
                                                                    type="datetime-local"
                                                                    name="captured_at"
                                                                    value="{{ now()->format('Y-m-d\TH:i') }}"
                                                                    class="kt-input w-full text-xs"
                                                                />
                                                            </div>
                                                            <div class="flex gap-1.5">
                                                                <input
                                                                    type="text"
                                                                    name="note"
                                                                    placeholder="Audit source / notes"
                                                                    class="kt-input w-full text-xs"
                                                                />
                                                                <button type="submit" class="kt-btn kt-btn-primary py-1 px-2.5 text-xs shrink-0">
                                                                    Save
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </form>
                                                </div>
                                            @empty
                                                <p class="text-[11px] text-muted-foreground italic">No KPIs assigned to this activity.</p>
                                            @endforelse
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-xs text-muted-foreground">No operational activities recorded under this objective.</p>
                                @endforelse
                            </div>
                        </div>
                    @empty
                        <div class="py-8 text-center text-muted-foreground">
                            <p class="text-xs">No strategic objectives structured yet.</p>
                        </div>
                    @endforelse
                </div>
            </x-metronic.card>

            {{-- Quality Variances & CAPA Root Cause Section --}}
            <x-metronic.card
                title="Quality Variances & Corrective Action Plans (CAPA)"
                subtitle="Root-cause documentation for indicator deviations and non-conformances"
            >
                <div class="space-y-4">
                    <div class="space-y-3">
                        @forelse($workplan->variances as $variance)
                            <div class="p-4 rounded-xl border border-border bg-card space-y-2 text-xs">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-foreground uppercase tracking-wider">{{ $variance->category }}</span>
                                        <x-metronic.badge variant="outline" size="sm">Impact: {{ ucfirst($variance->impact_level) }}</x-metronic.badge>
                                        @if($variance->kpi)
                                            <span class="text-muted-foreground text-[11px]">KPI: {{ $variance->kpi->name }}</span>
                                        @endif
                                    </div>
                                    <x-metronic.badge :variant="$variance->status === 'approved' ? 'success' : 'outline'" size="sm">
                                        {{ ucfirst($variance->status) }}
                                    </x-metronic.badge>
                                </div>
                                <p class="text-foreground leading-relaxed">
                                    <strong>Variance Narrative:</strong> {{ $variance->narrative }}
                                </p>
                                <div class="p-3 rounded-lg border border-primary/20 bg-primary/5 text-xs text-foreground">
                                    <strong class="text-primary font-semibold">CAPA Remediation:</strong> {{ $variance->corrective_action }}
                                </div>
                                <div class="flex items-center justify-between text-[11px] text-muted-foreground pt-1">
                                    <span>Target Revised Date: {{ $variance->revised_date?->format('d/m/Y') ?: 'Immediate' }}</span>
                                    <span>Logged {{ $variance->created_at?->diffForHumans() }}</span>
                                </div>
                            </div>
                        @empty
                            <div class="py-6 text-center text-muted-foreground">
                                <p class="text-xs">Zero quality variances recorded. All indicators operating within defined baseline.</p>
                            </div>
                        @endforelse
                    </div>

                    {{-- Log Quality Variance Form --}}
                    @can('qm.variances.manage')
                        <div class="pt-4 border-t border-border">
                            <h4 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-3">Log Quality Variance & CAPA</h4>
                            <form action="{{ route('quality-monitoring.workplans.variance', $workplan) }}" method="POST" class="space-y-3">
                                @csrf
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                    <div>
                                        <label class="block text-[11px] font-semibold text-muted-foreground uppercase mb-1">Category</label>
                                        <select name="category" required class="kt-select w-full text-xs">
                                            <option value="Clinical Indicator Shortfall">Clinical Indicator Shortfall</option>
                                            <option value="Surgical / Operative Non-conformance">Surgical / Operative Non-conformance</option>
                                            <option value="Medication Safety Variance">Medication Safety Variance</option>
                                            <option value="Infection Control Threshold">Infection Control Threshold</option>
                                            <option value="Operational Process Delay">Operational Process Delay</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-semibold text-muted-foreground uppercase mb-1">Impact Level</label>
                                        <select name="impact_level" required class="kt-select w-full text-xs">
                                            <option value="low">Low Impact</option>
                                            <option value="moderate">Moderate Impact</option>
                                            <option value="high">High Impact</option>
                                            <option value="critical">Critical / Sentinel Risk</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-semibold text-muted-foreground uppercase mb-1">Revised Target Date</label>
                                        <input type="date" name="revised_date" class="kt-input w-full text-xs" />
                                    </div>
                                </div>

                                <div>
                                    <textarea
                                        name="narrative"
                                        rows="2"
                                        required
                                        placeholder="Describe root-cause analysis findings and observed indicator variance..."
                                        class="kt-textarea w-full text-xs"
                                    ></textarea>
                                </div>

                                <div>
                                    <textarea
                                        name="corrective_action"
                                        rows="2"
                                        required
                                        placeholder="Specific Corrective & Preventive Action (CAPA) steps committed by clinical leadership..."
                                        class="kt-textarea w-full text-xs"
                                    ></textarea>
                                </div>

                                <div class="flex justify-end">
                                    <button type="submit" class="kt-btn kt-btn-primary py-1.5 px-3 text-xs">
                                        Log Variance & CAPA
                                    </button>
                                </div>
                            </form>
                        </div>
                    @endcan
                </div>
            </x-metronic.card>
        </div>

        {{-- Right 4 cols: Committee Governance, Alerts, and Version History --}}
        <div class="lg:col-span-4 flex flex-col gap-5">
            {{-- Stage-Gate Committee Review Section --}}
            <div id="committee-review-section">
                <x-metronic.card
                    title="Committee Review & Decision"
                    subtitle="Formal governance and accreditation deliberation"
                >
                    <div class="space-y-4">
                        {{-- Existing Review Records --}}
                        <div class="space-y-3">
                            @forelse($workplan->reviews as $review)
                                <div class="p-3 rounded-lg border border-border bg-muted/20 space-y-1 text-xs">
                                    <div class="flex items-center justify-between">
                                        <span class="font-semibold text-foreground">
                                            {{ $review->reviewer ? $review->reviewer->first_name.' '.$review->reviewer->last_name : 'Committee Member' }}
                                        </span>
                                        <x-metronic.badge :variant="$review->status === 'approved' ? 'success' : 'destructive'" size="sm">
                                            {{ ucfirst($review->status) }}
                                        </x-metronic.badge>
                                    </div>
                                    <p class="text-foreground leading-relaxed">{{ $review->comments }}</p>
                                    <span class="text-[11px] text-muted-foreground block text-right">
                                        {{ $review->created_at?->diffForHumans() }}
                                    </span>
                                </div>
                            @empty
                                <div class="py-4 text-center text-muted-foreground text-xs">
                                    No formal committee review records on file.
                                </div>
                            @endforelse
                        </div>

                        {{-- Action Controls for Committee Members --}}
                        @can('qm.approvals.manage')
                            <div class="pt-3 border-t border-border space-y-2">
                                <h4 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Deliberation Decision</h4>
                                <form action="{{ route('quality-monitoring.workplans.approve', $workplan) }}" method="POST" class="space-y-2">
                                    @csrf
                                    <textarea
                                        name="comments"
                                        rows="2"
                                        required
                                        placeholder="Approval commendations and committee verification remarks..."
                                        class="kt-textarea w-full text-xs"
                                    ></textarea>
                                    <div class="grid grid-cols-2 gap-2">
                                        <button type="submit" class="kt-btn kt-btn-success py-1.5 text-xs justify-center flex items-center gap-1">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                            </svg>
                                            Approve
                                        </button>
                                        <button
                                            type="submit"
                                            formaction="{{ route('quality-monitoring.workplans.reject', $workplan) }}"
                                            class="kt-btn kt-btn-outline text-destructive py-1.5 text-xs justify-center flex items-center gap-1"
                                        >
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                            Reject
                                        </button>
                                    </div>
                                </form>
                            </div>
                        @endcan
                    </div>
                </x-metronic.card>
            </div>

            {{-- Quality Alerts Linked to this Workplan --}}
            <x-metronic.card title="Workplan Alerts" subtitle="Open threshold breaches">
                <div class="space-y-3">
                    @forelse($workplan->alerts as $alert)
                        <div class="p-3 rounded-lg border border-destructive/20 bg-destructive/5 space-y-1.5 text-xs">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-destructive uppercase tracking-wider">{{ str_replace('_', ' ', $alert->type) }}</span>
                                <x-metronic.badge variant="destructive" size="sm">{{ ucfirst($alert->status) }}</x-metronic.badge>
                            </div>
                            <p class="text-foreground">
                                Indicator: <strong>{{ $alert->kpi?->name ?? 'General Threshold' }}</strong>
                            </p>
                            @if($alert->metadata)
                                <p class="text-[11px] text-muted-foreground">
                                    Target: {{ $alert->metadata['target'] ?? 'N/A' }} | Recorded: {{ $alert->metadata['recorded_value'] ?? 'N/A' }}
                                </p>
                            @endif
                            <div class="flex items-center justify-between pt-1">
                                <span class="text-[11px] text-muted-foreground">{{ $alert->created_at?->diffForHumans() }}</span>
                                @if($alert->status === 'open')
                                    <form action="{{ route('quality-monitoring.alerts.acknowledge', $alert) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="text-[11px] font-semibold text-primary hover:underline">
                                            Acknowledge
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="py-4 text-center text-muted-foreground text-xs">
                            No active alerts for this workplan.
                        </div>
                    @endforelse
                </div>
            </x-metronic.card>

            {{-- Workplan Version Audit History --}}
            <x-metronic.card title="Version Audit Trail" subtitle="Immutable submission milestones">
                <div class="space-y-2 text-xs">
                    @forelse($workplan->versions as $version)
                        <div class="flex items-center justify-between py-2 border-b border-border/60 last:border-0">
                            <div>
                                <span class="font-mono font-semibold text-foreground">v{{ $version->version_no }}</span>
                                <span class="text-muted-foreground ml-1.5 capitalize">({{ $version->status }})</span>
                            </div>
                            <span class="text-[11px] text-muted-foreground">
                                {{ $version->created_at?->format('d/m/Y') }}
                            </span>
                        </div>
                    @empty
                        <p class="text-xs text-muted-foreground text-center py-2">No version snapshots recorded.</p>
                    @endforelse
                </div>
            </x-metronic.card>

            {{-- Quality Monitoring Evidence Vault --}}
            <x-metronic.card title="Evidence Vault" subtitle="Auditable clinical & operational artifacts">
                <div class="space-y-3 text-xs">
                    @php
                        $evidenceList = $workplan->metadata['evidence_files'] ?? [];
                    @endphp

                    @forelse($evidenceList as $evidence)
                        <div class="p-3 rounded-lg border border-border bg-muted/20 space-y-1">
                            <div class="flex items-center justify-between">
                                <a href="{{ route('quality-monitoring.workplans.evidence.download', ['workplan' => $workplan, 'fileId' => $evidence['id']]) }}" class="font-semibold text-primary hover:underline truncate max-w-[200px]" title="{{ $evidence['name'] }}">
                                    {{ $evidence['name'] }}
                                </a>
                                <span class="text-[10px] text-muted-foreground">{{ number_format(($evidence['size_bytes'] ?? 0) / 1024, 1) }} KB</span>
                            </div>
                            @if(!empty($evidence['description']))
                                <p class="text-[11px] text-muted-foreground">{{ $evidence['description'] }}</p>
                            @endif
                            <div class="flex items-center justify-between text-[10px] text-muted-foreground pt-1">
                                <span>By: {{ $evidence['uploaded_by_name'] ?? 'User' }}</span>
                                <span>{{ \Carbon\Carbon::parse($evidence['uploaded_at'])->format('d/m/Y H:i') }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="py-4 text-center text-muted-foreground text-xs">
                            No evidence files uploaded to this vault yet.
                        </div>
                    @endforelse

                    @can('qm.workplans.manage')
                        <div class="pt-3 border-t border-border/60">
                            <form action="{{ route('quality-monitoring.workplans.evidence.upload', $workplan) }}" method="POST" enctype="multipart/form-data" class="space-y-3">
                                @csrf
                                <div>
                                    <label class="block text-[11px] font-semibold text-foreground mb-1">Upload New Evidence</label>
                                    <input type="file" name="evidence_file" required class="block w-full text-xs text-muted-foreground file:mr-2 file:py-1 file:px-2 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-primary/10 file:text-primary hover:file:bg-primary/20" />
                                    <p class="text-[10px] text-muted-foreground mt-0.5">PDF, PNG, JPG, DOCX, XLSX, ZIP (Max 10MB)</p>
                                </div>
                                <div>
                                    <input type="text" name="description" placeholder="Brief document description..." class="w-full text-xs rounded-md border border-input bg-background px-2.5 py-1.5 focus:outline-none focus:ring-1 focus:ring-ring" />
                                </div>
                                <button type="submit" class="kt-btn kt-btn-outline w-full py-1.5 text-xs justify-center flex items-center gap-1.5">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                    </svg>
                                    Upload to Vault
                                </button>
                            </form>
                        </div>
                    @endcan
                </div>
            </x-metronic.card>
        </div>
    </div>
</div>
@endsection

