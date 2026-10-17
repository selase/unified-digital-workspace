@extends('layouts.metronic.app')

@section('title', "Deal: {$deal->title}")

@section('content')
<div class="flex flex-col gap-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-xl font-bold text-foreground">{{ $deal->title }}</h1>
                <span class="kt-badge kt-badge-sm kt-badge-outline uppercase">{{ str_replace('_', ' ', $deal->stage) }}</span>
                <span class="kt-badge kt-badge-sm kt-badge-primary font-mono">{{ $deal->formattedValue() }}</span>
            </div>
            <p class="text-xs text-muted-foreground mt-1">
                Client: <a href="{{ route('crm.accounts.show', $deal->account) }}" class="text-primary hover:underline font-semibold">{{ $deal->account?->name }}</a>
                &bull; Created {{ $deal->created_at?->format('d/m/Y') }}
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('crm.deals.index') }}" class="kt-btn kt-btn-sm kt-btn-outline">Back to Deals</a>
            <a href="{{ route('crm.pipeline') }}" class="kt-btn kt-btn-sm kt-btn-outline">Kanban Pipeline</a>
            @can('crm.deals.manage')
                <a href="{{ route('crm.deals.edit', $deal) }}" class="kt-btn kt-btn-sm kt-btn-outline">Edit Deal</a>
                <form action="{{ route('crm.deals.destroy', $deal) }}" method="POST" onsubmit="return confirm('Soft-delete this deal?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="kt-btn kt-btn-sm kt-btn-destructive">Delete</button>
                </form>
            @endcan
        </div>
    </div>

    {{-- Main Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Left: Overview & Stage Mover --}}
        <div class="flex flex-col gap-6">
            <div class="kt-card p-5 space-y-3">
                <h3 class="font-semibold text-sm border-b border-border pb-2">Deal Metrics</h3>
                <div class="text-xs space-y-2">
                    <div>
                        <span class="text-muted-foreground">Deal Value:</span>
                        <span class="font-mono font-bold text-primary ml-1">{{ $deal->formattedValue() }}</span>
                    </div>
                    <div>
                        <span class="text-muted-foreground">Win Probability:</span>
                        <span class="font-semibold text-foreground ml-1">{{ $deal->probability }}%</span>
                    </div>
                    <div>
                        <span class="text-muted-foreground">Expected Close Date:</span>
                        <span class="font-medium text-foreground ml-1">{{ $deal->expected_close_date?->format('d/m/Y') ?? 'Not specified' }}</span>
                    </div>
                    <div>
                        <span class="text-muted-foreground">Deal Owner:</span>
                        <span class="font-medium text-foreground ml-1">{{ $deal->owner?->first_name ? $deal->owner->first_name.' '.$deal->owner->last_name : 'Unassigned' }}</span>
                    </div>
                    @if($deal->lead)
                        <div>
                            <span class="text-muted-foreground">Originating Lead:</span>
                            <a href="{{ route('crm.leads.show', $deal->lead) }}" class="text-primary hover:underline ml-1">
                                {{ $deal->lead->reference_code }} ({{ $deal->lead->full_name }})
                            </a>
                        </div>
                    @endif
                </div>

                @if($deal->notes)
                    <div class="pt-3 border-t border-border">
                        <span class="text-2xs text-muted-foreground uppercase font-semibold">Deal Notes</span>
                        <p class="text-xs text-foreground mt-1 whitespace-pre-line bg-muted/40 p-3 rounded">{{ $deal->notes }}</p>
                    </div>
                @endif
            </div>

            {{-- Stage Movement Form --}}
            @can('crm.deals.manage')
            <div class="kt-card p-5 space-y-3">
                <h3 class="font-semibold text-sm border-b border-border pb-2">Stage Progression</h3>
                <form action="{{ route('crm.deals.stage', $deal) }}" method="POST" class="space-y-3">
                    @csrf
                    <div>
                        <label class="kt-form-label text-xs">Update Stage</label>
                        <select name="stage" class="kt-select text-xs">
                            @foreach($stages as $st => $label)
                                <option value="{{ $st }}" @selected($deal->stage === $st)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="kt-btn kt-btn-sm kt-btn-primary w-full justify-center">Transition Stage</button>
                </form>
            </div>
            @endcan
        </div>

        {{-- Right: Activity History & Logger --}}
        <div class="lg:col-span-2 flex flex-col gap-6">
            {{-- Log Activity --}}
            @can('crm.deals.manage')
            <div class="kt-card p-5">
                <h3 class="font-semibold text-sm border-b border-border pb-2 mb-3">Log Interaction / Milestone</h3>
                <form action="{{ route('crm.deals.activities.store', $deal) }}" method="POST" class="space-y-3">
                    @csrf
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="kt-form-label text-xs">Activity Type *</label>
                            <select name="activity_type" class="kt-select text-xs" required>
                                <option value="note">Internal Note</option>
                                <option value="call">Call Logged</option>
                                <option value="meeting">Meeting Held</option>
                                <option value="email">Email</option>
                            </select>
                        </div>
                        <div>
                            <label class="kt-form-label text-xs">Occurred At</label>
                            <input type="datetime-local" name="occurred_at" value="{{ now()->format('Y-m-d\TH:i') }}" class="kt-input text-xs">
                        </div>
                    </div>
                    <div>
                        <label class="kt-form-label text-xs">Summary *</label>
                        <input type="text" name="summary" class="kt-input text-xs" placeholder="e.g. Sent revised price schedule and terms..." required>
                    </div>
                    <div>
                        <label class="kt-form-label text-xs">Details</label>
                        <textarea name="details" rows="2" class="kt-textarea text-xs" placeholder="Full details of the discussion..."></textarea>
                    </div>
                    <button type="submit" class="kt-btn kt-btn-sm kt-btn-outline">Add to Activity Trail</button>
                </form>
            </div>
            @endcan

            {{-- Activity Trail --}}
            <div class="kt-card p-5">
                <h3 class="font-semibold text-sm border-b border-border pb-2 mb-4">Deal Stage History & Activity Trail</h3>
                <div class="space-y-3">
                    @forelse($deal->activities as $act)
                        <div class="flex items-start gap-3 text-xs border-b border-border/40 pb-2 last:border-none">
                            <span class="kt-badge kt-badge-xs kt-badge-outline uppercase">{{ str_replace('_', ' ', $act->activity_type) }}</span>
                            <div class="flex-1">
                                <div class="font-semibold text-foreground">{{ $act->summary }}</div>
                                @if($act->details)
                                    <div class="text-muted-foreground mt-1">{{ $act->details }}</div>
                                @endif
                                <div class="text-2xs text-muted-foreground mt-1">
                                    {{ $act->occurred_at->format('d M Y, H:i') }} &bull; By {{ $act->loggedBy?->first_name ?? 'System' }}
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-4 text-xs text-muted-foreground">No activities logged on this deal yet.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

