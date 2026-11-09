@extends('layouts.metronic.app')

@section('title', 'Grants')

@section('content')
@php use App\Modules\Finance\Support\Money; use App\Modules\Grants\Models\GrtGrant; @endphp
<div class="flex flex-col gap-6">
    <div><h1 class="text-xl font-bold text-foreground">Grants</h1><p class="text-xs text-muted-foreground">Each grant gets its own restricted fund so its income and spending can be reported separately.</p></div>

    @include('grants::partials.nav')

    @can('grants.manage')
    <form method="POST" action="{{ route('grants.grants.store') }}" class="kt-card">
        @csrf
        <div class="kt-card-header"><h3 class="kt-card-title">New grant</h3></div>
        <div class="kt-card-content grid grid-cols-1 md:grid-cols-4 gap-3">
            <select class="kt-select" name="funder_id" required><option value="">Funder</option>@foreach($funders as $funder)<option value="{{ $funder->id }}" @selected((int) old('funder_id') === $funder->id)>{{ $funder->name }}</option>@endforeach</select>
            <input class="kt-input md:col-span-2" name="title" value="{{ old('title') }}" placeholder="Grant title" required>
            <input class="kt-input" type="number" step="0.01" min="0.01" name="total_amount" value="{{ old('total_amount') }}" placeholder="Total amount" required>
            <input class="kt-input" name="reference" value="{{ old('reference') }}" placeholder="Agreement reference">
            <input class="kt-input" type="date" name="starts_on" value="{{ old('starts_on') }}" title="Starts">
            <input class="kt-input" type="date" name="ends_on" value="{{ old('ends_on') }}" title="Ends">
            <select class="kt-select" name="status">@foreach(GrtGrant::STATUSES as $key => $label)<option value="{{ $key }}" @selected(old('status', 'pipeline') === $key)>{{ $label }}</option>@endforeach</select>
            <textarea class="kt-input md:col-span-3" name="purpose" rows="1" placeholder="What the money must be used for">{{ old('purpose') }}</textarea>
            <div><button class="kt-btn kt-btn-primary" type="submit">Save grant</button></div>
        </div>
    </form>
    @endcan

    <div class="kt-card"><div class="kt-card-table kt-scrollable-x-auto"><table class="kt-table table-auto kt-table-border">
        <thead><tr><th>Grant</th><th>Funder</th><th>Period</th><th>Status</th><th class="text-end">Amount</th></tr></thead>
        <tbody>
        @forelse($grants as $grant)
            <tr>
                <td><a class="text-primary" href="{{ route('grants.grants.show', $grant->uuid) }}">{{ $grant->title }}</a><div class="text-xs text-muted-foreground">{{ $grant->reference }}</div></td>
                <td>{{ $grant->funder->name }}</td>
                <td>{{ $grant->starts_on?->toInstitutionalDate() }} {{ $grant->ends_on ? 'to '.$grant->ends_on->toInstitutionalDate() : '' }}</td>
                <td>{{ GrtGrant::STATUSES[$grant->status] ?? $grant->status }}</td>
                <td class="text-end">{{ Money::format($grant->total_amount, $currency) }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-center text-muted-foreground py-6">No grants yet.</td></tr>
        @endforelse
        </tbody>
    </table></div></div>
</div>
@endsection
