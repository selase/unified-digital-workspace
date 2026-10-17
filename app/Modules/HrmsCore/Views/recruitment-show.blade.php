@extends('layouts.metronic.app')

@section('title', $posting->title . ' — Recruitment Pipeline')

@section('content')
<div class="flex flex-col gap-6">
    <x-metronic.page-header
        :title="$posting->title"
        :description="'Requisition: ' . ($posting->requisition?->requisition_number ?: 'N/A') . ' • Department: ' . ($posting->requisition?->department?->name ?: 'Institutional')"
    >
        <x-slot:actions>
            <a href="{{ route('hrms-core.recruitment.index') }}" class="kt-btn kt-btn-outline">
                Back to Recruitment
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

    @can('hrms.applications.manage')
        <x-metronic.card title="Record Candidate Application" subtitle="Add a new candidate application to this job posting">
            <form method="POST" action="{{ route('hrms-core.recruitment.applications.store', $posting) }}" class="space-y-4">
                @csrf
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="kt-form-label text-xs">First Name *</label>
                        <input type="text" name="first_name" value="{{ old('first_name') }}" required class="kt-input w-full" />
                    </div>
                    <div>
                        <label class="kt-form-label text-xs">Last Name *</label>
                        <input type="text" name="last_name" value="{{ old('last_name') }}" required class="kt-input w-full" />
                    </div>
                    <div>
                        <label class="kt-form-label text-xs">Email Address *</label>
                        <input type="email" name="email" value="{{ old('email') }}" required class="kt-input w-full" />
                    </div>
                    <div>
                        <label class="kt-form-label text-xs">Phone</label>
                        <input type="text" name="phone" value="{{ old('phone') }}" class="kt-input w-full" />
                    </div>
                    <div>
                        <label class="kt-form-label text-xs">Years of Experience</label>
                        <input type="number" name="years_of_experience" min="0" max="60" value="{{ old('years_of_experience') }}" class="kt-input w-full" />
                    </div>
                    <div>
                        <label class="kt-form-label text-xs">Expected Salary</label>
                        <input type="number" step="0.01" min="0" name="expected_salary" value="{{ old('expected_salary') }}" class="kt-input w-full" />
                    </div>
                    <div class="sm:col-span-3">
                        <label class="kt-form-label text-xs">Cover Letter / Summary</label>
                        <textarea name="cover_letter" rows="2" class="kt-textarea w-full">{{ old('cover_letter') }}</textarea>
                    </div>
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="kt-btn kt-btn-primary">Submit Application</button>
                </div>
            </form>
        </x-metronic.card>
    @endcan

    <x-metronic.card title="Candidate Applications ({{ $posting->applications->count() }})" subtitle="Screening, interviews, and job offers">
        <div class="space-y-6">
            @forelse($posting->applications as $application)
                <div class="rounded-lg border border-border p-4 space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <div class="text-base font-semibold text-foreground">
                                {{ $application->candidate?->full_name ?: 'Unknown Candidate' }}
                            </div>
                            <div class="text-xs text-muted-foreground">
                                {{ $application->candidate?->email }} • Ref: {{ $application->application_number ?: 'APP-' . $application->id }}
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $application->status->cssClass() }}">
                                {{ $application->status->label() }}
                            </span>
                            @can('hrms.applications.manage')
                                <form method="POST" action="{{ route('hrms-core.recruitment.applications.destroy', $application) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="kt-btn kt-btn-xs kt-btn-ghost text-danger">Remove</button>
                                </form>
                            @endcan
                        </div>
                    </div>

                    @can('hrms.applications.manage')
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 pt-3 border-t border-border">
                            <form method="POST" action="{{ route('hrms-core.recruitment.applications.status', $application) }}" class="space-y-2">
                                @csrf
                                @method('PUT')
                                <div class="text-xs font-semibold uppercase text-muted-foreground">Update Stage</div>
                                <select name="status" class="kt-select w-full text-xs">
                                    @foreach($applicationStatuses as $appStatus)
                                        <option value="{{ $appStatus->value }}" @selected($application->status === $appStatus)>
                                            {{ $appStatus->label() }}
                                        </option>
                                    @endforeach
                                </select>
                                <input type="text" name="screening_notes" value="{{ $application->screening_notes }}" placeholder="Screening notes…" class="kt-input w-full text-xs" />
                                <input type="text" name="rejection_reason" value="{{ $application->rejection_reason }}" placeholder="Rejection reason (if rejected)…" class="kt-input w-full text-xs" />
                                <button type="submit" class="kt-btn kt-btn-xs kt-btn-outline">Save Stage</button>
                            </form>

                            <form method="POST" action="{{ route('hrms-core.recruitment.interviews.store', $application) }}" class="space-y-2">
                                @csrf
                                <div class="text-xs font-semibold uppercase text-muted-foreground">Schedule Interview</div>
                                <div class="grid grid-cols-2 gap-2">
                                    <select name="type" class="kt-select w-full text-xs" required>
                                        <option value="video">Video</option>
                                        <option value="in_person">In-Person</option>
                                        <option value="phone">Phone</option>
                                    </select>
                                    <input type="date" name="interview_date" required class="kt-input w-full text-xs" />
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <input type="time" name="start_time" required class="kt-input w-full text-xs" />
                                    <input type="time" name="end_time" class="kt-input w-full text-xs" />
                                </div>
                                <button type="submit" class="kt-btn kt-btn-xs kt-btn-outline">Schedule</button>
                            </form>

                            <form method="POST" action="{{ route('hrms-core.recruitment.offers.store', $application) }}" class="space-y-2">
                                @csrf
                                <div class="text-xs font-semibold uppercase text-muted-foreground">Prepare Job Offer</div>
                                <input type="text" name="position_title" value="{{ $application->offer?->position_title ?? $posting->title }}" required class="kt-input w-full text-xs" placeholder="Position Title" />
                                <div class="grid grid-cols-2 gap-2">
                                    <input type="number" step="0.01" min="0" name="offered_salary" value="{{ $application->offer?->offered_salary }}" required class="kt-input w-full text-xs" placeholder="Salary" />
                                    <input type="date" name="start_date" value="{{ $application->offer?->start_date?->toDateString() }}" required class="kt-input w-full text-xs" />
                                </div>
                                <input type="hidden" name="employment_type" value="full_time" />
                                <button type="submit" class="kt-btn kt-btn-xs kt-btn-primary">Save Draft Offer</button>
                            </form>
                        </div>
                    @endcan

                    @if($application->interviews->isNotEmpty())
                        <div class="pt-3 border-t border-border">
                            <div class="text-xs font-semibold uppercase text-muted-foreground mb-2">Interviews ({{ $application->interviews->count() }})</div>
                            <div class="space-y-2">
                                @foreach($application->interviews as $interview)
                                    <div class="flex flex-wrap items-center justify-between gap-2 bg-muted/20 rounded px-3 py-2 text-xs">
                                        <div>
                                            <span class="font-semibold">{{ strtoupper($interview->type) }}</span> •
                                            {{ $interview->interview_date?->format('d/m/Y') }} at {{ $interview->start_time }}
                                            <span class="ml-2 px-2 py-0.5 rounded {{ $interview->status->cssClass() }}">{{ $interview->status->label() }}</span>
                                            @if($interview->feedback_summary)
                                                <span class="text-muted-foreground ml-2">— {{ $interview->feedback_summary }}</span>
                                            @endif
                                        </div>
                                        @can('hrms.applications.manage')
                                            <form method="POST" action="{{ route('hrms-core.recruitment.interviews.update', $interview) }}" class="inline-flex items-center gap-1.5">
                                                @csrf
                                                @method('PUT')
                                                <select name="status" class="kt-select text-xs">
                                                    @foreach($interviewStatuses as $intStatus)
                                                        <option value="{{ $intStatus->value }}" @selected($interview->status === $intStatus)>{{ $intStatus->label() }}</option>
                                                    @endforeach
                                                </select>
                                                <input type="text" name="feedback_summary" value="{{ $interview->feedback_summary }}" placeholder="Feedback…" class="kt-input text-xs" />
                                                <button type="submit" class="kt-btn kt-btn-xs kt-btn-outline">Update</button>
                                            </form>
                                        @endcan
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if($application->offer)
                        <div class="pt-3 border-t border-border flex flex-wrap items-center justify-between gap-3 bg-muted/10 rounded p-3 text-xs">
                            <div>
                                <span class="font-semibold text-foreground">Offer {{ $application->offer->offer_number }}:</span>
                                {{ $application->offer->position_title }} • Salary: {{ number_format((float) $application->offer->offered_salary, 2) }}
                                • Start: {{ $application->offer->start_date?->format('d/m/Y') }}
                                <span class="ml-2 px-2 py-0.5 rounded {{ $application->offer->status->cssClass() }}">
                                    {{ $application->offer->status->label() }}
                                </span>
                            </div>
                            @can('hrms.applications.manage')
                                <div class="inline-flex items-center gap-2">
                                    @if($application->offer->status->canModify())
                                        <form method="POST" action="{{ route('hrms-core.recruitment.offers.send', $application->offer) }}">
                                            @csrf
                                            <button type="submit" class="kt-btn kt-btn-xs kt-btn-primary">Approve & Send Offer</button>
                                        </form>
                                    @endif
                                    @if($application->offer->status->awaitingResponse())
                                        <form method="POST" action="{{ route('hrms-core.recruitment.offers.decision', $application->offer) }}" class="inline-flex items-center gap-1.5">
                                            @csrf
                                            <input type="hidden" name="decision" value="accept" />
                                            <button type="submit" class="kt-btn kt-btn-xs kt-btn-success">Mark Accepted</button>
                                        </form>
                                        <form method="POST" action="{{ route('hrms-core.recruitment.offers.decision', $application->offer) }}" class="inline-flex items-center gap-1.5">
                                            @csrf
                                            <input type="hidden" name="decision" value="reject" />
                                            <input type="hidden" name="candidate_feedback" value="Candidate declined offer terms" />
                                            <button type="submit" class="kt-btn kt-btn-xs kt-btn-outline text-danger">Mark Rejected</button>
                                        </form>
                                    @endif
                                </div>
                            @endcan
                        </div>
                    @endif
                </div>
            @empty
                <div class="py-10 text-center text-muted-foreground text-sm">
                    No candidate applications submitted for this posting yet.
                </div>
            @endforelse
        </div>
    </x-metronic.card>
</div>
@endsection

