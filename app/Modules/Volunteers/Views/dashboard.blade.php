@extends('layouts.metronic.app')

@section('title', 'Volunteers')

@section('content')
<div class="flex flex-col gap-6">
    <div><h1 class="text-xl font-bold text-foreground">Volunteers</h1><p class="text-xs text-muted-foreground">The people who give their time. Hours count once approved.</p></div>

    @include('volunteers::partials.nav')

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="rounded-lg bg-muted/30 p-4"><div class="text-xs text-muted-foreground">Active volunteers</div><div class="mt-1 text-lg font-semibold">{{ $active }}</div></div>
        <div class="rounded-lg bg-muted/30 p-4"><div class="text-xs text-muted-foreground">Hours this year</div><div class="mt-1 text-lg font-semibold">{{ number_format($summary['total_minutes'] / 60, 1) }}</div></div>
        <div class="rounded-lg bg-muted/30 p-4"><div class="text-xs text-muted-foreground">Sign-ups to approve</div><div class="mt-1 text-lg font-semibold"><a class="text-primary" href="{{ route('volunteers.volunteers.index', ['status' => 'pending']) }}">{{ $pending }}</a></div></div>
        <div class="rounded-lg bg-muted/30 p-4"><div class="text-xs text-muted-foreground">Hours to approve</div><div class="mt-1 text-lg font-semibold"><a class="text-primary" href="{{ route('volunteers.hours.index') }}">{{ $pendingHours }}</a></div></div>
    </div>

    <div class="rounded-lg bg-muted/30 p-4 text-sm flex flex-col md:flex-row md:items-center gap-3" x-data="{ copied: false }">
        <div class="flex-1"><div class="font-medium text-foreground">Your public sign-up page</div><div class="text-xs text-muted-foreground">Share this link so people can offer to volunteer. New sign-ups wait here for your approval.</div></div>
        <input class="kt-input md:w-96" readonly id="signup-link" value="{{ $signupUrl }}" onclick="this.select()">
        <button type="button" class="kt-btn kt-btn-outline" @click="navigator.clipboard.writeText(document.getElementById('signup-link').value); copied = true"><span x-text="copied ? 'Copied' : 'Copy link'"></span></button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="kt-card">
            <div class="kt-card-header"><h3 class="kt-card-title">Most hours this year</h3></div>
            <div class="kt-card-table kt-scrollable-x-auto"><table class="kt-table table-auto kt-table-border">
                <thead><tr><th>Volunteer</th><th class="text-end">Hours</th></tr></thead>
                <tbody>
                @forelse($summary['top'] as $row)
                    <tr><td><a class="text-primary" href="{{ route('volunteers.volunteers.show', $row['volunteer']->uuid) }}">{{ $row['volunteer']->name }}</a></td><td class="text-end">{{ number_format($row['minutes'] / 60, 1) }}</td></tr>
                @empty
                    <tr><td colspan="2" class="text-center text-muted-foreground py-6">No approved hours yet.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
        <div class="kt-card">
            <div class="kt-card-header"><h3 class="kt-card-title">Coming up</h3><a class="kt-btn kt-btn-sm kt-btn-outline" href="{{ route('volunteers.opportunities.index') }}">All opportunities</a></div>
            <div class="kt-card-table kt-scrollable-x-auto"><table class="kt-table table-auto kt-table-border">
                <thead><tr><th>Date</th><th>Opportunity</th><th class="text-end">Places</th></tr></thead>
                <tbody>
                @forelse($upcoming as $item)
                    <tr><td>{{ $item->starts_on->toInstitutionalDate() }}</td><td>{{ $item->title }}<div class="text-xs text-muted-foreground">{{ $item->location }}</div></td><td class="text-end">{{ $item->volunteers_count }}{{ $item->spots > 0 ? ' / '.$item->spots : '' }}</td></tr>
                @empty
                    <tr><td colspan="3" class="text-center text-muted-foreground py-6">Nothing scheduled.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
    </div>
</div>
@endsection
