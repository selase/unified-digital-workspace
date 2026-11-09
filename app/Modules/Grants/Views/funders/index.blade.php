@extends('layouts.metronic.app')

@section('title', 'Funders')

@section('content')
@php use App\Modules\Finance\Support\Money; use App\Modules\Grants\Models\GrtFunder; @endphp
<div class="flex flex-col gap-6">
    <div><h1 class="text-xl font-bold text-foreground">Funders</h1><p class="text-xs text-muted-foreground">The organisations that fund your work.</p></div>

    @include('grants::partials.nav')

    @can('grants.manage')
    <form method="POST" action="{{ route('grants.funders.store') }}" class="kt-card">
        @csrf
        <div class="kt-card-header"><h3 class="kt-card-title">Add a funder</h3></div>
        <div class="kt-card-content grid grid-cols-1 md:grid-cols-3 gap-3">
            <input class="kt-input" name="name" value="{{ old('name') }}" placeholder="Name" required>
            <select class="kt-select" name="type">@foreach(GrtFunder::TYPES as $key => $label)<option value="{{ $key }}" @selected(old('type') === $key)>{{ $label }}</option>@endforeach</select>
            <input class="kt-input" name="contact_name" value="{{ old('contact_name') }}" placeholder="Programme officer">
            <input class="kt-input" type="email" name="email" value="{{ old('email') }}" placeholder="Email">
            <input class="kt-input" name="phone" value="{{ old('phone') }}" placeholder="Phone">
            <div><button class="kt-btn kt-btn-primary" type="submit">Save funder</button></div>
        </div>
    </form>
    @endcan

    <div class="kt-card"><div class="kt-card-table kt-scrollable-x-auto"><table class="kt-table table-auto kt-table-border">
        <thead><tr><th>Funder</th><th>Type</th><th>Contact</th><th class="text-end">Grants</th><th class="text-end">Awarded</th></tr></thead>
        <tbody>
        @forelse($funders as $funder)
            <tr><td>{{ $funder->name }}</td><td>{{ GrtFunder::TYPES[$funder->type] ?? $funder->type }}</td><td>{{ $funder->contact_name }}<div class="text-xs text-muted-foreground">{{ $funder->email }}</div></td><td class="text-end">{{ $funder->grants_count }}</td><td class="text-end">{{ Money::format((int) $funder->awarded, $currency) }}</td></tr>
        @empty
            <tr><td colspan="5" class="text-center text-muted-foreground py-6">No funders yet.</td></tr>
        @endforelse
        </tbody>
    </table></div></div>
</div>
@endsection
