@extends('layouts.metronic.app')

@section('title', 'Donations')

@section('content')
@php use App\Modules\Finance\Support\Money; $preselect = $preselectedDonor ? $donors->firstWhere('uuid', $preselectedDonor)?->id : null; @endphp
<div class="flex flex-col gap-6">
    <div>
        <h1 class="text-xl font-bold text-foreground">Donations</h1>
        <p class="text-xs text-muted-foreground">Record a gift and it posts to the ledger, against its fund, straight away.</p>
    </div>

    @include('donations::partials.nav')

    @can('donations.record')
    <form method="POST" action="{{ route('donations.donations.store') }}" class="kt-card">
        @csrf
        <div class="kt-card-header"><h3 class="kt-card-title">Record a donation</h3></div>
        <div class="kt-card-content grid grid-cols-1 md:grid-cols-4 gap-3">
            <select class="kt-select" name="donor_id" required>
                <option value="">Donor</option>
                @foreach($donors as $donor)<option value="{{ $donor->id }}" @selected((int) old('donor_id', $preselect) === $donor->id)>{{ $donor->name }}</option>@endforeach
            </select>
            <input class="kt-input" type="date" name="date" value="{{ old('date', today()->toDateString()) }}" required>
            <input class="kt-input" type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount') }}" placeholder="Amount" required>
            <select class="kt-select" name="method" required>
                @foreach(['bank_transfer' => 'Bank transfer', 'mobile_money' => 'Mobile money', 'cash' => 'Cash', 'cheque' => 'Cheque', 'card' => 'Card', 'other' => 'Other'] as $key => $label)<option value="{{ $key }}" @selected(old('method') === $key)>{{ $label }}</option>@endforeach
            </select>
            <select class="kt-select" name="campaign_id"><option value="">No campaign</option>@foreach($campaigns as $campaign)<option value="{{ $campaign->id }}" @selected((int) old('campaign_id') === $campaign->id)>{{ $campaign->name }}</option>@endforeach</select>
            <select class="kt-select" name="fund_id"><option value="">Fund (campaign's, or none)</option>@foreach($funds as $fund)<option value="{{ $fund->id }}" @selected((int) old('fund_id') === $fund->id)>{{ $fund->name }}{{ $fund->is_restricted ? ' (restricted)' : '' }}</option>@endforeach</select>
            <select class="kt-select" name="deposit_account_id"><option value="">Deposit to Bank</option>@foreach($depositAccounts as $account)<option value="{{ $account->id }}">{{ $account->name }}</option>@endforeach</select>
            <input class="kt-input" name="reference" value="{{ old('reference') }}" placeholder="Reference">
            <textarea class="kt-input md:col-span-3" name="notes" rows="1" placeholder="Notes">{{ old('notes') }}</textarea>
            <div><button class="kt-btn kt-btn-primary" type="submit">Record donation</button></div>
        </div>
    </form>
    @endcan

    <div class="kt-card">
        <div class="kt-card-table kt-scrollable-x-auto">
            <table class="kt-table table-auto kt-table-border">
                <thead><tr><th>Receipt</th><th>Date</th><th>Donor</th><th>Campaign</th><th>Fund</th><th class="text-end">Amount</th><th></th></tr></thead>
                <tbody>
                @forelse($donations as $gift)
                    <tr class="{{ $gift->voided_at ? 'text-muted-foreground line-through' : '' }}">
                        <td>{{ $gift->receipt_number }}@if($gift->receipt_sent_at)<div class="text-xs text-muted-foreground no-underline">emailed</div>@endif</td>
                        <td>{{ $gift->date->toInstitutionalDate() }}</td>
                        <td><a class="text-primary" href="{{ route('donations.donors.show', $gift->donor->uuid) }}">{{ $gift->donor->name }}</a></td>
                        <td>{{ $gift->campaign?->name }}</td>
                        <td>{{ $gift->fund?->name }}</td>
                        <td class="text-end">{{ Money::format($gift->amount, $currency) }}</td>
                        <td class="text-end">
                            <div class="flex justify-end gap-1">
                                <a class="kt-btn kt-btn-xs kt-btn-outline" target="_blank" href="{{ route('donations.donations.receipt', $gift->uuid) }}">Receipt</a>
                                @can('donations.record')
                                @unless($gift->voided_at)
                                    @if($gift->donor->email)<form method="POST" action="{{ route('donations.donations.email', $gift->uuid) }}">@csrf<button class="kt-btn kt-btn-xs kt-btn-outline" type="submit">Email</button></form>@endif
                                    <form method="POST" action="{{ route('donations.donations.void', $gift->uuid) }}" onsubmit="return confirm('Void this donation?')">@csrf<button class="kt-btn kt-btn-xs kt-btn-outline" type="submit">Void</button></form>
                                @endunless
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted-foreground py-6">No donations yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    {{ $donations->links() }}
</div>
@endsection
