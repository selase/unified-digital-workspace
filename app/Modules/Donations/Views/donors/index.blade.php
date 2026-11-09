@extends('layouts.metronic.app')

@section('title', 'Donors')

@section('content')
@php use App\Modules\Finance\Support\Money; @endphp
<div class="flex flex-col gap-6">
    <div>
        <h1 class="text-xl font-bold text-foreground">Donors</h1>
        <p class="text-xs text-muted-foreground">The people and organisations who give.</p>
    </div>

    @include('donations::partials.nav')

    @can('donations.donors.manage')
    <div class="kt-card">
        <div class="kt-card-header"><h3 class="kt-card-title">Add a donor</h3></div>
        <form method="POST" action="{{ route('donations.donors.store') }}" class="kt-card-content grid grid-cols-1 md:grid-cols-3 gap-3">
            @csrf
            <input class="kt-input" name="name" value="{{ old('name') }}" placeholder="Name" required>
            <select class="kt-select" name="type"><option value="individual">Individual</option><option value="organisation" @selected(old('type') === 'organisation')>Organisation</option></select>
            <input class="kt-input" type="email" name="email" value="{{ old('email') }}" placeholder="Email (for receipts)">
            <input class="kt-input" name="phone" value="{{ old('phone') }}" placeholder="Phone">
            <input class="kt-input md:col-span-2" name="address" value="{{ old('address') }}" placeholder="Address">
            <div><button class="kt-btn kt-btn-primary" type="submit">Save donor</button></div>
        </form>
    </div>
    @endcan

    <div class="kt-card">
        <div class="kt-card-header">
            <form method="GET" class="flex gap-2"><input class="kt-input" name="search" value="{{ $search }}" placeholder="Search name or email"><button class="kt-btn kt-btn-outline" type="submit">Search</button></form>
        </div>
        <div class="kt-card-table kt-scrollable-x-auto">
            <table class="kt-table table-auto kt-table-border">
                <thead><tr><th>Name</th><th>Type</th><th>Email</th><th class="text-end">Gifts</th><th class="text-end">Total given</th></tr></thead>
                <tbody>
                @forelse($donors as $donor)
                    <tr>
                        <td><a class="text-primary" href="{{ route('donations.donors.show', $donor->uuid) }}">{{ $donor->name }}</a></td>
                        <td>{{ ucfirst($donor->type) }}</td>
                        <td>{{ $donor->email }}</td>
                        <td class="text-end">{{ $donor->gifts }}</td>
                        <td class="text-end">{{ Money::format((int) $donor->given, $currency) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted-foreground py-6">No donors yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    {{ $donors->links() }}
</div>
@endsection
