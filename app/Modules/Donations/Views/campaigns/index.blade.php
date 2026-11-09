@extends('layouts.metronic.app')

@section('title', 'Campaigns')

@section('content')
@php use App\Modules\Finance\Support\Money; @endphp
<div class="flex flex-col gap-6">
    <div>
        <h1 class="text-xl font-bold text-foreground">Campaigns</h1>
        <p class="text-xs text-muted-foreground">Appeals with a goal. Link a campaign to a fund and every gift to it lands in that fund automatically.</p>
    </div>

    @include('donations::partials.nav')

    @can('donations.campaigns.manage')
    <form method="POST" action="{{ route('donations.campaigns.store') }}" class="kt-card">
        @csrf
        <div class="kt-card-header"><h3 class="kt-card-title">New campaign</h3></div>
        <div class="kt-card-content grid grid-cols-1 md:grid-cols-4 gap-3">
            <input class="kt-input md:col-span-2" name="name" value="{{ old('name') }}" placeholder="Campaign name" required>
            <input class="kt-input" type="number" step="0.01" min="0" name="goal_amount" value="{{ old('goal_amount') }}" placeholder="Goal amount">
            <select class="kt-select" name="fund_id"><option value="">No fund</option>@foreach($funds as $fund)<option value="{{ $fund->id }}" @selected((int) old('fund_id') === $fund->id)>{{ $fund->name }}{{ $fund->is_restricted ? ' (restricted)' : '' }}</option>@endforeach</select>
            <input class="kt-input" type="date" name="starts_on" value="{{ old('starts_on') }}" title="Starts">
            <input class="kt-input" type="date" name="ends_on" value="{{ old('ends_on') }}" title="Ends">
            <textarea class="kt-input md:col-span-2" name="description" rows="1" placeholder="What is it for?">{{ old('description') }}</textarea>
            <div><button class="kt-btn kt-btn-primary" type="submit">Create campaign</button></div>
        </div>
    </form>
    @endcan

    <div class="kt-card">
        <div class="kt-card-table kt-scrollable-x-auto">
            <table class="kt-table table-auto kt-table-border">
                <thead><tr><th>Campaign</th><th>Fund</th><th class="text-end">Gifts</th><th class="text-end">Raised</th><th class="text-end">Goal</th><th>Progress</th><th></th></tr></thead>
                <tbody>
                @forelse($rows as $row)
                    <tr class="{{ $row['campaign']->is_active ? '' : 'text-muted-foreground' }}">
                        <td>{{ $row['campaign']->name }} @unless($row['campaign']->is_active)<span class="text-xs">(closed)</span>@endunless<div class="text-xs text-muted-foreground">{{ $row['campaign']->description }}</div></td>
                        <td>{{ $row['campaign']->fund?->name }}</td>
                        <td class="text-end">{{ $row['gifts'] }}</td>
                        <td class="text-end">{{ Money::format($row['raised'], $currency) }}</td>
                        <td class="text-end">{{ $row['campaign']->goal_amount > 0 ? Money::format($row['campaign']->goal_amount, $currency) : '—' }}</td>
                        <td class="w-48">@if($row['campaign']->goal_amount > 0)<div class="h-2 rounded bg-muted"><div class="h-2 rounded bg-primary" style="width: {{ $row['percent'] }}%"></div></div><div class="text-xs text-muted-foreground">{{ $row['percent'] }}%</div>@endif</td>
                        <td class="text-end">@can('donations.campaigns.manage')<form method="POST" action="{{ route('donations.campaigns.toggle', $row['campaign']->uuid) }}">@csrf<button class="kt-btn kt-btn-xs kt-btn-outline" type="submit">{{ $row['campaign']->is_active ? 'Close' : 'Reopen' }}</button></form>@endcan</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted-foreground py-6">No campaigns yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
