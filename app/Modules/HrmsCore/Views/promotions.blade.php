@extends('layouts.metronic.app')

@section('title', 'Staff Promotions & Progression')

@section('content')
<div class="flex flex-col gap-6">
    <x-metronic.page-header
        title="Staff Promotions & Grade Progression"
        description="Multi-stage promotion, re-grading, and salary level progression workflow with supervisor recommendation and HR approval."
    >
        <x-slot:actions>
            <a href="{{ route('hrms-core.index') }}" class="kt-btn kt-btn-outline">HRMS Hub</a>
            <a href="{{ route('hrms-core.salary.index') }}" class="kt-btn kt-btn-outline">Salary Structure</a>
            <a href="{{ route('hrms-core.promotions.index', ['scope' => 'mine']) }}" class="kt-btn {{ $scope === 'mine' ? 'kt-btn-primary' : 'kt-btn-outline' }}">
                My Promotions
            </a>
            @if($scope === 'mine')
                <a href="{{ route('hrms-core.promotions.index') }}" class="kt-btn kt-btn-outline">All Visible</a>
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

    {{-- Top Metrics --}}
    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
        <div class="rounded-xl border border-border bg-card p-4">
            <span class="text-xs font-semibold uppercase text-muted-foreground">Visible Requests</span>
            <p class="mt-2 text-2xl font-bold text-foreground">{{ $totalPromotions }}</p>
        </div>
        <div class="rounded-xl border border-border bg-card p-4">
            <span class="text-xs font-semibold uppercase text-muted-foreground">Awaiting Supervisor</span>
            <p class="mt-2 text-2xl font-bold text-amber-600">{{ $awaitingSupervisorCount }}</p>
        </div>
        <div class="rounded-xl border border-border bg-card p-4">
            <span class="text-xs font-semibold uppercase text-muted-foreground">Awaiting HR Approval</span>
            <p class="mt-2 text-2xl font-bold text-primary">{{ $awaitingHrCount }}</p>
        </div>
        <div class="rounded-xl border border-border bg-card p-4">
            <span class="text-xs font-semibold uppercase text-muted-foreground">Approved</span>
            <p class="mt-2 text-2xl font-bold text-emerald-600">{{ $approvedCount }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        {{-- Left 8 Columns: Promotion Register --}}
        <div class="lg:col-span-8 flex flex-col gap-6">
            <x-metronic.card
                title="Promotion & Re-grading Requests ({{ $promotions->total() }})"
                subtitle="Restricted to HR approvers, recommending supervisors, and the employee's own promotion requests"
            >
                <div class="overflow-x-auto">
                    <table class="kt-table table-auto kt-table-border w-full text-left text-xs">
                        <thead>
                            <tr class="uppercase text-muted-foreground border-b border-border">
                                <th class="py-2.5 px-3">Employee</th>
                                <th class="py-2.5 px-3">Category</th>
                                <th class="py-2.5 px-3">Grade / Scale Change</th>
                                <th class="py-2.5 px-3">Effective Date</th>
                                <th class="py-2.5 px-3">Status</th>
                                <th class="py-2.5 px-3 text-right">Details</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @forelse($promotions as $promotion)
                                <tr>
                                    <td class="py-2.5 px-3">
                                        <p class="font-semibold text-foreground">{{ $promotion->employee?->displayName() ?: '—' }}</p>
                                        <p class="text-[11px] font-mono text-muted-foreground">{{ $promotion->employee?->employee_staff_id ?: '—' }}</p>
                                    </td>
                                    <td class="py-2.5 px-3">
                                        <p class="font-medium text-foreground">{{ $promotion->category->label() }}</p>
                                        @if($promotion->reason)
                                            <p class="text-[11px] text-muted-foreground">{{ $promotion->reason }}</p>
                                        @endif
                                    </td>
                                    <td class="py-2.5 px-3 text-muted-foreground">
                                        <div>
                                            {{ $promotion->fromGrade?->name ?: 'Current' }}
                                            &rarr;
                                            <span class="font-semibold text-foreground">{{ $promotion->toGrade?->name ?: 'Unchanged' }}</span>
                                        </div>
                                        @if($promotion->toSalaryLevel)
                                            <div class="text-[11px] font-mono">
                                                Scale: {{ $promotion->fromSalaryLevel?->name ?: '—' }} &rarr; {{ $promotion->toSalaryLevel->name }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="py-2.5 px-3 font-mono text-[11px]">{{ $promotion->effective_date?->format('Y-m-d') ?: 'TBD' }}</td>
                                    <td class="py-2.5 px-3">
                                        <span class="px-2 py-0.5 rounded text-[11px] font-semibold {{ $promotion->status->cssClass() }}">
                                            {{ $promotion->status->label() }}
                                        </span>
                                    </td>
                                    <td class="py-2.5 px-3 text-right">
                                        <a href="{{ route('hrms-core.promotions.show', $promotion) }}" class="kt-btn kt-btn-xs kt-btn-outline">
                                            Open
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-6 text-center text-muted-foreground">No promotion requests found in this scope.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $promotions->links() }}
                </div>
            </x-metronic.card>
        </div>

        {{-- Right 4 Columns: Initiate Promotion Request --}}
        <div class="lg:col-span-4 flex flex-col gap-6">
            @if($canRequest || $canRecommend || $canApprove)
                <x-metronic.card title="Initiate Promotion Request" subtitle="Submit a promotion, re-grading, or step upgrade">
                    <form method="POST" action="{{ route('hrms-core.promotions.store') }}" class="space-y-3 text-xs">
                        @csrf
                        <div>
                            <label class="block font-semibold text-foreground mb-1">Employee *</label>
                            <select name="employee_id" required class="kt-select w-full text-xs">
                                <option value="">Select employee...</option>
                                @foreach($employees as $emp)
                                    @if($canApprove || $canRecommend || ($actorEmployee && $actorEmployee->id === $emp->id))
                                        <option value="{{ $emp->id }}">{{ $emp->displayName() }} ({{ $emp->employee_staff_id ?: 'No ID' }})</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block font-semibold text-foreground mb-1">Category *</label>
                                <select name="category" required class="kt-select w-full text-xs">
                                    @foreach($categories as $cat)
                                        <option value="{{ $cat->value }}">{{ $cat->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block font-semibold text-foreground mb-1">Effective Date</label>
                                <input type="date" name="effective_date" class="kt-input w-full text-xs" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block font-semibold text-foreground mb-1">Proposed Grade</label>
                                <select name="to_grade_id" class="kt-select w-full text-xs">
                                    <option value="">Select grade...</option>
                                    @foreach($grades as $grade)
                                        <option value="{{ $grade->id }}">{{ $grade->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block font-semibold text-foreground mb-1">Proposed Salary Level</label>
                                <select name="to_salary_level_id" class="kt-select w-full text-xs">
                                    <option value="">Select scale...</option>
                                    @foreach($salaryLevels as $level)
                                        <option value="{{ $level->id }}">{{ $level->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="block font-semibold text-foreground mb-1">Reviewing Supervisor</label>
                            <select name="supervisor_id" class="kt-select w-full text-xs">
                                <option value="">Any authorized supervisor</option>
                                @foreach($employees as $emp)
                                    <option value="{{ $emp->id }}">{{ $emp->displayName() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block font-semibold text-foreground mb-1">Reason *</label>
                            <textarea name="reason" rows="2" required class="kt-textarea w-full text-xs" placeholder="Summary reason for promotion or re-grading"></textarea>
                        </div>
                        <div>
                            <label class="block font-semibold text-foreground mb-1">Justification & Achievements</label>
                            <textarea name="justification" rows="2" class="kt-textarea w-full text-xs" placeholder="Supporting appraisal results, tenure, or qualifications"></textarea>
                        </div>
                        <label class="inline-flex items-center gap-1.5">
                            <input type="checkbox" name="submit_for_review" value="1" checked />
                            <span>Submit immediately for Supervisor Review</span>
                        </label>
                        <button type="submit" class="kt-btn kt-btn-primary w-full justify-center">Submit Promotion Request</button>
                    </form>
                </x-metronic.card>
            @endif
        </div>
    </div>
</div>
@endsection
