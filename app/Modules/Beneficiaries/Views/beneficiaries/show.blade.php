@extends('layouts.metronic.app')

@section('title', $beneficiary->reference)

@section('content')
@php use App\Modules\Beneficiaries\Models\BenBeneficiary; use App\Modules\Beneficiaries\Models\BenCase; use App\Modules\Finance\Support\Money; $locked = ! $beneficiary->hasValidConsent(); @endphp
<div class="flex flex-col gap-6">
    <div>
        <h1 class="text-xl font-bold text-foreground">{{ $beneficiary->reference }} · {{ $beneficiary->displayName($mayViewPersonal) }}</h1>
        <p class="text-xs text-muted-foreground">{{ $beneficiary->programme }} · {{ $beneficiary->isAnonymised() ? 'Anonymised' : ucfirst($beneficiary->status) }}@if($mayViewPersonal && $beneficiary->age() !== null) · {{ $beneficiary->age() }} years @endif{{ $beneficiary->gender ? ' · '.ucfirst($beneficiary->gender) : '' }}</p>
    </div>

    @include('beneficiaries::partials.nav')

    <div class="rounded-lg p-4 text-sm {{ $locked ? 'bg-red-50 text-red-700' : 'bg-green-50 text-green-700' }}">
        @if($beneficiary->isAnonymised())
            Personal details have been erased. Only anonymous counts remain.
        @elseif($beneficiary->hasValidConsent())
            Consent recorded on {{ $beneficiary->consent_recorded_on?->toInstitutionalDate() }}@if($mayViewPersonal): {{ $beneficiary->consent_scope }}@endif.
            @can('beneficiaries.manage')
            <form method="POST" action="{{ route('beneficiaries.beneficiaries.withdraw-consent', $beneficiary->uuid) }}" class="inline" onsubmit="return confirm('Withdraw consent? No new services or case work can be recorded.')">@csrf<button class="underline ms-2" type="submit">Withdraw consent</button></form>
            @endcan
        @else
            {{ $beneficiary->consent_withdrawn_on ? 'Consent was withdrawn on '.$beneficiary->consent_withdrawn_on->toInstitutionalDate().'.' : 'No consent recorded yet.' }} Services and case work are locked until consent is recorded.
        @endif
    </div>

    @if(! $beneficiary->isAnonymised())
    @can('beneficiaries.manage')
    @if($locked)
    <form method="POST" action="{{ route('beneficiaries.beneficiaries.consent', $beneficiary->uuid) }}" class="kt-card">@csrf
        <div class="kt-card-header"><h3 class="kt-card-title">Record consent</h3></div>
        <div class="kt-card-content grid grid-cols-1 md:grid-cols-4 gap-3">
            <input class="kt-input md:col-span-2" name="consent_scope" value="{{ old('consent_scope', 'Receive services, be included in programme reports') }}" placeholder="What did they agree to?" required>
            <input class="kt-input" type="date" name="consent_date" value="{{ old('consent_date', today()->toDateString()) }}" required>
            <div><button class="kt-btn kt-btn-primary" type="submit">Record consent</button></div>
        </div>
    </form>
    @endif
    @endcan

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="kt-card">
            <div class="kt-card-header"><h3 class="kt-card-title">Services</h3></div>
            @can('beneficiaries.manage')
            @unless($locked)
            <form method="POST" action="{{ route('beneficiaries.beneficiaries.services.store', $beneficiary->uuid) }}" class="kt-card-content grid grid-cols-2 gap-2">@csrf
                <input class="kt-input" type="date" name="delivered_on" value="{{ today()->toDateString() }}" required>
                <select class="kt-select" name="type" required>@foreach(BenBeneficiary::SERVICE_TYPES as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select>
                <input class="kt-input" name="description" placeholder="Details">
                <input class="kt-input" type="number" step="0.01" min="0" name="value" placeholder="Value (optional)">
                <div class="col-span-2"><button class="kt-btn kt-btn-outline" type="submit">Record service</button></div>
            </form>
            @endunless
            @endcan
            <div class="kt-card-table kt-scrollable-x-auto"><table class="kt-table table-auto kt-table-border">
                <thead><tr><th>Date</th><th>Service</th><th>Details</th><th class="text-end">Value</th></tr></thead>
                <tbody>
                @forelse($services as $s)<tr><td>{{ $s->delivered_on->toInstitutionalDate() }}</td><td>{{ BenBeneficiary::SERVICE_TYPES[$s->type] ?? $s->type }}</td><td>{{ $s->description }}</td><td class="text-end">{{ $s->value ? Money::format($s->value, '') : '' }}</td></tr>@empty<tr><td colspan="4" class="text-center text-muted-foreground py-6">No services yet.</td></tr>@endforelse
                </tbody>
            </table></div>
        </div>

        <div class="kt-card">
            <div class="kt-card-header"><h3 class="kt-card-title">Details</h3></div>
            <div class="kt-card-content grid grid-cols-2 gap-3 text-sm">
                <div><div class="text-xs text-muted-foreground">Phone</div>{{ $mayViewPersonal ? ($beneficiary->phone ?: '—') : 'Hidden' }}</div>
                <div><div class="text-xs text-muted-foreground">Date of birth</div>{{ $mayViewPersonal ? ($beneficiary->date_of_birth?->toInstitutionalDate() ?: '—') : 'Hidden' }}</div>
                <div><div class="text-xs text-muted-foreground">Community</div>{{ $beneficiary->community ?: '—' }}</div>
                <div><div class="text-xs text-muted-foreground">Region</div>{{ $beneficiary->region ?: '—' }}</div>
                <div><div class="text-xs text-muted-foreground">Household size</div>{{ $beneficiary->household_size ?: '—' }}</div>
                <div><div class="text-xs text-muted-foreground">Enrolled</div>{{ $beneficiary->enrolled_on?->toInstitutionalDate() }}</div>
            </div>
        </div>
    </div>

    <div class="kt-card">
        <div class="kt-card-header"><h3 class="kt-card-title">Cases</h3></div>
        @can('beneficiaries.cases.manage')
        @unless($locked)
        <form method="POST" action="{{ route('beneficiaries.beneficiaries.cases.store', $beneficiary->uuid) }}" class="kt-card-content grid grid-cols-1 md:grid-cols-4 gap-2">@csrf
            <input class="kt-input md:col-span-2" name="title" placeholder="Case title" required>
            <select class="kt-select" name="category">@foreach(BenCase::CATEGORIES as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select>
            <select class="kt-select" name="priority"><option value="normal">Normal</option><option value="high">High priority</option><option value="low">Low</option></select>
            <div><button class="kt-btn kt-btn-outline" type="submit">Open case</button></div>
        </form>
        @endunless
        @endcan
        <div class="kt-card-content flex flex-col gap-4">
        @forelse($cases as $case)
            <div class="rounded-lg bg-muted/30 p-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="font-medium">{{ $case->title }} <span class="text-xs text-muted-foreground">· {{ BenCase::CATEGORIES[$case->category] ?? $case->category }} · {{ $case->priority }} · opened {{ $case->opened_on->toInstitutionalDate() }}</span></div>
                    <span class="text-xs {{ $case->status === 'open' ? 'text-primary' : 'text-muted-foreground' }}">{{ ucfirst($case->status) }}{{ $case->outcome ? ': '.$case->outcome : '' }}</span>
                </div>
                <div class="mt-2 flex flex-col gap-2 text-sm">
                    @forelse($case->notes as $note)<div class="rounded bg-background p-2">{{ $note->note }} @if($note->is_confidential)<span class="text-xs text-destructive">(confidential)</span>@endif<div class="text-xs text-muted-foreground">{{ $note->created_at->toInstitutionalDateTime() }}</div></div>@empty<span class="text-xs text-muted-foreground">No notes you can see.</span>@endforelse
                </div>
                @can('beneficiaries.cases.manage')
                @if($case->status === 'open')
                <div class="mt-3 flex flex-col md:flex-row gap-2">
                    @unless($locked)
                    <form method="POST" action="{{ route('beneficiaries.beneficiaries.cases.notes', $case->uuid) }}" class="flex flex-1 gap-2">@csrf
                        <input class="kt-input" name="note" placeholder="Add a note" required>
                        <label class="flex items-center gap-1 text-xs whitespace-nowrap"><input type="checkbox" name="is_confidential" value="1"> Confidential</label>
                        <button class="kt-btn kt-btn-xs kt-btn-outline" type="submit">Add</button>
                    </form>
                    @endunless
                    <form method="POST" action="{{ route('beneficiaries.beneficiaries.cases.close', $case->uuid) }}" class="flex gap-2">@csrf
                        <input class="kt-input" name="outcome" placeholder="Outcome" required>
                        <button class="kt-btn kt-btn-xs kt-btn-outline" type="submit">Close case</button>
                    </form>
                </div>
                @endif
                @endcan
            </div>
        @empty
            <span class="text-sm text-muted-foreground">No cases.</span>
        @endforelse
        </div>
    </div>

    @can('beneficiaries.manage')
    @if($beneficiary->status === 'active')
    <form method="POST" action="{{ route('beneficiaries.beneficiaries.exit', $beneficiary->uuid) }}" class="kt-card">@csrf
        <div class="kt-card-header"><h3 class="kt-card-title">Leaving the programme</h3></div>
        <div class="kt-card-content flex gap-2"><input class="kt-input w-96" name="reason" placeholder="Reason (completed, moved away…)" required><button class="kt-btn kt-btn-outline" type="submit">Mark as exited</button></div>
    </form>
    @endif
    @endcan

    @can('beneficiaries.anonymise')
    <form method="POST" action="{{ route('beneficiaries.beneficiaries.anonymise', $beneficiary->uuid) }}" class="kt-card" onsubmit="return confirm('This permanently erases the name, phone, date of birth and notes. Continue?')">@csrf
        <div class="kt-card-header"><h3 class="kt-card-title text-destructive">Erase personal details</h3></div>
        <div class="kt-card-content flex flex-col md:flex-row gap-2 md:items-center">
            <p class="text-xs text-muted-foreground flex-1">Use when the person asks to be forgotten. Name, birth date, phone, notes and case notes are deleted for good; anonymous counts stay.</p>
            <input class="kt-input w-48" name="confirm" placeholder="Type ANONYMISE" required>
            <button class="kt-btn kt-btn-outline" type="submit">Erase</button>
        </div>
    </form>
    @endcan
    @endif
</div>
@endsection
