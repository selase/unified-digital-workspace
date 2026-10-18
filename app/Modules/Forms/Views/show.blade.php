@extends('layouts.metronic.app')

@section('title', $form->title)

@section('content')
<div class="flex flex-col gap-6">
    <x-metronic.page-header
        title="{{ $form->title }}"
        description="Type: {{ ucfirst(str_replace('_', ' ', $form->type)) }} | Audience: {{ ucfirst($form->target_audience) }} | Status: {{ ucfirst($form->status) }}"
    >
        <x-slot:actions>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('forms.results', $form) }}" class="kt-btn kt-btn-primary">
                    Results & Analytics
                </a>
                <a href="{{ route('forms.export-csv', $form) }}" class="kt-btn kt-btn-outline">
                    Export CSV
                </a>
                @if($form->status === 'draft')
                    <a href="{{ route('forms.edit', $form) }}" class="kt-btn kt-btn-outline">
                        Edit Draft
                    </a>
                    <form action="{{ route('forms.destroy', $form) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this draft form?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="kt-btn kt-btn-outline text-destructive">
                            Delete Draft
                        </button>
                    </form>
                    <form action="{{ route('forms.publish', $form) }}" method="POST">
                        @csrf
                        <button type="submit" class="kt-btn kt-btn-success">Publish Form</button>
                    </form>
                @elseif($form->status === 'published')
                    <a href="{{ route('forms.public.show', $form->slug) }}" target="_blank" class="kt-btn kt-btn-outline">
                        View Live Form ↗
                    </a>
                    <form action="{{ route('forms.close', $form) }}" method="POST">
                        @csrf
                        <button type="submit" class="kt-btn kt-btn-outline text-destructive">Close Submissions</button>
                    </form>
                @else
                    <form action="{{ route('forms.publish', $form) }}" method="POST">
                        @csrf
                        <button type="submit" class="kt-btn kt-btn-primary">Reopen Submissions</button>
                    </form>
                @endif
                <a class="kt-btn kt-btn-outline" href="{{ route('forms.index') }}">Back to Forms</a>
            </div>
        </x-slot:actions>
    </x-metronic.page-header>

    {{-- Share / Embed Widget --}}
    <div class="kt-card p-4 bg-muted/20 border border-border flex flex-wrap items-center justify-between gap-4">
        <div>
            <span class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Public Direct Link:</span>
            <div class="text-xs font-mono text-primary mt-0.5 select-all">{{ url('/forms/f/' . $form->slug) }}</div>
        </div>
        <div class="text-xs text-muted-foreground">
            Created by <span class="font-semibold text-foreground">{{ $form->creator?->first_name }} {{ $form->creator?->last_name }}</span> on {{ $form->created_at?->format('d/m/Y') }}
        </div>
    </div>

    {{-- If Live Poll: Render Visual Tally Percentage Bars --}}
    @if($form->type === 'live_poll')
        <div class="kt-card p-6">
            <h3 class="text-base font-bold text-foreground mb-4">Live Poll Results Tabulation</h3>
            <div class="space-y-4">
                @forelse($pollTally as $option => $data)
                    <div>
                        <div class="flex justify-between items-center text-sm font-semibold mb-1">
                            <span>{{ $option }}</span>
                            <span class="text-primary">{{ $data['percentage'] }}% <span class="text-xs text-muted-foreground">({{ $data['count'] }} votes)</span></span>
                        </div>
                        <div class="w-full bg-muted rounded-full h-3 overflow-hidden">
                            <div class="bg-primary h-3 rounded-full transition-all duration-500" style="width: {{ $data['percentage'] }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-muted-foreground">No votes cast yet in this live poll.</p>
                @endforelse
            </div>
        </div>
    @endif

    {{-- Submissions Register (For general, hr_request, survey) --}}
    @if($form->type !== 'live_poll')
        <div class="kt-card p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-base font-bold text-foreground">
                    Submissions Register ({{ $form->submissions->count() }})
                </h3>
            </div>

            <div class="overflow-x-auto">
                <table class="kt-table table-auto kt-table-border w-full text-sm">
                    <thead>
                        <tr class="border-b border-border/60 text-left text-xs font-semibold text-muted-foreground">
                            <th class="py-3 px-4">Submission Ref</th>
                            <th class="py-3 px-4">Submitter</th>
                            <th class="py-3 px-4">Responses Summary</th>
                            <th class="py-3 px-4">Review Status</th>
                            <th class="py-3 px-4">Date</th>
                            <th class="py-3 px-4 text-right">Workflow Triage</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/40">
                        @forelse($form->submissions as $sub)
                            <tr class="hover:bg-muted/30 transition">
                                <td class="py-3 px-4 font-mono text-xs">
                                    #{{ substr($sub->uuid, 0, 8) }}
                                </td>
                                <td class="py-3 px-4">
                                    @if($sub->user)
                                        <div class="font-bold text-foreground">{{ $sub->user->first_name }} {{ $sub->user->last_name }}</div>
                                        <div class="text-xs text-muted-foreground">{{ $sub->user->email }}</div>
                                    @else
                                        <span class="text-xs text-muted-foreground italic">Anonymous Guest</span>
                                    @endif
                                </td>
                                <td class="py-3 px-4 text-xs">
                                    <div class="space-y-1 max-w-md">
                                        @foreach($sub->responses_json ?? [] as $key => $val)
                                            <div><span class="font-semibold text-foreground">{{ ucwords(str_replace('_', ' ', $key)) }}:</span> <span class="text-muted-foreground">{{ is_array($val) ? implode(', ', $val) : $val }}</span></div>
                                        @endforeach
                                    </div>
                                    @if($sub->reviewer_notes)
                                        <div class="mt-2 p-2 bg-muted/40 rounded text-xs border border-border/50">
                                            <span class="font-bold">Reviewer Note:</span> {{ $sub->reviewer_notes }}
                                        </div>
                                    @endif
                                </td>
                                <td class="py-3 px-4">
                                    <span class="kt-badge kt-badge-sm {{ $sub->status === 'approved' ? 'kt-badge-success' : ($sub->status === 'rejected' ? 'kt-badge-destructive' : 'kt-badge-warning') }}">
                                        {{ str_replace('_', ' ', $sub->status) }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-xs text-muted-foreground">
                                    {{ $sub->created_at?->format('d/m/Y H:i') }}
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <div class="inline-flex items-center gap-1.5 justify-end">
                                        <a href="{{ route('forms.submissions.show', [$form, $sub]) }}" class="kt-btn kt-btn-xs kt-btn-outline" title="View Full Submission">
                                            Details
                                        </a>
                                        <form action="{{ route('forms.submissions.review', [$form, $sub]) }}" method="POST" class="inline">
                                            @csrf
                                            <input type="hidden" name="status" value="approved">
                                            <button type="submit" class="kt-btn kt-btn-xs kt-btn-outline text-success" title="Approve Request">
                                                ✓
                                            </button>
                                        </form>
                                        <form action="{{ route('forms.submissions.review', [$form, $sub]) }}" method="POST" class="inline">
                                            @csrf
                                            <input type="hidden" name="status" value="rejected">
                                            <button type="submit" class="kt-btn kt-btn-xs kt-btn-outline text-destructive" title="Reject Request">
                                                ✕
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-8 text-center text-muted-foreground">
                                    No submissions recorded yet for this form.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection

