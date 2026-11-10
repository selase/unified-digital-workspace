@extends('layouts.metronic.app')

@section('title', 'Meetings')

@section('content')
@php use App\Modules\Governance\Models\GovMeeting; @endphp
<div class="flex flex-col gap-6">
    <div><h1 class="text-xl font-bold text-foreground">Meetings</h1><p class="text-xs text-muted-foreground">Schedule a meeting, record who came, write the minutes, and get them approved.</p></div>

    @include('governance::partials.nav')

    @can('governance.manage')
    <form method="POST" action="{{ route('governance.meetings.store') }}" class="kt-card">@csrf
        <div class="kt-card-header"><h3 class="kt-card-title">Schedule a meeting</h3></div>
        <div class="kt-card-content grid grid-cols-1 md:grid-cols-4 gap-3">
            <input class="kt-input md:col-span-2" name="title" value="{{ old('title') }}" placeholder="e.g. Quarterly board meeting" required>
            <select class="kt-select" name="body">@foreach($bodies as $body)<option @selected(old('body', 'Board') === $body)>{{ $body }}</option>@endforeach</select>
            <input class="kt-input" type="datetime-local" name="meets_at" value="{{ old('meets_at') }}" required>
            <input class="kt-input md:col-span-2" name="venue" value="{{ old('venue') }}" placeholder="Venue or online link">
            <input class="kt-input" type="number" min="1" name="quorum" value="{{ old('quorum') }}" placeholder="Quorum (people needed)">
            <div></div>
            <textarea class="kt-input md:col-span-4" rows="3" name="agenda" placeholder="Agenda (one item per line)">{{ old('agenda') }}</textarea>
            <div><button class="kt-btn kt-btn-primary" type="submit">Schedule</button></div>
        </div>
    </form>
    @endcan

    <div class="kt-card"><div class="kt-card-table kt-scrollable-x-auto"><table class="kt-table table-auto kt-table-border">
        <thead><tr><th>Date</th><th>Meeting</th><th>Status</th><th>Minutes</th></tr></thead>
        <tbody>
        @forelse($meetings as $meeting)
            <tr><td>{{ $meeting->meets_at->format('d/m/Y H:i') }}</td><td><a class="text-primary" href="{{ route('governance.meetings.show', $meeting->uuid) }}">{{ $meeting->title }}</a><div class="text-xs text-muted-foreground">{{ $meeting->body }}{{ $meeting->venue ? ' · '.$meeting->venue : '' }}</div></td><td>{{ GovMeeting::STATUSES[$meeting->status] }}</td><td>{{ ['none' => '—', 'draft' => 'Draft', 'approved' => 'Approved'][$meeting->minutes_status] }}</td></tr>
        @empty
            <tr><td colspan="4" class="text-center text-muted-foreground py-6">No meetings yet.</td></tr>
        @endforelse
        </tbody>
    </table></div></div>
</div>
@endsection
