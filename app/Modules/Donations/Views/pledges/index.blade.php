@extends('layouts.metronic.app')

@section('title', 'Pledges')

@section('content')
@php use App\Modules\Finance\Support\Money; use App\Modules\Donations\Models\DonPledge; @endphp
<div class="flex flex-col gap-6">
    <div>
        <h1 class="text-xl font-bold text-foreground">Pledges</h1>
        <p class="text-xs text-muted-foreground">Promises to give, one-off or regular. Nothing is booked until you receive an instalment; then it becomes a donation with a receipt.</p>
    </div>

    @include('donations::partials.nav')

    @if(session('pledge_link'))
    <div class="rounded-lg bg-muted/30 p-4 text-sm flex flex-col md:flex-row md:items-center gap-3" x-data="{ copied: false }">
        <div class="flex-1"><div class="font-medium text-foreground">Payment link for {{ session('pledge_link')['donor'] }}</div><div class="text-xs text-muted-foreground">Send this to the donor. It opens the payment page for their usual amount and stays valid for 30 days.</div></div>
        <input class="kt-input md:w-96" readonly id="pledge-link" value="{{ session('pledge_link')['url'] }}" onclick="this.select()">
        <button type="button" class="kt-btn kt-btn-outline" @click="navigator.clipboard.writeText(document.getElementById('pledge-link').value); copied = true"><span x-text="copied ? 'Copied' : 'Copy link'"></span></button>
    </div>
    @endif

    @can('donations.record')
    <form method="POST" action="{{ route('donations.pledges.store') }}" class="kt-card">
        @csrf
        <div class="kt-card-header"><h3 class="kt-card-title">New pledge</h3></div>
        <div class="kt-card-content grid grid-cols-1 md:grid-cols-4 gap-3">
            <select class="kt-select" name="donor_id" required><option value="">Donor</option>@foreach($donors as $donor)<option value="{{ $donor->id }}" @selected((int) old('donor_id') === $donor->id)>{{ $donor->name }}</option>@endforeach</select>
            <input class="kt-input" type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount') }}" placeholder="Amount each time" required>
            <select class="kt-select" name="frequency">@foreach(DonPledge::FREQUENCIES as $key => $label)<option value="{{ $key }}" @selected(old('frequency', 'monthly') === $key)>{{ $label }}</option>@endforeach</select>
            <input class="kt-input" type="date" name="starts_on" value="{{ old('starts_on', today()->toDateString()) }}" title="First instalment due" required>
            <input class="kt-input" type="date" name="ends_on" value="{{ old('ends_on') }}" title="Ends (optional)">
            <select class="kt-select" name="campaign_id"><option value="">No campaign</option>@foreach($campaigns as $campaign)<option value="{{ $campaign->id }}">{{ $campaign->name }}</option>@endforeach</select>
            <select class="kt-select" name="fund_id"><option value="">Fund (campaign's, or none)</option>@foreach($funds as $fund)<option value="{{ $fund->id }}">{{ $fund->name }}</option>@endforeach</select>
            <div><button class="kt-btn kt-btn-primary" type="submit">Record pledge</button></div>
        </div>
    </form>
    @endcan

    <div class="kt-card">
        <div class="kt-card-table kt-scrollable-x-auto">
            <table class="kt-table table-auto kt-table-border">
                <thead><tr><th>Donor</th><th>Pledge</th><th>Next due</th><th class="text-end">Received</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse($pledges as $pledge)
                    <tr>
                        <td><a class="text-primary" href="{{ route('donations.donors.show', $pledge->donor->uuid) }}">{{ $pledge->donor->name }}</a><div class="text-xs text-muted-foreground">{{ $pledge->campaign?->name ?? $pledge->fund?->name }}</div></td>
                        <td>{{ Money::format($pledge->amount, $currency) }} · {{ DonPledge::FREQUENCIES[$pledge->frequency] }}</td>
                        <td class="{{ $pledge->isOverdue() ? 'text-destructive' : '' }}">{{ $pledge->next_due_on?->toInstitutionalDate() ?? '—' }}{{ $pledge->isOverdue() ? ' (overdue)' : '' }}</td>
                        <td class="text-end">{{ Money::format((int) $pledge->received, $currency) }}</td>
                        <td>{{ ucfirst($pledge->status) }}</td>
                        <td class="text-end">
                            @can('donations.record')
                            @if($pledge->status === 'active')
                                <div class="flex justify-end gap-1">
                                    <form method="POST" action="{{ route('donations.pledges.link', $pledge->uuid) }}">@csrf<button class="kt-btn kt-btn-xs kt-btn-outline" type="submit">Payment link</button></form>
                                    <form method="POST" action="{{ route('donations.pledges.receive', $pledge->uuid) }}">@csrf<button class="kt-btn kt-btn-xs kt-btn-primary" type="submit">Receive instalment</button></form>
                                    <form method="POST" action="{{ route('donations.pledges.status', $pledge->uuid) }}">@csrf<input type="hidden" name="status" value="paused"><button class="kt-btn kt-btn-xs kt-btn-outline" type="submit">Pause</button></form>
                                    <form method="POST" action="{{ route('donations.pledges.status', $pledge->uuid) }}" onsubmit="return confirm('Cancel this pledge?')">@csrf<input type="hidden" name="status" value="cancelled"><button class="kt-btn kt-btn-xs kt-btn-outline" type="submit">Cancel</button></form>
                                </div>
                            @elseif($pledge->status === 'paused')
                                <form method="POST" action="{{ route('donations.pledges.status', $pledge->uuid) }}">@csrf<input type="hidden" name="status" value="active"><button class="kt-btn kt-btn-xs kt-btn-outline" type="submit">Resume</button></form>
                            @endif
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted-foreground py-6">No pledges yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
