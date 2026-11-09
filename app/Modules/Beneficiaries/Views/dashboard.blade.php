@extends('layouts.metronic.app')

@section('title', 'Beneficiaries')

@section('content')
<div class="flex flex-col gap-6">
    <div><h1 class="text-xl font-bold text-foreground">Beneficiaries</h1><p class="text-xs text-muted-foreground">The people you serve. These figures contain no personal details.</p></div>

    @include('beneficiaries::partials.nav')

    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
        <div class="rounded-lg bg-muted/30 p-4"><div class="text-xs text-muted-foreground">Registered</div><div class="mt-1 text-lg font-semibold">{{ $summary['total'] }}</div></div>
        <div class="rounded-lg bg-muted/30 p-4"><div class="text-xs text-muted-foreground">In the programme</div><div class="mt-1 text-lg font-semibold">{{ $summary['active'] }}</div></div>
        <div class="rounded-lg bg-muted/30 p-4"><div class="text-xs text-muted-foreground">Reached this year</div><div class="mt-1 text-lg font-semibold">{{ $summary['reached_this_year'] }}</div></div>
        <div class="rounded-lg bg-muted/30 p-4"><div class="text-xs text-muted-foreground">Open cases</div><div class="mt-1 text-lg font-semibold">{{ $openCases }}@if($highPriority) <span class="text-xs text-destructive">({{ $highPriority }} high)</span>@endif</div></div>
        <div class="rounded-lg bg-muted/30 p-4"><div class="text-xs text-muted-foreground">Without valid consent</div><div class="mt-1 text-lg font-semibold {{ $noConsent ? 'text-destructive' : '' }}">{{ $noConsent }}</div></div>
    </div>

    @unless($mayViewPersonal)
        <div class="rounded-lg bg-muted/30 p-3 text-xs text-muted-foreground">Your role shows beneficiaries by reference and initials only. Names, phone numbers and confidential notes are limited to staff with that permission.</div>
    @endunless

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        @foreach(['by_programme' => 'By programme', 'by_gender' => 'By gender', 'by_region' => 'By region'] as $key => $label)
        <div class="kt-card">
            <div class="kt-card-header"><h3 class="kt-card-title">{{ $label }}</h3></div>
            <div class="kt-card-content flex flex-col gap-2 text-sm">
                @forelse($summary[$key] as $name => $count)<div class="flex justify-between"><span>{{ ucfirst($name) }}</span><span class="font-medium">{{ $count }}</span></div>@empty<span class="text-muted-foreground">No data yet.</span>@endforelse
            </div>
        </div>
        @endforeach
    </div>

    <div class="kt-card">
        <div class="kt-card-header"><h3 class="kt-card-title">Services delivered this year</h3></div>
        <div class="kt-card-content grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
            @forelse($summary['services_by_type'] as $type => $count)<div class="rounded-lg bg-muted/30 p-3"><div class="text-xs text-muted-foreground">{{ \App\Modules\Beneficiaries\Models\BenBeneficiary::SERVICE_TYPES[$type] ?? $type }}</div><div class="mt-1 font-semibold">{{ $count }}</div></div>@empty<span class="text-muted-foreground">No services recorded yet.</span>@endforelse
        </div>
    </div>
</div>
@endsection
