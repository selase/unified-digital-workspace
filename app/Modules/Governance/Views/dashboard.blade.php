@extends('layouts.metronic.app')

@section('title', 'Board & Governance')

@section('content')
<div class="flex flex-col gap-6">
    <div><h1 class="text-xl font-bold text-foreground">Board & Governance</h1><p class="text-xs text-muted-foreground">Meetings, minutes, resolutions and the actions agreed at them.</p></div>

    @include('governance::partials.nav')

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="rounded-lg bg-muted/30 p-4"><div class="text-xs text-muted-foreground">Active members</div><div class="mt-1 text-lg font-semibold">{{ $activeMembers }}</div></div>
        <div class="rounded-lg bg-muted/30 p-4"><div class="text-xs text-muted-foreground">Upcoming meetings</div><div class="mt-1 text-lg font-semibold">{{ $upcoming->count() }}</div></div>
        <div class="rounded-lg bg-muted/30 p-4"><div class="text-xs text-muted-foreground">Minutes to approve</div><div class="mt-1 text-lg font-semibold">{{ $unapproved->count() }}</div></div>
        <div class="rounded-lg bg-muted/30 p-4"><div class="text-xs text-muted-foreground">Overdue actions</div><div class="mt-1 text-lg font-semibold {{ $overdue ? 'text-destructive' : '' }}">{{ $overdue }}</div></div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="kt-card">
            <div class="kt-card-header"><h3 class="kt-card-title">Upcoming meetings</h3></div>
            <div class="kt-card-table kt-scrollable-x-auto"><table class="kt-table table-auto kt-table-border">
                <tbody>
                @forelse($upcoming as $meeting)
                    <tr><td><a class="text-primary" href="{{ route('governance.meetings.show', $meeting->uuid) }}">{{ $meeting->title }}</a><div class="text-xs text-muted-foreground">{{ $meeting->body }}{{ $meeting->venue ? ' · '.$meeting->venue : '' }}</div></td><td class="text-end">{{ $meeting->meets_at->format('d/m/Y H:i') }}</td></tr>
                @empty
                    <tr><td class="text-center text-muted-foreground py-6">Nothing scheduled.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
        <div class="kt-card">
            <div class="kt-card-header"><h3 class="kt-card-title">Minutes waiting for approval</h3></div>
            <div class="kt-card-table kt-scrollable-x-auto"><table class="kt-table table-auto kt-table-border">
                <tbody>
                @forelse($unapproved as $meeting)
                    <tr><td><a class="text-primary" href="{{ route('governance.meetings.show', $meeting->uuid) }}">{{ $meeting->title }}</a></td><td class="text-end">{{ $meeting->meets_at->format('d/m/Y') }}</td><td>{{ $meeting->minutes ? 'Draft written' : 'Not written' }}</td></tr>
                @empty
                    <tr><td class="text-center text-muted-foreground py-6">All minutes are approved.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
        <div class="kt-card">
            <div class="kt-card-header"><h3 class="kt-card-title">Open actions</h3><a class="kt-btn kt-btn-sm kt-btn-outline" href="{{ route('governance.actions.index') }}">All actions</a></div>
            <div class="kt-card-table kt-scrollable-x-auto"><table class="kt-table table-auto kt-table-border">
                <tbody>
                @forelse($openActions as $action)
                    <tr><td>{{ $action->description }}<div class="text-xs text-muted-foreground">{{ $action->owner_name }}</div></td><td class="text-end {{ $action->isOverdue() ? 'text-destructive' : '' }}">{{ $action->due_on?->toInstitutionalDate() ?? 'No date' }}</td></tr>
                @empty
                    <tr><td class="text-center text-muted-foreground py-6">No open actions.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
        <div class="kt-card">
            <div class="kt-card-header"><h3 class="kt-card-title">Terms ending in 90 days</h3></div>
            <div class="kt-card-table kt-scrollable-x-auto"><table class="kt-table table-auto kt-table-border">
                <tbody>
                @forelse($ending as $member)
                    <tr><td>{{ $member->name }}<div class="text-xs text-muted-foreground">{{ $member->position }}, {{ $member->body }}</div></td><td class="text-end">{{ $member->term_ends_on->toInstitutionalDate() }}</td></tr>
                @empty
                    <tr><td class="text-center text-muted-foreground py-6">No terms are ending soon.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
    </div>
</div>
@endsection
