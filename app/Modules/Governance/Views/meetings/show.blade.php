@extends('layouts.metronic.app')

@section('title', $meeting->title)

@section('content')
@php use App\Modules\Governance\Models\GovMeeting; $held = $meeting->status === 'held'; $locked = $meeting->minutes_status === 'approved'; @endphp
<div class="flex flex-col gap-6">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div><h1 class="text-xl font-bold text-foreground">{{ $meeting->title }}</h1><p class="text-xs text-muted-foreground">{{ $meeting->body }} · {{ $meeting->meets_at->format('d/m/Y H:i') }}{{ $meeting->venue ? ' · '.$meeting->venue : '' }} · {{ GovMeeting::STATUSES[$meeting->status] }}</p></div>
        <div class="flex flex-wrap gap-2">
            <a class="kt-btn kt-btn-sm kt-btn-outline" href="{{ route('governance.meetings.pack', $meeting->uuid) }}">Meeting pack (PDF)</a>
            @can('governance.manage')
            @if($meeting->status === 'scheduled')
                <form method="POST" action="{{ route('governance.meetings.hold', $meeting->uuid) }}">@csrf<button class="kt-btn kt-btn-sm kt-btn-primary" type="submit">Mark as held</button></form>
                <form method="POST" action="{{ route('governance.meetings.cancel', $meeting->uuid) }}" onsubmit="return confirm('Cancel this meeting?')">@csrf<button class="kt-btn kt-btn-sm kt-btn-outline" type="submit">Cancel meeting</button></form>
            @endif
            @endcan
        </div>
    </div>

    @include('governance::partials.nav')

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="rounded-lg bg-muted/30 p-4"><div class="text-xs text-muted-foreground">Present</div><div class="mt-1 text-lg font-semibold">{{ $present }}</div></div>
        <div class="rounded-lg bg-muted/30 p-4"><div class="text-xs text-muted-foreground">Quorum</div><div class="mt-1 text-lg font-semibold">{{ $meeting->quorum ?? '—' }}</div></div>
        <div class="rounded-lg bg-muted/30 p-4"><div class="text-xs text-muted-foreground">Quorum met</div><div class="mt-1 text-lg font-semibold {{ $quorumMet === false ? 'text-destructive' : '' }}">{{ $quorumMet === null ? 'No rule set' : ($quorumMet ? 'Yes' : 'No') }}</div></div>
        <div class="rounded-lg bg-muted/30 p-4"><div class="text-xs text-muted-foreground">Minutes</div><div class="mt-1 text-lg font-semibold">{{ ['none' => 'Not started', 'draft' => 'Draft', 'approved' => 'Approved '.$meeting->minutes_approved_on?->toInstitutionalDate()][$meeting->minutes_status] }}</div></div>
    </div>

    <div class="kt-card">
        <div class="kt-card-header"><h3 class="kt-card-title">Agenda</h3></div>
        @if($meeting->status === 'scheduled')
        @can('governance.manage')
        <form method="POST" action="{{ route('governance.meetings.agenda', $meeting->uuid) }}" class="kt-card-content flex flex-col gap-2">@csrf
            <textarea class="kt-input" rows="5" name="agenda">{{ old('agenda', $meeting->agenda) }}</textarea>
            <div><button class="kt-btn kt-btn-outline" type="submit">Save agenda</button></div>
        </form>
        @else
        <div class="kt-card-content whitespace-pre-line text-sm">{{ $meeting->agenda ?: 'No agenda.' }}</div>
        @endcan
        @else
        <div class="kt-card-content whitespace-pre-line text-sm">{{ $meeting->agenda ?: 'No agenda.' }}</div>
        @endif
    </div>

    @if($meeting->status !== 'cancelled')
    <div class="kt-card">
        <div class="kt-card-header"><h3 class="kt-card-title">Attendance</h3></div>
        <form method="POST" action="{{ route('governance.meetings.attendance', $meeting->uuid) }}" class="kt-card-content flex flex-col gap-2">@csrf
            @forelse($members as $member)
                <div class="flex items-center justify-between gap-3 text-sm"><span>{{ $member->name }} <span class="text-xs text-muted-foreground">{{ $member->position }}</span></span>
                    <select class="kt-select w-40" name="attendance[{{ $member->id }}]" @cannot('governance.manage') disabled @endcannot>
                        <option value="">Not recorded</option>
                        @foreach($attendanceLabels as $key => $label)<option value="{{ $key }}" @selected(($attendance[$member->id] ?? null) === $key)>{{ $label }}</option>@endforeach
                    </select>
                </div>
            @empty
                <span class="text-sm text-muted-foreground">Add board members first.</span>
            @endforelse
            @can('governance.manage')<div><button class="kt-btn kt-btn-outline" type="submit">Save attendance</button></div>@endcan
        </form>
    </div>
    @endif

    @if($held)
    <div class="kt-card">
        <div class="kt-card-header"><h3 class="kt-card-title">Minutes</h3></div>
        @if($locked)
            <div class="kt-card-content whitespace-pre-line text-sm">{{ $meeting->minutes }}</div>
        @else
        @can('governance.manage')
        <form method="POST" action="{{ route('governance.meetings.minutes', $meeting->uuid) }}" class="kt-card-content flex flex-col gap-2">@csrf
            <textarea class="kt-input" rows="12" name="minutes" required>{{ old('minutes', $meeting->minutes) }}</textarea>
            <div><button class="kt-btn kt-btn-outline" type="submit">Save draft</button></div>
        </form>
        @endcan
        @can('governance.minutes.approve')
        @if($meeting->minutes)
        <form method="POST" action="{{ route('governance.meetings.approve-minutes', $meeting->uuid) }}" class="kt-card-content flex gap-2 items-center">@csrf
            <input class="kt-input w-44" type="date" name="date" value="{{ today()->toDateString() }}">
            <button class="kt-btn kt-btn-primary" type="submit" onclick="return confirm('Approved minutes are locked. Approve?')">Approve minutes</button>
        </form>
        @endif
        @endcan
        @endif
    </div>

    <div class="kt-card">
        <div class="kt-card-header"><h3 class="kt-card-title">Resolutions</h3></div>
        @can('governance.manage')
        <form method="POST" action="{{ route('governance.meetings.resolutions.store', $meeting->uuid) }}" class="kt-card-content flex flex-wrap gap-2">@csrf
            <input class="kt-input flex-1 min-w-64" name="text" placeholder="The board resolved that…" required>
            <select class="kt-select w-36" name="outcome">@foreach($outcomes as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select>
            <button class="kt-btn kt-btn-outline" type="submit">Record</button>
        </form>
        @endcan
        <div class="kt-card-table kt-scrollable-x-auto"><table class="kt-table table-auto kt-table-border"><tbody>
            @forelse($resolutions as $resolution)<tr><td class="whitespace-nowrap">{{ $resolution->reference }}</td><td>{{ $resolution->text }}</td><td>{{ $outcomes[$resolution->outcome] }}</td></tr>@empty<tr><td class="text-center text-muted-foreground py-6">No resolutions recorded.</td></tr>@endforelse
        </tbody></table></div>
    </div>

    <div class="kt-card">
        <div class="kt-card-header"><h3 class="kt-card-title">Actions from this meeting</h3><a class="kt-btn kt-btn-sm kt-btn-outline" href="{{ route('governance.actions.index') }}">Add or manage actions</a></div>
        <div class="kt-card-table kt-scrollable-x-auto"><table class="kt-table table-auto kt-table-border"><tbody>
            @forelse($actions as $action)<tr><td>{{ $action->description }}</td><td>{{ $action->owner_name }}</td><td class="text-end {{ $action->isOverdue() ? 'text-destructive' : '' }}">{{ $action->due_on?->toInstitutionalDate() }}</td><td>{{ ucfirst($action->status) }}</td></tr>@empty<tr><td class="text-center text-muted-foreground py-6">No actions.</td></tr>@endforelse
        </tbody></table></div>
    </div>
    @endif
</div>
@endsection
