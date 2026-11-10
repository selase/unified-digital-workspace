@extends('layouts.metronic.app')

@section('title', 'Memberships')

@section('content')
@php use App\Modules\Finance\Support\Money; @endphp
<div class="flex flex-col gap-6">
    <div><h1 class="text-xl font-bold text-foreground">Memberships</h1><p class="text-xs text-muted-foreground">Members, dues and renewals. Dues are invoiced through Finance, and a member is active once their invoice is paid.</p></div>

    @include('memberships::partials.nav')

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="rounded-lg bg-muted/30 p-4"><div class="text-xs text-muted-foreground">Active members</div><div class="mt-1 text-lg font-semibold">{{ $summary['by_status']['active'] ?? 0 }}</div></div>
        <div class="rounded-lg bg-muted/30 p-4"><div class="text-xs text-muted-foreground">Awaiting first payment</div><div class="mt-1 text-lg font-semibold"><a class="text-primary" href="{{ route('memberships.members.index', ['status' => 'pending']) }}">{{ $summary['by_status']['pending'] ?? 0 }}</a></div></div>
        <div class="rounded-lg bg-muted/30 p-4"><div class="text-xs text-muted-foreground">Dues collected this year</div><div class="mt-1 text-lg font-semibold">{{ Money::format($summary['collected'], $currency) }}</div></div>
        <div class="rounded-lg bg-muted/30 p-4"><div class="text-xs text-muted-foreground">Dues outstanding</div><div class="mt-1 text-lg font-semibold">{{ Money::format($summary['outstanding'], $currency) }}</div></div>
    </div>

    <div class="rounded-lg bg-muted/30 p-4 text-sm flex flex-col md:flex-row md:items-center gap-3" x-data="{ copied: false }">
        <div class="flex-1"><div class="font-medium text-foreground">Your public join page</div><div class="text-xs text-muted-foreground">Share this link so people can apply. They are sent to pay their first dues online when your payment account is connected.</div></div>
        <input class="kt-input md:w-96" readonly id="join-link" value="{{ $joinUrl }}" onclick="this.select()">
        <button type="button" class="kt-btn kt-btn-outline" @click="navigator.clipboard.writeText(document.getElementById('join-link').value); copied = true"><span x-text="copied ? 'Copied' : 'Copy link'"></span></button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="kt-card">
            <div class="kt-card-header"><h3 class="kt-card-title">Active members by type</h3></div>
            <div class="kt-card-table kt-scrollable-x-auto"><table class="kt-table table-auto kt-table-border">
                <thead><tr><th>Type</th><th class="text-end">Fee</th><th class="text-end">Active</th></tr></thead>
                <tbody>
                @forelse($summary['by_type'] as $row)
                    <tr><td>{{ $row['type']->name }}</td><td class="text-end">{{ Money::format($row['type']->fee, $currency) }} / {{ $row['type']->period_months }} mo</td><td class="text-end">{{ $row['active'] }}</td></tr>
                @empty
                    <tr><td colspan="3" class="text-center text-muted-foreground py-6">No membership types yet. <a class="text-primary" href="{{ route('memberships.types.index') }}">Create one</a>.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
        <div class="kt-card">
            <div class="kt-card-header"><h3 class="kt-card-title">Renewals in the next 60 days</h3></div>
            <div class="kt-card-table kt-scrollable-x-auto"><table class="kt-table table-auto kt-table-border">
                <thead><tr><th>Member</th><th>Type</th><th class="text-end">Ends</th></tr></thead>
                <tbody>
                @forelse($renewals as $member)
                    <tr><td><a class="text-primary" href="{{ route('memberships.members.show', $member->uuid) }}">{{ $member->name }}</a></td><td>{{ $member->type->name }}</td><td class="text-end">{{ $member->expires_on->toInstitutionalDate() }}</td></tr>
                @empty
                    <tr><td colspan="3" class="text-center text-muted-foreground py-6">No renewals due.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
    </div>
</div>
@endsection
