@extends('layouts.metronic.app')

@section('title', 'Beneficiary register')

@section('content')
<div class="flex flex-col gap-6">
    <div><h1 class="text-xl font-bold text-foreground">Register</h1><p class="text-xs text-muted-foreground">Register a person, record their consent, then record services and open cases. Every time a record is opened it is logged.</p></div>

    @include('beneficiaries::partials.nav')

    @can('beneficiaries.manage')
    <form method="POST" action="{{ route('beneficiaries.beneficiaries.store') }}" class="kt-card">@csrf
        <div class="kt-card-header"><h3 class="kt-card-title">Register a person</h3></div>
        <div class="kt-card-content grid grid-cols-1 md:grid-cols-4 gap-3">
            <input class="kt-input md:col-span-2" name="full_name" value="{{ old('full_name') }}" placeholder="Full name" required>
            <input class="kt-input" type="date" name="date_of_birth" value="{{ old('date_of_birth') }}" title="Date of birth">
            <select class="kt-select" name="gender"><option value="">Gender</option>@foreach(['female' => 'Female', 'male' => 'Male', 'other' => 'Other'] as $key => $label)<option value="{{ $key }}" @selected(old('gender') === $key)>{{ $label }}</option>@endforeach</select>
            <input class="kt-input" name="phone" value="{{ old('phone') }}" placeholder="Phone">
            <input class="kt-input" name="community" value="{{ old('community') }}" placeholder="Community / village">
            <input class="kt-input" name="region" value="{{ old('region') }}" placeholder="Region">
            <input class="kt-input" type="number" min="1" name="household_size" value="{{ old('household_size') }}" placeholder="Household size">
            <input class="kt-input md:col-span-2" name="programme" value="{{ old('programme') }}" placeholder="Programme, e.g. School feeding">
            <div><button class="kt-btn kt-btn-primary" type="submit">Register</button></div>
        </div>
    </form>
    @endcan

    <div class="kt-card">
        <div class="kt-card-header"><form method="GET" class="flex gap-2">
            <input class="kt-input" name="search" value="{{ $search }}" placeholder="{{ $mayViewPersonal ? 'Search reference or name' : 'Search reference' }}">
            <select class="kt-select w-56" name="programme"><option value="">All programmes</option>@foreach($programmes as $p)<option value="{{ $p }}" @selected($programme === $p)>{{ $p }}</option>@endforeach</select>
            <button class="kt-btn kt-btn-outline" type="submit">Filter</button>
        </form></div>
        <div class="kt-card-table kt-scrollable-x-auto"><table class="kt-table table-auto kt-table-border">
            <thead><tr><th>Reference</th><th>Name</th><th>Programme</th><th>Community</th><th>Consent</th><th>Status</th></tr></thead>
            <tbody>
            @forelse($beneficiaries as $b)
                <tr>
                    <td><a class="text-primary" href="{{ route('beneficiaries.beneficiaries.show', $b->uuid) }}">{{ $b->reference }}</a></td>
                    <td>{{ $b->displayName($mayViewPersonal) }}</td>
                    <td>{{ $b->programme }}</td>
                    <td>{{ $b->community }}</td>
                    <td class="{{ $b->hasValidConsent() ? '' : 'text-destructive' }}">{{ $b->isAnonymised() ? 'n/a' : ($b->hasValidConsent() ? 'Recorded' : ($b->consent_withdrawn_on ? 'Withdrawn' : 'Missing')) }}</td>
                    <td>{{ $b->isAnonymised() ? 'Anonymised' : ucfirst($b->status) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted-foreground py-6">No one found.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>
    {{ $beneficiaries->links() }}
</div>
@endsection
