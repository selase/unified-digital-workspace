@extends('layouts.metronic.app')

@section('title', 'Donations')

@section('content')
@php
    use App\Modules\Finance\Support\Money;
    $peak = max(1, collect($summary['by_month'])->max('total'));
@endphp
<div class="flex flex-col gap-6">
    <div>
        <h1 class="text-xl font-bold text-foreground">Donations</h1>
        <p class="text-xs text-muted-foreground">Gifts received from {{ $from->toInstitutionalDate() }} to today. Every gift is already in the books.</p>
    </div>

    @include('donations::partials.nav')

    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
        <div class="rounded-lg bg-muted/30 p-4"><div class="text-xs text-muted-foreground">Raised this year</div><div class="mt-1 text-lg font-semibold">{{ Money::format($summary['total'], $currency) }}</div></div>
        <div class="rounded-lg bg-muted/30 p-4"><div class="text-xs text-muted-foreground">Gifts</div><div class="mt-1 text-lg font-semibold">{{ $summary['count'] }}</div></div>
        <div class="rounded-lg bg-muted/30 p-4"><div class="text-xs text-muted-foreground">Donors who gave</div><div class="mt-1 text-lg font-semibold">{{ $summary['donors'] }}</div></div>
        <div class="rounded-lg bg-muted/30 p-4"><div class="text-xs text-muted-foreground">Pledges due</div><div class="mt-1 text-lg font-semibold"><a class="text-primary" href="{{ route('donations.pledges.index') }}">{{ $pledgesDue }}</a></div></div>
        <div class="rounded-lg bg-muted/30 p-4"><div class="text-xs text-muted-foreground">Average gift</div><div class="mt-1 text-lg font-semibold">{{ Money::format($summary['average'], $currency) }}</div></div>
    </div>

    <div class="rounded-lg bg-muted/30 p-4 text-sm flex flex-col md:flex-row md:items-center gap-3" x-data="{ copied: false }">
        <div class="flex-1">
            <div class="font-medium text-foreground">Your public giving page</div>
            <div class="text-xs text-muted-foreground">Share this link on your website, WhatsApp or social media. {{ $onlineGiving ? 'Online giving is on: gifts are recorded and receipted automatically.' : 'Online giving needs Paystack keys under Accounting settings; until then the page tells visitors to contact you.' }}</div>
        </div>
        <input class="kt-input md:w-96" readonly id="give-link" value="{{ $giveUrl }}" onclick="this.select()">
        <button type="button" class="kt-btn kt-btn-outline" @click="navigator.clipboard.writeText(document.getElementById('give-link').value); copied = true"><span x-text="copied ? 'Copied' : 'Copy link'"></span></button>
    </div>

    <div class="kt-card">
        <div class="kt-card-header"><h3 class="kt-card-title">Giving by month</h3></div>
        <div class="kt-card-content">
            <div class="flex items-end gap-2 h-40">
                @foreach($summary['by_month'] as $month)
                    <div class="flex-1 flex flex-col items-center justify-end gap-1 h-full" title="{{ $month['month'] }}: {{ Money::format($month['total'], $currency) }}">
                        <div class="w-full rounded-t bg-primary" style="height: {{ max(2, intdiv($month['total'] * 100, $peak)) }}%"></div>
                        <div class="text-[10px] text-muted-foreground">{{ mb_substr($month['month'], 0, 3) }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="kt-card">
            <div class="kt-card-header"><h3 class="kt-card-title">Active campaigns</h3><a class="kt-btn kt-btn-sm kt-btn-outline" href="{{ route('donations.campaigns.index') }}">All campaigns</a></div>
            <div class="kt-card-content flex flex-col gap-4">
                @forelse($campaigns as $row)
                    <div>
                        <div class="flex justify-between text-sm"><span>{{ $row['campaign']->name }}</span><span>{{ Money::format($row['raised'], $currency) }}@if($row['campaign']->goal_amount > 0) of {{ Money::format($row['campaign']->goal_amount, $currency) }}@endif</span></div>
                        @if($row['campaign']->goal_amount > 0)<div class="mt-1 h-2 rounded bg-muted"><div class="h-2 rounded bg-primary" style="width: {{ $row['percent'] }}%"></div></div>@endif
                    </div>
                @empty
                    <p class="text-sm text-muted-foreground">No active campaigns. Create one to track a goal.</p>
                @endforelse
            </div>
        </div>
        <div class="kt-card">
            <div class="kt-card-header"><h3 class="kt-card-title">Top donors this year</h3></div>
            <div class="kt-card-table kt-scrollable-x-auto">
                <table class="kt-table table-auto kt-table-border">
                    <thead><tr><th>Donor</th><th class="text-end">Gifts</th><th class="text-end">Total</th></tr></thead>
                    <tbody>
                    @forelse($topDonors as $row)
                        <tr><td>{{ $row->name }}</td><td class="text-end">{{ $row->gifts }}</td><td class="text-end">{{ Money::format($row->total, '') }}</td></tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-muted-foreground py-6">No gifts yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="kt-card">
        <div class="kt-card-header"><h3 class="kt-card-title">Latest gifts</h3><a class="kt-btn kt-btn-sm kt-btn-outline" href="{{ route('donations.donations.index') }}">Record a donation</a></div>
        <div class="kt-card-table kt-scrollable-x-auto">
            <table class="kt-table table-auto kt-table-border">
                <thead><tr><th>Date</th><th>Donor</th><th>Campaign</th><th class="text-end">Amount</th></tr></thead>
                <tbody>
                @forelse($recent as $gift)
                    <tr><td>{{ $gift->date->toInstitutionalDate() }}</td><td>{{ $gift->donor->name }}</td><td>{{ $gift->campaign?->name }}</td><td class="text-end">{{ Money::format($gift->amount, $currency) }}</td></tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted-foreground py-6">No donations recorded yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
