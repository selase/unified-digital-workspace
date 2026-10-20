@extends('layouts.metronic.app')

@section('title', 'Submission #' . substr($submission->uuid, 0, 8) . ' - ' . $form->title)

@section('content')
<div class="flex flex-col gap-6">
    <x-metronic.page-header
        title="Submission #{{ substr($submission->uuid, 0, 8) }}"
        description="Form: {{ $form->title }} | Status: {{ ucfirst(str_replace('_', ' ', $submission->status)) }}"
    >
        <x-slot:actions>
            <div class="flex items-center gap-2">
                <a href="{{ route('forms.show', $form) }}" class="kt-btn kt-btn-outline">
                    Back to Submissions
                </a>
            </div>
        </x-slot:actions>
    </x-metronic.page-header>

    <div class="grid gap-6 md:grid-cols-3">
        {{-- Submission Meta Details --}}
        <div class="md:col-span-1 space-y-6">
            <div class="kt-card p-6">
                <h3 class="text-sm font-bold text-foreground uppercase tracking-wider mb-4 border-b border-border/50 pb-2">
                    Submitter Info
                </h3>
                <div class="space-y-3 text-sm">
                    <div>
                        <div class="text-xs text-muted-foreground">Submitter Name:</div>
                        <div class="font-semibold text-foreground">
                            @if($submission->user)
                                {{ $submission->user->first_name }} {{ $submission->user->last_name }}
                            @else
                                <span class="italic text-muted-foreground">Anonymous Guest</span>
                            @endif
                        </div>
                    </div>
                    @if($submission->user)
                        <div>
                            <div class="text-xs text-muted-foreground">Email Address:</div>
                            <div class="font-mono text-xs text-foreground">{{ $submission->user->email }}</div>
                        </div>
                    @endif
                    <div>
                        <div class="text-xs text-muted-foreground">IP Address:</div>
                        <div class="font-mono text-xs text-muted-foreground">{{ $submission->ip_address ?? 'Not recorded' }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-muted-foreground">Submitted At:</div>
                        <div class="text-foreground">{{ $submission->created_at?->format('d/m/Y H:i:s') }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-muted-foreground">Current Status:</div>
                        <span class="kt-badge kt-badge-sm mt-1 {{ $submission->status === 'approved' ? 'kt-badge-success' : ($submission->status === 'rejected' ? 'kt-badge-destructive' : 'kt-badge-warning') }}">
                            {{ ucfirst(str_replace('_', ' ', $submission->status)) }}
                        </span>
                    </div>
                </div>
            </div>

            {{-- Review Triage Box --}}
            <div class="kt-card p-6">
                <h3 class="text-sm font-bold text-foreground uppercase tracking-wider mb-4 border-b border-border/50 pb-2">
                    Workflow Review
                </h3>

                @if($submission->reviewed_at)
                    <div class="mb-4 p-3 bg-muted/40 rounded border border-border/50 text-xs space-y-1">
                        <div><span class="font-bold">Reviewed by:</span> {{ $submission->reviewer?->first_name }} {{ $submission->reviewer?->last_name }}</div>
                        <div><span class="font-bold">Date:</span> {{ $submission->reviewed_at->format('d/m/Y H:i') }}</div>
                        @if($submission->reviewer_notes)
                            <div class="mt-2 pt-2 border-t border-border/40"><span class="font-bold">Notes:</span> {{ $submission->reviewer_notes }}</div>
                        @endif
                    </div>
                @endif

                @if(! ($billingOwnScope ?? false) && auth()->user()->can('forms.review'))
                    <form action="{{ route('forms.submissions.review', [$form, $submission]) }}" method="POST" class="space-y-4">
                        @csrf
                        <div>
                            <label class="text-xs font-semibold text-foreground mb-1 block">Triage Decision *</label>
                            <select name="status" class="kt-select text-xs w-full" required>
                                <option value="under_review" {{ $submission->status === 'under_review' ? 'selected' : '' }}>Under Review</option>
                                <option value="approved" {{ $submission->status === 'approved' ? 'selected' : '' }}>Approve Request</option>
                                <option value="rejected" {{ $submission->status === 'rejected' ? 'selected' : '' }}>Reject Request</option>
                            </select>
                        </div>

                        <div>
                            <label class="text-xs font-semibold text-foreground mb-1 block">Reviewer Notes (Optional)</label>
                            <textarea name="reviewer_notes" rows="3" class="kt-textarea text-xs w-full" placeholder="Internal remarks or approval conditions...">{{ old('reviewer_notes', $submission->reviewer_notes) }}</textarea>
                        </div>

                        <button type="submit" class="kt-btn kt-btn-sm kt-btn-primary w-full">
                            Save Review Decision
                        </button>
                    </form>
                @endif
            </div>
        </div>

        {{-- Form Fields & Submitted Answers --}}
        <div class="md:col-span-2">
            <div class="kt-card p-6">
                <h3 class="text-base font-bold text-foreground mb-6 border-b border-border/50 pb-3">
                    Submitted Responses
                </h3>

                <div class="space-y-6">
                    @forelse($form->fields as $field)
                        @php
                            $fieldName = $field->field_name;
                            $answer = $submission->responses_json[$fieldName] ?? null;
                        @endphp
                        <div class="p-4 bg-muted/20 border border-border/60 rounded-xl">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-sm font-semibold text-foreground">{{ $field->label }}</span>
                                <span class="kt-badge kt-badge-xs kt-badge-outline uppercase text-[10px]">{{ $field->field_type }}</span>
                            </div>

                            <div class="mt-2 text-sm text-foreground font-medium">
                                @if($answer === null || $answer === '')
                                    <span class="text-muted-foreground italic text-xs">No response provided</span>
                                @elseif(is_array($answer))
                                    <div class="flex flex-wrap gap-1.5 mt-1">
                                        @foreach($answer as $item)
                                            <span class="kt-badge kt-badge-sm kt-badge-primary">{{ $item }}</span>
                                        @endforeach
                                    </div>
                                @elseif($field->field_type === 'textarea')
                                    <div class="whitespace-pre-wrap bg-muted/40 p-3 rounded border border-border/40 text-xs">
                                        {{ $answer }}
                                    </div>
                                @else
                                    <div class="p-2 bg-muted/30 rounded border border-border/40 text-xs">
                                        {{ $answer }}
                                    </div>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-muted-foreground">No form fields configured.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

