@extends('layouts.metronic.app')

@section('title', 'Board members')

@section('content')
<div class="flex flex-col gap-6">
    <div><h1 class="text-xl font-bold text-foreground">Board members</h1><p class="text-xs text-muted-foreground">Everyone who sits on the board or a committee, with their term. Terms ending soon are flagged.</p></div>

    @include('governance::partials.nav')

    @can('governance.manage')
    <form method="POST" action="{{ route('governance.members.store') }}" class="kt-card">@csrf
        <div class="kt-card-header"><h3 class="kt-card-title">Add a member</h3></div>
        <div class="kt-card-content grid grid-cols-1 md:grid-cols-4 gap-3">
            <input class="kt-input" name="name" value="{{ old('name') }}" placeholder="Name" required>
            <input class="kt-input" type="email" name="email" value="{{ old('email') }}" placeholder="Email">
            <input class="kt-input" name="phone" value="{{ old('phone') }}" placeholder="Phone">
            <select class="kt-select" name="position">@foreach($positions as $position)<option @selected(old('position', 'Member') === $position)>{{ $position }}</option>@endforeach</select>
            <input class="kt-input" name="body" value="{{ old('body', 'Board') }}" placeholder="Board or committee">
            <input class="kt-input" type="date" name="term_starts_on" value="{{ old('term_starts_on') }}" title="Term starts">
            <input class="kt-input" type="date" name="term_ends_on" value="{{ old('term_ends_on') }}" title="Term ends">
            <div><button class="kt-btn kt-btn-primary" type="submit">Add member</button></div>
        </div>
    </form>
    @endcan

    <div class="kt-card"><div class="kt-card-table kt-scrollable-x-auto"><table class="kt-table table-auto kt-table-border">
        <thead><tr><th>Name</th><th>Position</th><th>Body</th><th>Term</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse($members as $member)
            <tr class="{{ $member->status === 'former' ? 'text-muted-foreground' : '' }}">
                <td>{{ $member->name }}<div class="text-xs text-muted-foreground">{{ $member->email }} {{ $member->phone }}</div></td>
                <td>{{ $member->position }}</td>
                <td>{{ $member->body }}</td>
                <td class="{{ in_array($member->id, $ending, true) ? 'text-destructive' : '' }}">{{ $member->term_starts_on?->toInstitutionalDate() }}{{ $member->term_ends_on ? ' to '.$member->term_ends_on->toInstitutionalDate() : '' }}</td>
                <td>{{ ucfirst($member->status) }}</td>
                <td class="text-end">@can('governance.manage')<form method="POST" action="{{ route('governance.members.status', $member->uuid) }}">@csrf<input type="hidden" name="status" value="{{ $member->status === 'active' ? 'former' : 'active' }}"><button class="kt-btn kt-btn-xs kt-btn-outline" type="submit">{{ $member->status === 'active' ? 'Mark as former' : 'Reactivate' }}</button></form>@endcan</td>
            </tr>
        @empty
            <tr><td colspan="6" class="text-center text-muted-foreground py-6">No members yet.</td></tr>
        @endforelse
        </tbody>
    </table></div></div>
</div>
@endsection
