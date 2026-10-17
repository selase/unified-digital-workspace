@extends('layouts.metronic.app')

@section('title', 'HRMS Recruitment')

@section('content')
<div class="flex flex-col gap-6">
    <x-metronic.page-header
        title="Recruitment Pipeline"
        description="Track institutional talent requisitions, published vacancies, and candidate application volumes."
    >
        <x-slot:actions>
            <a href="{{ route('hrms-core.index') }}" class="kt-btn kt-btn-outline">
                Back to Hub
            </a>
            <a href="{{ route('hrms-core.employees.index') }}" class="kt-btn kt-btn-outline">
                Staff Directory
            </a>
        </x-slot:actions>
    </x-metronic.page-header>

    @if(session('status'))
        <div class="rounded-lg border border-success/30 bg-success/10 px-4 py-3 text-sm text-success">
            {{ session('status') }}
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-lg border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger">
            <ul class="list-disc pl-5 space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if(auth()->user()?->can('hrms.jobs.create') || auth()->user()?->can('hrms.jobs.manage'))
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <x-metronic.card title="New Job Requisition" subtitle="Initiate an institutional staffing request">
                <form method="POST" action="{{ route('hrms-core.recruitment.requisitions.store') }}" class="space-y-4">
                    @csrf
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="sm:col-span-2">
                            <label class="kt-form-label text-xs">Requisition Title *</label>
                            <input type="text" name="title" value="{{ old('title') }}" required class="kt-input w-full" placeholder="e.g. Senior Clinical Pharmacist" />
                        </div>
                        <div>
                            <label class="kt-form-label text-xs">Requisition Number</label>
                            <input type="text" name="requisition_number" value="{{ old('requisition_number') }}" class="kt-input w-full" placeholder="Auto-generated if blank" />
                        </div>
                        <div>
                            <label class="kt-form-label text-xs">Department</label>
                            <select name="department_id" class="kt-select w-full">
                                <option value="">Select Department</option>
                                @foreach($departments as $dept)
                                    <option value="{{ $dept->id }}" @selected((int) old('department_id') === $dept->id)>{{ $dept->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="kt-form-label text-xs">Salary Grade</label>
                            <select name="grade_id" class="kt-select w-full">
                                <option value="">Select Grade</option>
                                @foreach($grades as $grade)
                                    <option value="{{ $grade->id }}" @selected((int) old('grade_id') === $grade->id)>{{ $grade->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="kt-form-label text-xs">Employment Type *</label>
                            <select name="employment_type" class="kt-select w-full" required>
                                <option value="full_time" @selected(old('employment_type', 'full_time') === 'full_time')>Full-Time</option>
                                <option value="part_time" @selected(old('employment_type') === 'part_time')>Part-Time</option>
                                <option value="contract" @selected(old('employment_type') === 'contract')>Contract</option>
                                <option value="temporary" @selected(old('employment_type') === 'temporary')>Temporary</option>
                            </select>
                        </div>
                        <div>
                            <label class="kt-form-label text-xs">Vacancies *</label>
                            <input type="number" name="vacancies" min="1" max="500" value="{{ old('vacancies', 1) }}" required class="kt-input w-full" />
                        </div>
                        <div>
                            <label class="kt-form-label text-xs">Location</label>
                            <input type="text" name="location" value="{{ old('location') }}" class="kt-input w-full" placeholder="e.g. Main Campus" />
                        </div>
                        <div>
                            <label class="kt-form-label text-xs">Min Salary</label>
                            <input type="number" step="0.01" min="0" name="min_salary" value="{{ old('min_salary') }}" class="kt-input w-full" />
                        </div>
                        <div>
                            <label class="kt-form-label text-xs">Max Salary</label>
                            <input type="number" step="0.01" min="0" name="max_salary" value="{{ old('max_salary') }}" class="kt-input w-full" />
                        </div>
                        <div>
                            <label class="kt-form-label text-xs">Target Start Date</label>
                            <input type="date" name="target_start_date" value="{{ old('target_start_date') }}" class="kt-input w-full" />
                        </div>
                        <div>
                            <label class="kt-form-label text-xs">Application Deadline</label>
                            <input type="date" name="application_deadline" value="{{ old('application_deadline') }}" class="kt-input w-full" />
                        </div>
                        <div class="sm:col-span-2">
                            <label class="kt-form-label text-xs">Job Description</label>
                            <textarea name="job_description" rows="2" class="kt-textarea w-full">{{ old('job_description') }}</textarea>
                        </div>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="kt-btn kt-btn-primary">Create Requisition</button>
                    </div>
                </form>
            </x-metronic.card>

            <x-metronic.card title="Publish Job Posting" subtitle="Create an internal or external job listing from an approved requisition">
                <form method="POST" action="{{ route('hrms-core.recruitment.postings.store') }}" class="space-y-4">
                    @csrf
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="sm:col-span-2">
                            <label class="kt-form-label text-xs">Parent Requisition *</label>
                            <select name="requisition_id" required class="kt-select w-full">
                                <option value="">Select Requisition</option>
                                @foreach($requisitions as $reqOption)
                                    <option value="{{ $reqOption->id }}" @selected((int) old('requisition_id') === $reqOption->id)>
                                        {{ $reqOption->requisition_number ?: 'REQ-' . $reqOption->id }} — {{ $reqOption->title }} ({{ $reqOption->status->label() }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="kt-form-label text-xs">Posting Title *</label>
                            <input type="text" name="title" value="{{ old('title') }}" required class="kt-input w-full" placeholder="Public-facing job title" />
                        </div>
                        <div>
                            <label class="kt-form-label text-xs">Posted Date</label>
                            <input type="date" name="posted_date" value="{{ old('posted_date', now()->toDateString()) }}" class="kt-input w-full" />
                        </div>
                        <div>
                            <label class="kt-form-label text-xs">Closing Date</label>
                            <input type="date" name="closing_date" value="{{ old('closing_date') }}" class="kt-input w-full" />
                        </div>
                        <div class="sm:col-span-2">
                            <label class="kt-form-label text-xs">Description & Requirements</label>
                            <textarea name="description" rows="2" class="kt-textarea w-full">{{ old('description') }}</textarea>
                        </div>
                        <div class="sm:col-span-2 flex items-center gap-6">
                            <label class="inline-flex items-center gap-2 text-xs text-foreground">
                                <input type="checkbox" name="is_external" value="1" checked class="kt-checkbox" />
                                External Career Portal
                            </label>
                            <label class="inline-flex items-center gap-2 text-xs text-foreground">
                                <input type="checkbox" name="is_internal" value="1" class="kt-checkbox" />
                                Internal Staff Board
                            </label>
                            <label class="inline-flex items-center gap-2 text-xs text-foreground">
                                <input type="checkbox" name="is_active" value="1" checked class="kt-checkbox" />
                                Open Immediately
                            </label>
                        </div>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="kt-btn kt-btn-primary">Publish Posting</button>
                    </div>
                </form>
            </x-metronic.card>
        </div>
    @endif

    <x-metronic.card title="Job Requisitions ({{ $requisitions->total() }})" subtitle="Approval workflow and headcount tracking">
        <div class="kt-scrollable-x-auto">
            <table class="kt-table table-auto kt-table-border">
                <thead>
                    <tr class="text-xs uppercase text-muted-foreground border-b border-border bg-muted/20">
                        <th class="py-3 px-4 font-semibold text-left">Requisition</th>
                        <th class="py-3 px-4 font-semibold text-left">Department / Grade</th>
                        <th class="py-3 px-4 font-semibold text-center">Vacancies</th>
                        <th class="py-3 px-4 font-semibold text-left">Status</th>
                        <th class="py-3 px-4 font-semibold text-right">Workflow Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border text-sm">
                    @forelse($requisitions as $req)
                        <tr class="hover:bg-muted/10 transition-colors">
                            <td class="py-3.5 px-4">
                                <div class="font-semibold text-foreground">{{ $req->title }}</div>
                                <div class="text-xs text-muted-foreground font-mono">{{ $req->requisition_number ?: 'REQ-' . $req->id }}</div>
                            </td>
                            <td class="py-3.5 px-4">
                                <div class="text-sm text-foreground">{{ $req->department?->name ?: 'Institutional' }}</div>
                                <div class="text-xs text-muted-foreground">{{ $req->grade?->name ?: 'Standard Grade' }}</div>
                            </td>
                            <td class="py-3.5 px-4 text-center font-semibold">{{ $req->vacancies }}</td>
                            <td class="py-3.5 px-4">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $req->status->cssClass() }}">
                                    {{ $req->status->label() }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4 text-right">
                                @can('hrms.jobs.manage')
                                    <div class="inline-flex items-center gap-1.5">
                                        @if($req->status === \App\Modules\HrmsCore\Enums\RequisitionStatus::Draft)
                                            <form method="POST" action="{{ route('hrms-core.recruitment.requisitions.status', $req) }}">
                                                @csrf
                                                <input type="hidden" name="status" value="pending_approval" />
                                                <button type="submit" class="kt-btn kt-btn-xs kt-btn-outline">Submit</button>
                                            </form>
                                        @endif
                                        @if(in_array($req->status, [\App\Modules\HrmsCore\Enums\RequisitionStatus::Draft, \App\Modules\HrmsCore\Enums\RequisitionStatus::PendingApproval], true))
                                            <form method="POST" action="{{ route('hrms-core.recruitment.requisitions.status', $req) }}">
                                                @csrf
                                                <input type="hidden" name="status" value="approved" />
                                                <button type="submit" class="kt-btn kt-btn-xs kt-btn-primary">Approve</button>
                                            </form>
                                        @endif
                                        @if($req->status === \App\Modules\HrmsCore\Enums\RequisitionStatus::Approved)
                                            <form method="POST" action="{{ route('hrms-core.recruitment.requisitions.status', $req) }}">
                                                @csrf
                                                <input type="hidden" name="status" value="open" />
                                                <button type="submit" class="kt-btn kt-btn-xs kt-btn-success">Open</button>
                                            </form>
                                        @endif
                                        @if($req->status === \App\Modules\HrmsCore\Enums\RequisitionStatus::Open)
                                            <form method="POST" action="{{ route('hrms-core.recruitment.requisitions.status', $req) }}">
                                                @csrf
                                                <input type="hidden" name="status" value="closed" />
                                                <button type="submit" class="kt-btn kt-btn-xs kt-btn-outline">Close</button>
                                            </form>
                                        @endif
                                        <form method="POST" action="{{ route('hrms-core.recruitment.requisitions.destroy', $req) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="kt-btn kt-btn-xs kt-btn-ghost text-danger">Delete</button>
                                        </form>
                                    </div>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-8 text-center text-muted-foreground text-sm">
                                No job requisitions recorded yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-metronic.card>

    <x-metronic.card title="Job Postings ({{ $jobPostings->total() }})" subtitle="Active and archived career opportunities">
        <div class="mb-5 pb-4 border-b border-border">
            <form method="GET" action="{{ route('hrms-core.recruitment.index') }}" class="flex flex-wrap items-center gap-3">
                <div class="flex-1 min-w-[240px]">
                    <input
                        type="text"
                        name="search"
                        value="{{ $search }}"
                        placeholder="Search posting title…"
                        class="kt-input w-full"
                    />
                </div>
                <div class="w-44">
                    <select name="status" onchange="this.form.submit()" class="kt-select w-full">
                        <option value="">All Statuses</option>
                        <option value="open" @selected($status === 'open')>Open Only</option>
                        <option value="closed" @selected($status === 'closed')>Closed Only</option>
                    </select>
                </div>
                <button type="submit" class="kt-btn kt-btn-outline">Filter</button>
                @if($search || $status)
                    <a href="{{ route('hrms-core.recruitment.index') }}" class="kt-btn kt-btn-ghost text-xs text-muted-foreground">Reset</a>
                @endif
            </form>
        </div>

        <div class="kt-scrollable-x-auto">
            <table class="kt-table table-auto kt-table-border">
                <thead>
                    <tr class="text-xs uppercase text-muted-foreground border-b border-border bg-muted/20">
                        <th class="py-3 px-4 font-semibold text-left">Posting Title</th>
                        <th class="py-3 px-4 font-semibold text-left">Requisition Reference</th>
                        <th class="py-3 px-4 font-semibold text-left">Target Department</th>
                        <th class="py-3 px-4 font-semibold text-center">Applications</th>
                        <th class="py-3 px-4 font-semibold text-left">Status</th>
                        <th class="py-3 px-4 font-semibold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border text-sm">
                    @forelse($jobPostings as $jobPosting)
                        <tr class="hover:bg-muted/10 transition-colors">
                            <td class="py-3.5 px-4 font-medium text-foreground">
                                <a href="{{ route('hrms-core.recruitment.postings.show', $jobPosting) }}" class="font-semibold text-primary hover:underline">
                                    {{ $jobPosting->title }}
                                </a>
                                <div class="text-xs text-muted-foreground flex items-center gap-1.5 mt-0.5">
                                    Closing: {{ $jobPosting->closing_date?->format('d/m/Y') ?: 'Open until filled' }}
                                </div>
                            </td>
                            <td class="py-3.5 px-4">
                                @if($jobPosting->requisition)
                                    <span class="text-xs font-mono font-medium text-foreground bg-muted px-2 py-0.5 rounded border border-border">
                                        {{ $jobPosting->requisition->requisition_number ?: 'REQ-' . $jobPosting->requisition->id }}
                                    </span>
                                    <div class="text-xs text-muted-foreground mt-0.5">{{ Str::limit($jobPosting->requisition->title, 28) }}</div>
                                @else
                                    <span class="text-xs text-muted-foreground italic">—</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="text-sm text-foreground">
                                    {{ $jobPosting->requisition?->department?->name ?: 'Institutional' }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-primary/10 text-primary border border-primary/20">
                                    {{ $jobPosting->applications_count ?? 0 }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4">
                                <x-metronic.badge :variant="$jobPosting->is_active ? 'success' : 'secondary'" size="sm">
                                    {{ $jobPosting->is_active ? 'Open' : 'Closed' }}
                                </x-metronic.badge>
                            </td>
                            <td class="py-3.5 px-4 text-right">
                                <div class="inline-flex items-center gap-1.5">
                                    <a href="{{ route('hrms-core.recruitment.postings.show', $jobPosting) }}" class="kt-btn kt-btn-xs kt-btn-outline">
                                        Pipeline
                                    </a>
                                    @can('hrms.jobs.manage')
                                        <form method="POST" action="{{ route('hrms-core.recruitment.postings.toggle-status', $jobPosting) }}">
                                            @csrf
                                            <button type="submit" class="kt-btn kt-btn-xs kt-btn-outline">
                                                {{ $jobPosting->is_active ? 'Close' : 'Re-open' }}
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('hrms-core.recruitment.postings.destroy', $jobPosting) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="kt-btn kt-btn-xs kt-btn-ghost text-danger">Delete</button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-12 text-center text-muted-foreground">
                                <p class="text-sm">No job postings found matching your filters.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4 pt-4 border-t border-border">
            {{ $jobPostings->links() }}
        </div>
    </x-metronic.card>
</div>
@endsection
