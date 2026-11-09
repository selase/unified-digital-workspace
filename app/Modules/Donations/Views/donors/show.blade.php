@extends('layouts.metronic.app')

@section('title', $donor->name)

@section('content')
@php use App\Modules\Finance\Support\Money; @endphp
<div class="flex flex-col gap-6">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-foreground">{{ $donor->name }}</h1>
            <p class="text-xs text-muted-foreground">{{ ucfirst($donor->type) }} · {{ $donor->email ?: 'no email' }} · {{ $donor->phone }}</p>
        </div>
        @can('donations.record')
            <a href="{{ route('donations.donations.index', ['donor' => $donor->uuid]) }}" class="kt-btn kt-btn-primary">Record a donation</a>
        @endcan
    </div>

    @include('donations::partials.nav')

    <div class="grid grid-cols-2 lg:grid-cols-3 gap-4">
        <div class="rounded-lg bg-muted/30 p-4"><div class="text-xs text-muted-foreground">Total given</div><div class="mt-1 text-lg font-semibold">{{ Money::format($total, $currency) }}</div></div>
        <div class="rounded-lg bg-muted/30 p-4"><div class="text-xs text-muted-foreground">Gifts</div><div class="mt-1 text-lg font-semibold">{{ $gifts->whereNull('voided_at')->count() }}</div></div>
        <div class="rounded-lg bg-muted/30 p-4"><div class="text-xs text-muted-foreground">Last gift</div><div class="mt-1 text-lg font-semibold">{{ $gifts->whereNull('voided_at')->first()?->date->toInstitutionalDate() ?? '—' }}</div></div>
    </div>

    <div class="kt-card">
        <div class="kt-card-header"><h3 class="kt-card-title">Giving history</h3></div>
        <div class="kt-card-table kt-scrollable-x-auto">
            <table class="kt-table table-auto kt-table-border">
                <thead><tr><th>Receipt</th><th>Date</th><th>Campaign</th><th>Fund</th><th class="text-end">Amount</th><th></th></tr></thead>
                <tbody>
                @forelse($gifts as $gift)
                    <tr class="{{ $gift->voided_at ? 'text-muted-foreground line-through' : '' }}">
                        <td>{{ $gift->receipt_number }}</td>
                        <td>{{ $gift->date->toInstitutionalDate() }}</td>
                        <td>{{ $gift->campaign?->name }}</td>
                        <td>{{ $gift->fund?->name }}</td>
                        <td class="text-end">{{ Money::format($gift->amount, $currency) }}</td>
                        <td class="text-end"><a class="kt-btn kt-btn-xs kt-btn-outline" target="_blank" href="{{ route('donations.donations.receipt', $gift->uuid) }}">Receipt</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted-foreground py-6">No gifts yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @can('donations.donors.manage')
    <form method="POST" action="{{ route('donations.donors.update', $donor->uuid) }}" class="kt-card">
        @csrf @method('PUT')
        <div class="kt-card-header"><h3 class="kt-card-title">Edit details</h3></div>
        <div class="kt-card-content grid grid-cols-1 md:grid-cols-3 gap-3">
            <input class="kt-input" name="name" value="{{ old('name', $donor->name) }}" required>
            <select class="kt-select" name="type"><option value="individual" @selected($donor->type === 'individual')>Individual</option><option value="organisation" @selected($donor->type === 'organisation')>Organisation</option></select>
            <input class="kt-input" type="email" name="email" value="{{ old('email', $donor->email) }}" placeholder="Email">
            <input class="kt-input" name="phone" value="{{ old('phone', $donor->phone) }}" placeholder="Phone">
            <input class="kt-input md:col-span-2" name="address" value="{{ old('address', $donor->address) }}" placeholder="Address">
            <textarea class="kt-input md:col-span-3" name="notes" rows="2" placeholder="Notes">{{ old('notes', $donor->notes) }}</textarea>
            <div><button class="kt-btn kt-btn-primary" type="submit">Save</button></div>
        </div>
    </form>
    @endcan
</div>
@endsection
