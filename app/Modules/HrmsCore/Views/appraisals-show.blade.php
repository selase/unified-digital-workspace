@extends('layouts.metronic.app')

@section('title', 'Appraisal Dossier — ' . ($appraisal->employee?->displayName() ?: 'Employee'))

@section('content')
@php
    $isAppraisedEmployee = $actorEmployee !== null && $appraisal->employee_id === $actorEmployee->id;
    $isSupervisorActor = $actorEmployee !== null && $appraisal->supervisor_id === $actorEmployee->id;
    $isFinalReviewerActor = $actorEmployee !== null && in_array($actorEmployee->id, [$appraisal->hod_id, $appraisal->hr_reviewer_id], true);
@endphp
<div class="flex flex-col gap-6">
    <x-metronic.page-header
        :title="'Appraisal Dossier: ' . ($appraisal->employee?->displayName() ?: 'Employee')"
        :description="($appraisal->period?->name ?: 'Review Period') . ' · ' . ($appraisal->template?->name ?: 'Template')"
    >
        <x-slot:actions>
            <a href="{{ route('hrms-core.appraisals.index', ['scope' => 'mine']) }}" class="kt-btn kt-btn-outline">My Appraisals</a>
            <a href="{{ route('hrms-core.appraisals.index') }}" class="kt-btn kt-btn-outline">&larr; All Appraisals</a>
            @if(in_array($appraisal->status->value, ['draft', 'cancelled'], true) && (auth()->user()?->can('hrms.appraisal.create') || $isHrManage))
                <form method="POST" action="{{ route('hrms-core.appraisals.destroy', $appraisal) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="kt-btn kt-btn-outline text-destructive">Delete Appraisal</button>
                </form>
            @endif
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

    {{-- Top Summary & Score Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        <div class="rounded-xl border border-border bg-card p-4">
            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Current Stage</span>
            <div class="mt-2">
                <span class="px-2.5 py-1 rounded-full text-xs font-semibold {{ $appraisal->status->cssClass() }}">
                    {{ $appraisal->status->label() }}
                </span>
            </div>
        </div>
        <div class="rounded-xl border border-border bg-card p-4">
            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Self Overall Score</span>
            <p class="mt-2 text-2xl font-bold text-foreground">{{ $appraisal->self_overall_score ?? '—' }} <span class="text-xs font-normal text-muted-foreground">/ 5.00</span></p>
        </div>
        <div class="rounded-xl border border-border bg-card p-4">
            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Supervisor Score</span>
            @if($canViewSupervisorAssessment)
                <p class="mt-2 text-2xl font-bold text-foreground">{{ $appraisal->supervisor_overall_score ?? '—' }} <span class="text-xs font-normal text-muted-foreground">/ 5.00</span></p>
            @else
                <p class="mt-2 text-xs italic text-muted-foreground">Hidden until appraisal is completed</p>
            @endif
        </div>
        <div class="rounded-xl border border-border bg-card p-4">
            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Final Score</span>
            @if($canViewSupervisorAssessment)
                <p class="mt-2 text-2xl font-bold text-primary">{{ $appraisal->final_overall_score ?? '—' }} <span class="text-xs font-normal text-muted-foreground">/ 5.00</span></p>
            @else
                <p class="mt-2 text-xs italic text-muted-foreground">Hidden until appraisal is completed</p>
            @endif
        </div>
        <div class="rounded-xl border border-border bg-card p-4 flex flex-col justify-between">
            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Workflow Progression</span>
            @if(! $appraisal->status->isFinal() && ($isAppraisedEmployee || auth()->user()?->can('hrms.appraisal.evaluate') || $isHrManage))
                <div class="mt-2 flex flex-wrap gap-1.5">
                    @if($appraisal->status->nextStatus())
                        <form method="POST" action="{{ route('hrms-core.appraisals.status', $appraisal) }}">
                            @csrf
                            <input type="hidden" name="status" value="{{ $appraisal->status->nextStatus()->value }}" />
                            <button type="submit" class="kt-btn kt-btn-xs kt-btn-primary">
                                Advance to {{ $appraisal->status->nextStatus()->label() }}
                            </button>
                        </form>
                    @endif
                    @if($isHrManage)
                        <form method="POST" action="{{ route('hrms-core.appraisals.status', $appraisal) }}">
                            @csrf
                            <input type="hidden" name="status" value="cancelled" />
                            <button type="submit" class="kt-btn kt-btn-xs kt-btn-outline text-destructive">Cancel</button>
                        </form>
                    @endif
                </div>
            @else
                <p class="mt-2 text-xs text-muted-foreground">Workflow finalized</p>
            @endif
        </div>
    </div>

    {{-- Rating Scale Legend --}}
    @if($ratingScales->isNotEmpty())
        <div class="rounded-xl border border-border bg-card p-4 flex flex-wrap items-center gap-3">
            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Rating Scale (1–5):</span>
            @foreach($ratingScales as $scale)
                <span class="px-2.5 py-1 rounded-lg text-xs font-medium {{ $scale->getCssClass() }}" title="{{ $scale->description }}">
                    <strong>{{ $scale->value }}</strong> — {{ $scale->label }}
                </span>
            @endforeach
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        {{-- Left 8 Cols: Competencies & Goals --}}
        <div class="lg:col-span-8 flex flex-col gap-6">
            {{-- Competencies Evaluation Card --}}
            <x-metronic.card title="Competencies & Behavioral Evaluation ({{ $appraisal->competencies->count() }})" subtitle="Self-assessment, supervisor rating, and final competency scores (1–5)">
                <div class="space-y-4">
                    @forelse($appraisal->competencies as $competency)
                        <div class="rounded-xl border border-border p-4 space-y-3">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <h4 class="text-sm font-semibold text-foreground">{{ $competency->name }}</h4>
                                    @if($competency->description)
                                        <p class="text-xs text-muted-foreground">{{ $competency->description }}</p>
                                    @endif
                                    @if($competency->behavioral_indicators)
                                        <p class="text-[11px] text-muted-foreground mt-1"><strong>Indicators:</strong> {{ $competency->behavioral_indicators }}</p>
                                    @endif
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="px-2 py-0.5 rounded bg-muted text-[11px] font-mono">Weight: {{ $competency->weight }}%</span>
                                    @if(! $appraisal->status->isFinal() && (auth()->user()?->can('hrms.appraisal.create') || $isHrManage))
                                        <form method="POST" action="{{ route('hrms-core.appraisals.competencies.destroy', [$appraisal, $competency]) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs text-destructive hover:underline">Remove</button>
                                        </form>
                                    @endif
                                </div>
                            </div>

                            @if(! $appraisal->status->isFinal() && ($isAppraisedEmployee || auth()->user()?->can('hrms.appraisal.evaluate') || $isHrManage))
                                <form method="POST" action="{{ route('hrms-core.appraisals.competencies.update', [$appraisal, $competency]) }}" class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-2 border-t border-border text-xs">
                                    @csrf
                                    @method('PUT')
                                    <div>
                                        <label class="block font-semibold text-foreground mb-1">Self Rating (1–5)</label>
                                        @if(($isAppraisedEmployee && $appraisal->status->value === 'self_assessment') || $isHrManage)
                                            <input type="number" name="self_rating" min="1" max="5" value="{{ $competency->self_rating }}" class="kt-input w-full text-xs mb-1.5" />
                                            <input type="text" name="self_evidence" value="{{ $competency->self_evidence }}" placeholder="Self evidence / examples..." class="kt-input w-full text-xs" />
                                        @else
                                            <p class="text-muted-foreground">Score: <strong>{{ $competency->self_rating ?? '—' }}</strong></p>
                                            <p class="text-[11px] text-muted-foreground">{{ $competency->self_evidence ?: 'No self evidence' }}</p>
                                        @endif
                                    </div>
                                    <div>
                                        <label class="block font-semibold text-foreground mb-1">Supervisor Rating (1–5)</label>
                                        @if(($isSupervisorActor && $appraisal->status->value === 'supervisor_review') || $isHrManage)
                                            <input type="number" name="supervisor_rating" min="1" max="5" value="{{ $competency->supervisor_rating }}" class="kt-input w-full text-xs mb-1.5" />
                                            <input type="text" name="supervisor_evidence" value="{{ $competency->supervisor_evidence }}" placeholder="Supervisor notes..." class="kt-input w-full text-xs" />
                                        @elseif($canViewSupervisorAssessment)
                                            <p class="text-muted-foreground">Score: <strong>{{ $competency->supervisor_rating ?? '—' }}</strong></p>
                                            <p class="text-[11px] text-muted-foreground">{{ $competency->supervisor_evidence ?: 'No supervisor notes' }}</p>
                                        @else
                                            <p class="text-[11px] italic text-muted-foreground">Hidden until appraisal is completed</p>
                                        @endif
                                    </div>
                                    <div class="flex flex-col justify-between">
                                        <div>
                                            <label class="block font-semibold text-foreground mb-1">Final Rating (1–5)</label>
                                            @if(($isFinalReviewerActor && in_array($appraisal->status->value, ['hod_review', 'hr_review'], true)) || $isHrManage)
                                                <input type="number" name="final_rating" min="1" max="5" value="{{ $competency->final_rating }}" class="kt-input w-full text-xs" />
                                            @elseif($canViewSupervisorAssessment)
                                                <p class="text-muted-foreground">Score: <strong>{{ $competency->final_rating ?? '—' }}</strong></p>
                                            @else
                                                <p class="text-[11px] italic text-muted-foreground">Hidden until appraisal is completed</p>
                                            @endif
                                        </div>
                                        <button type="submit" class="kt-btn kt-btn-xs kt-btn-primary mt-2 self-end">Save Rating</button>
                                    </div>
                                </form>
                            @else
                                <div class="grid grid-cols-3 gap-2 pt-2 border-t border-border text-xs">
                                    <div>Self: <strong>{{ $competency->self_rating ?? '—' }}</strong> ({{ $competency->self_evidence ?: 'No evidence' }})</div>
                                    @if($canViewSupervisorAssessment)
                                        <div>Supervisor: <strong>{{ $competency->supervisor_rating ?? '—' }}</strong> ({{ $competency->supervisor_evidence ?: 'No notes' }})</div>
                                        <div>Final: <strong>{{ $competency->final_rating ?? '—' }}</strong></div>
                                    @else
                                        <div class="col-span-2 italic text-muted-foreground">Supervisor and final ratings are hidden until the appraisal is completed.</div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @empty
                        <p class="text-xs text-muted-foreground py-4 text-center">No competencies added to this appraisal yet.</p>
                    @endforelse

                    @if(! $appraisal->status->isFinal() && (auth()->user()?->can('hrms.appraisal.create') || auth()->user()?->can('hrms.appraisal.evaluate') || $isHrManage))
                        <form method="POST" action="{{ route('hrms-core.appraisals.competencies.store', $appraisal) }}" class="pt-4 border-t border-border grid grid-cols-1 sm:grid-cols-4 gap-2.5 text-xs">
                            @csrf
                            <input type="text" name="name" required placeholder="Competency name *" class="kt-input text-xs" />
                            <input type="text" name="behavioral_indicators" placeholder="Behavioral indicators" class="kt-input text-xs sm:col-span-2" />
                            <div class="flex items-center gap-2">
                                <input type="number" name="weight" step="0.01" min="0" max="100" placeholder="Weight %" class="kt-input text-xs w-24" />
                                <button type="submit" class="kt-btn kt-btn-xs kt-btn-outline shrink-0">+ Add</button>
                            </div>
                        </form>
                    @endif
                </div>
            </x-metronic.card>

            {{-- Performance Goals Card --}}
            <x-metronic.card title="Performance Goals & Objectives ({{ $appraisal->goals->count() }})" subtitle="Key results, targets, achievement tracking, and goal ratings">
                <div class="space-y-4">
                    @forelse($appraisal->goals as $goal)
                        <div class="rounded-xl border border-border p-4 space-y-3">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <h4 class="text-sm font-semibold text-foreground">{{ $goal->title }}</h4>
                                    <p class="text-xs text-muted-foreground">Target: {{ $goal->target ?: '—' }} · Weight: {{ $goal->weight }}%</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-medium {{ $goal->status->cssClass() }}">{{ $goal->status->label() }}</span>
                                    @if(! $appraisal->status->isFinal() && (auth()->user()?->can('hrms.appraisal.create') || $isHrManage))
                                        <form method="POST" action="{{ route('hrms-core.appraisals.goals.destroy', [$appraisal, $goal]) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs text-destructive hover:underline">Remove</button>
                                        </form>
                                    @endif
                                </div>
                            </div>

                            @if(! $appraisal->status->isFinal() && ($isAppraisedEmployee || auth()->user()?->can('hrms.appraisal.evaluate') || $isHrManage))
                                <form method="POST" action="{{ route('hrms-core.appraisals.goals.update', [$appraisal, $goal]) }}" class="grid grid-cols-1 sm:grid-cols-4 gap-2.5 pt-2 border-t border-border text-xs">
                                    @csrf
                                    @method('PUT')
                                    @if(($isAppraisedEmployee && $appraisal->status->value === 'self_assessment') || $isHrManage)
                                        <input type="text" name="achievement" value="{{ $goal->achievement }}" placeholder="Actual achievement..." class="kt-input text-xs" />
                                    @else
                                        <span class="text-muted-foreground">{{ $goal->achievement ?: 'No achievement recorded' }}</span>
                                    @endif
                                    <select name="status" class="kt-select text-xs">
                                        @foreach($goalStatuses as $gStatus)
                                            <option value="{{ $gStatus->value }}" @selected($goal->status === $gStatus)>{{ $gStatus->label() }}</option>
                                        @endforeach
                                    </select>
                                    @if(($isAppraisedEmployee && $appraisal->status->value === 'self_assessment') || $isHrManage)
                                        <input type="number" name="self_rating" min="1" max="5" value="{{ $goal->self_rating }}" placeholder="Self (1-5)" class="kt-input text-xs" />
                                    @else
                                        <span class="text-muted-foreground">Self: <strong>{{ $goal->self_rating ?? '—' }}</strong></span>
                                    @endif
                                    <div class="flex items-center gap-2">
                                        @if(($isSupervisorActor && $appraisal->status->value === 'supervisor_review') || $isHrManage)
                                            <input type="number" name="supervisor_rating" min="1" max="5" value="{{ $goal->supervisor_rating }}" placeholder="Sup (1-5)" class="kt-input text-xs" />
                                        @elseif($canViewSupervisorAssessment)
                                            <span class="text-muted-foreground">Sup: <strong>{{ $goal->supervisor_rating ?? '—' }}</strong></span>
                                        @else
                                            <span class="text-[11px] italic text-muted-foreground">Sup: Hidden</span>
                                        @endif
                                        <button type="submit" class="kt-btn kt-btn-xs kt-btn-primary shrink-0">Save</button>
                                    </div>
                                </form>
                            @endif
                        </div>
                    @empty
                        <p class="text-xs text-muted-foreground py-4 text-center">No performance goals defined for this appraisal yet.</p>
                    @endforelse

                    @if(! $appraisal->status->isFinal() && (auth()->user()?->can('hrms.appraisal.create') || auth()->user()?->can('hrms.appraisal.evaluate') || $isHrManage))
                        <form method="POST" action="{{ route('hrms-core.appraisals.goals.store', $appraisal) }}" class="pt-4 border-t border-border grid grid-cols-1 sm:grid-cols-4 gap-2.5 text-xs">
                            @csrf
                            <input type="text" name="title" required placeholder="Goal title *" class="kt-input text-xs" />
                            <input type="text" name="target" placeholder="Target metric" class="kt-input text-xs" />
                            <input type="date" name="due_date" class="kt-input text-xs" />
                            <div class="flex items-center gap-2">
                                <input type="number" name="weight" step="0.01" min="0" max="100" placeholder="Weight %" class="kt-input text-xs w-24" />
                                <button type="submit" class="kt-btn kt-btn-xs kt-btn-outline shrink-0">+ Add Goal</button>
                            </div>
                        </form>
                    @endif
                </div>
            </x-metronic.card>

            {{-- Comments Feed --}}
            <x-metronic.card title="Appraisal Comments & Action Items ({{ $appraisal->comments->count() }})" subtitle="Feedback, general observations, and follow-up action items">
                <div class="space-y-3">
                    @forelse($appraisal->comments as $comment)
                        <div class="rounded-lg border border-border p-3 flex items-start justify-between gap-2 text-xs">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-semibold text-foreground">{{ $comment->author?->displayName() ?: 'Reviewer' }}</span>
                                    <span class="px-2 py-0.5 rounded bg-muted text-[10px] uppercase font-mono">{{ $comment->type }}</span>
                                    @if($comment->is_private)
                                        <span class="px-2 py-0.5 rounded bg-amber-100 text-amber-800 text-[10px] font-semibold">Private (HR/Mgmt)</span>
                                    @endif
                                </div>
                                <p class="mt-1 text-muted-foreground">{{ $comment->comment }}</p>
                            </div>
                            @if($isHrManage || ($actorEmployee !== null && $comment->author_id === $actorEmployee->id))
                                <form method="POST" action="{{ route('hrms-core.appraisals.comments.destroy', [$appraisal, $comment]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-[11px] text-destructive hover:underline">Delete</button>
                                </form>
                            @endif
                        </div>
                    @empty
                        <p class="text-xs text-muted-foreground py-3 text-center">No comments recorded yet.</p>
                    @endforelse

                    @if(auth()->user()?->can('hrms.appraisal.evaluate') || $isHrManage)
                        <form method="POST" action="{{ route('hrms-core.appraisals.comments.store', $appraisal) }}" class="pt-3 border-t border-border space-y-2.5 text-xs">
                            @csrf
                            <textarea name="comment" rows="2" required placeholder="Add feedback, observation, or action item..." class="kt-textarea w-full text-xs"></textarea>
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="flex items-center gap-3">
                                    <select name="type" class="kt-select text-xs">
                                        @foreach($commentTypes as $cKey => $cLabel)
                                            <option value="{{ $cKey }}">{{ $cLabel }}</option>
                                        @endforeach
                                    </select>
                                    <label class="inline-flex items-center gap-1.5">
                                        <input type="checkbox" name="is_private" value="1" />
                                        <span>Private note</span>
                                    </label>
                                </div>
                                <button type="submit" class="kt-btn kt-btn-xs kt-btn-primary">Post Comment</button>
                            </div>
                        </form>
                    @endif
                </div>
            </x-metronic.card>
        </div>

        {{-- Right 4 Cols: Stage Reviews & Recommendations --}}
        <div class="lg:col-span-4 flex flex-col gap-6">
            @if($canViewSupervisorAssessment)
                <x-metronic.card title="Stage Reviews ({{ $appraisal->reviews->count() }})" subtitle="Supervisor, HOD, and HR formal review sign-offs">
                    <div class="space-y-3 mb-4">
                        @forelse($appraisal->reviews as $review)
                            <div class="rounded-lg border border-border p-3 text-xs space-y-1">
                                <div class="flex items-center justify-between">
                                    <span class="font-semibold uppercase text-foreground">{{ $review->reviewer_type }}</span>
                                    <span class="px-2 py-0.5 rounded bg-muted text-[11px] font-mono">{{ $review->decision }}</span>
                                </div>
                                <p class="text-muted-foreground">Reviewer: {{ $review->reviewer?->displayName() ?: '—' }} · Rating: {{ $review->overall_rating ?? '—' }}</p>
                                @if($review->strengths)
                                    <p class="text-foreground"><span class="font-semibold text-muted-foreground">Strengths:</span> {{ $review->strengths }}</p>
                                @endif
                                @if($review->areas_for_improvement)
                                    <p class="text-foreground"><span class="font-semibold text-muted-foreground">Areas for improvement:</span> {{ $review->areas_for_improvement }}</p>
                                @endif
                                @if($review->general_comments)
                                    <p class="text-foreground">{{ $review->general_comments }}</p>
                                @endif
                            </div>
                        @empty
                            <p class="text-xs text-muted-foreground text-center py-2">No formal stage reviews recorded yet.</p>
                        @endforelse
                    </div>

                    @if(! $appraisal->status->isFinal() && (auth()->user()?->can('hrms.appraisal.evaluate') || $isHrManage))
                        <form method="POST" action="{{ route('hrms-core.appraisals.reviews.store', $appraisal) }}" class="pt-3 border-t border-border space-y-2.5 text-xs">
                            @csrf
                            <div>
                                <label class="block font-semibold text-foreground mb-1">Review Stage *</label>
                                <select name="reviewer_type" required class="kt-select w-full text-xs">
                                    @foreach($reviewerTypes as $rKey => $rLabel)
                                        <option value="{{ $rKey }}">{{ $rLabel }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block font-semibold text-foreground mb-1">Decision *</label>
                                    <select name="decision" required class="kt-select w-full text-xs">
                                        @foreach($reviewDecisions as $dKey => $dLabel)
                                            <option value="{{ $dKey }}">{{ $dLabel }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block font-semibold text-foreground mb-1">Overall Rating (1–5)</label>
                                    <input type="number" name="overall_rating" step="0.1" min="1" max="5" class="kt-input w-full text-xs" />
                                </div>
                            </div>
                            <div>
                                <label class="block font-semibold text-foreground mb-1">Strengths</label>
                                <input type="text" name="strengths" class="kt-input w-full text-xs" />
                            </div>
                            <div>
                                <label class="block font-semibold text-foreground mb-1">Areas for Improvement</label>
                                <input type="text" name="areas_for_improvement" class="kt-input w-full text-xs" />
                            </div>
                            <div>
                                <label class="block font-semibold text-foreground mb-1">General Comments</label>
                                <textarea name="general_comments" rows="2" class="kt-textarea w-full text-xs"></textarea>
                            </div>
                            <button type="submit" class="kt-btn kt-btn-outline w-full justify-center">Record Stage Review</button>
                        </form>
                    @endif
                </x-metronic.card>

                <x-metronic.card title="Recommendations ({{ $appraisal->recommendations->count() }})" subtitle="Post-appraisal outcomes (promotion, training, recognition, PIP)">
                    <div class="space-y-3 mb-4">
                        @forelse($appraisal->recommendations as $rec)
                            <div class="rounded-lg border border-border p-3 text-xs space-y-1">
                                <div class="flex items-center justify-between">
                                    <span class="px-2 py-0.5 rounded text-[11px] font-semibold {{ $rec->type->cssClass() }}">{{ $rec->type->label() }}</span>
                                    <span class="text-[11px] font-mono text-muted-foreground">{{ $rec->status }}</span>
                                </div>
                                @if($rec->description)
                                    <p class="text-foreground">{{ $rec->description }}</p>
                                @endif
                            </div>
                        @empty
                            <p class="text-xs text-muted-foreground text-center py-2">No recommendations recorded yet.</p>
                        @endforelse
                    </div>

                    @if(auth()->user()?->can('hrms.appraisal.evaluate') || $isHrManage)
                        <form method="POST" action="{{ route('hrms-core.appraisals.recommendations.store', $appraisal) }}" class="pt-3 border-t border-border space-y-2.5 text-xs">
                            @csrf
                            <div>
                                <label class="block font-semibold text-foreground mb-1">Recommendation Type *</label>
                                <select name="type" required class="kt-select w-full text-xs">
                                    @foreach($recommendationTypes as $recType)
                                        <option value="{{ $recType->value }}">{{ $recType->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block font-semibold text-foreground mb-1">Description</label>
                                <input type="text" name="description" class="kt-input w-full text-xs" />
                            </div>
                            <div>
                                <label class="block font-semibold text-foreground mb-1">Action Plan</label>
                                <textarea name="action_plan" rows="2" class="kt-textarea w-full text-xs"></textarea>
                            </div>
                            <button type="submit" class="kt-btn kt-btn-outline w-full justify-center">Add Recommendation</button>
                        </form>
                    @endif
                </x-metronic.card>
            @else
                <x-metronic.card title="Reviewer Evaluation & Recommendations" subtitle="Confidential until finalized">
                    <p class="text-xs italic text-muted-foreground py-4">
                        Supervisor ratings, formal stage reviews, and post-appraisal recommendations remain confidential until the appraisal cycle is marked Complete.
                    </p>
                </x-metronic.card>
            @endif
        </div>
    </div>
</div>
@endsection
