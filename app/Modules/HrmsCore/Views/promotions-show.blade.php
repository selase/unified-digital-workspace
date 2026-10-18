@extends('layouts.metronic.app')

@section('title', 'Promotion Review - ' . ($promotion->employee?->displayName() ?: 'Staff Member'))

@section('content')
<div class="flex flex-col gap-6">
    <x-metronic.page-header
        title="Promotion Dossier: {{ $promotion->employee?->displayName() ?: 'Employee' }}"
        description="{{ $promotion->category->label() }} &bull; {{ $promotion->status->label() }}"
    >
        <x-slot:actions>
            <a href="{{ route('hrms-core.promotions.index') }}" class="kt-btn kt-btn-outline">Back to Promotions</a>
            @if($promotion->status === \App\Modules\HrmsCore\Enums\PromotionStatus::Pending)
                <form method="POST" action="{{ route('hrms-core.promotions.submit', $promotion) }}" class="inline">
                    @csrf
                    <button type="submit" class="kt-btn kt-btn-primary">Submit for Supervisor Review</button>
                </form>
                <form method="POST" action="{{ route('hrms-core.promotions.destroy', $promotion) }}" class="inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="kt-btn kt-btn-outline text-rose-600">Delete Draft</button>
                </form>
            @endif
        </x-slot:actions>
    </x-metronic.page-header>

    @if(session('status'))
        <div class="rounded-xl border border-emerald-500/20 bg-emerald-500/10 p-4 text-emerald-800 dark:text-emerald-300 text-sm">
            {{ session('status') }}
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-xl border border-rose-500/20 bg-rose-500/10 p-4 text-rose-800 dark:text-rose-300 text-sm">
            <ul class="list-disc list-inside space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        {{-- Left 7 Columns: Promotion Details & Approval Audit --}}
        <div class="lg:col-span-7 flex flex-col gap-6">
            <x-metronic.card title="Progression Summary" subtitle="Current vs proposed grade and salary scale">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                    <div class="rounded-xl border border-border p-3.5">
                        <span class="text-[11px] uppercase font-semibold text-muted-foreground">Grade Progression</span>
                        <p class="mt-1 text-sm font-bold text-foreground">
                            {{ $promotion->fromGrade?->name ?: 'Unassigned' }}
                            &rarr;
                            <span class="text-primary">{{ $promotion->toGrade?->name ?: 'Unchanged' }}</span>
                        </p>
                    </div>
                    <div class="rounded-xl border border-border p-3.5">
                        <span class="text-[11px] uppercase font-semibold text-muted-foreground">Salary Scale Progression</span>
                        <p class="mt-1 text-sm font-bold text-foreground">
                            {{ $promotion->fromSalaryLevel?->name ?: 'Unassigned' }}
                            &rarr;
                            <span class="text-primary">{{ $promotion->toSalaryLevel?->name ?: 'Unchanged' }}</span>
                        </p>
                    </div>
                    <div class="rounded-xl border border-border p-3.5">
                        <span class="text-[11px] uppercase font-semibold text-muted-foreground">Requested / Effective Date</span>
                        <p class="mt-1 font-mono text-foreground">
                            Requested: {{ $promotion->requested_date?->format('Y-m-d') ?: '—' }}
                            &bull;
                            Effective: {{ $promotion->effective_date?->format('Y-m-d') ?: 'TBD' }}
                        </p>
                    </div>
                    <div class="rounded-xl border border-border p-3.5">
                        <span class="text-[11px] uppercase font-semibold text-muted-foreground">Workflow Stage</span>
                        <p class="mt-1">
                            <span class="px-2 py-0.5 rounded text-xs font-semibold {{ $promotion->status->cssClass() }}">
                                {{ $promotion->status->label() }}
                            </span>
                        </p>
                    </div>
                </div>

                <div class="mt-4 pt-4 border-t border-border space-y-3 text-xs">
                    <div>
                        <span class="font-semibold uppercase text-muted-foreground block mb-1">Reason for Request</span>
                        <p class="text-foreground">{{ $promotion->reason ?: '—' }}</p>
                    </div>
                    @if($promotion->justification)
                        <div>
                            <span class="font-semibold uppercase text-muted-foreground block mb-1">Supporting Justification</span>
                            <p class="text-foreground">{{ $promotion->justification }}</p>
                        </div>
                    @endif
                    @if($promotion->rejection_reason)
                        <div class="rounded-lg border border-rose-500/30 bg-rose-500/10 p-3 text-rose-800 dark:text-rose-300">
                            <span class="font-semibold uppercase block mb-1">Rejection Reason</span>
                            <p>{{ $promotion->rejection_reason }}</p>
                        </div>
                    @endif
                </div>
            </x-metronic.card>

            <x-metronic.card title="Approval Trail" subtitle="Supervisor recommendation and HR final decision">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                    <div class="rounded-xl border border-border p-4 space-y-1.5">
                        <div class="flex items-center justify-between">
                            <span class="font-semibold uppercase text-muted-foreground">1. Supervisor Review</span>
                            @if($promotion->supervisor_approved === true)
                                <span class="px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-700 font-semibold">Recommended</span>
                            @elseif($promotion->supervisor_approved === false)
                                <span class="px-2 py-0.5 rounded bg-rose-500/10 text-rose-700 font-semibold">Rejected</span>
                            @else
                                <span class="px-2 py-0.5 rounded bg-muted text-muted-foreground">Pending</span>
                            @endif
                        </div>
                        <p class="text-muted-foreground">Supervisor: <span class="font-medium text-foreground">{{ $promotion->supervisor?->displayName() ?: 'Unassigned' }}</span></p>
                        @if($promotion->supervisor_comments)
                            <p class="text-foreground italic">"{{ $promotion->supervisor_comments }}"</p>
                        @endif
                    </div>

                    <div class="rounded-xl border border-border p-4 space-y-1.5">
                        <div class="flex items-center justify-between">
                            <span class="font-semibold uppercase text-muted-foreground">2. HR Final Approval</span>
                            @if($promotion->hr_approved === true)
                                <span class="px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-700 font-semibold">Approved</span>
                            @elseif($promotion->hr_approved === false)
                                <span class="px-2 py-0.5 rounded bg-rose-500/10 text-rose-700 font-semibold">Rejected</span>
                            @else
                                <span class="px-2 py-0.5 rounded bg-muted text-muted-foreground">Pending</span>
                            @endif
                        </div>
                        <p class="text-muted-foreground">HR Approver: <span class="font-medium text-foreground">{{ $promotion->hrApprover?->displayName() ?: '—' }}</span></p>
                        @if($promotion->hr_comments)
                            <p class="text-foreground italic">"{{ $promotion->hr_comments }}"</p>
                        @endif
                    </div>
                </div>
            </x-metronic.card>
        </div>

        {{-- Right 5 Columns: Stage Decision Forms --}}
        <div class="lg:col-span-5 flex flex-col gap-6">
            @if($promotion->status === \App\Modules\HrmsCore\Enums\PromotionStatus::AwaitingSupervisorApproval && ($canRecommend || $canApprove))
                <x-metronic.card title="Supervisor Recommendation Decision" subtitle="Record supervisor approval or rejection">
                    <form method="POST" action="{{ route('hrms-core.promotions.supervisor-review', $promotion) }}" class="space-y-3 text-xs">
                        @csrf
                        <div>
                            <label class="block font-semibold text-foreground mb-1">Decision *</label>
                            <select name="decision" required class="kt-select w-full text-xs">
                                <option value="approve">Recommend for HR Approval</option>
                                <option value="reject">Reject Promotion Request</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-semibold text-foreground mb-1">Supervisor Comments</label>
                            <textarea name="comments" rows="2" class="kt-textarea w-full text-xs"></textarea>
                        </div>
                        <div>
                            <label class="block font-semibold text-foreground mb-1">Rejection Reason (if rejecting)</label>
                            <input type="text" name="rejection_reason" class="kt-input w-full text-xs" />
                        </div>
                        <button type="submit" class="kt-btn kt-btn-primary w-full justify-center">Submit Supervisor Decision</button>
                    </form>
                </x-metronic.card>
            @endif

            @if($promotion->status === \App\Modules\HrmsCore\Enums\PromotionStatus::AwaitingHrApproval && $canApprove)
                <x-metronic.card title="HR Final Approval Decision" subtitle="Approving automatically updates the employee's grade and salary scale">
                    <form method="POST" action="{{ route('hrms-core.promotions.hr-review', $promotion) }}" class="space-y-3 text-xs">
                        @csrf
                        <div>
                            <label class="block font-semibold text-foreground mb-1">HR Decision *</label>
                            <select name="decision" required class="kt-select w-full text-xs">
                                <option value="approve">Approve & Apply Grade/Scale Progression</option>
                                <option value="reject">Reject Promotion Request</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-semibold text-foreground mb-1">HR Comments</label>
                            <textarea name="comments" rows="2" class="kt-textarea w-full text-xs"></textarea>
                        </div>
                        <div>
                            <label class="block font-semibold text-foreground mb-1">Rejection Reason (required if rejecting)</label>
                            <input type="text" name="rejection_reason" class="kt-input w-full text-xs" />
                        </div>
                        <button type="submit" class="kt-btn kt-btn-primary w-full justify-center">Record Final HR Decision</button>
                    </form>
                </x-metronic.card>
            @endif
        </div>
    </div>
</div>
@endsection

