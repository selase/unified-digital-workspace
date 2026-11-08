@extends('layouts.metronic.app')

@section('title', 'Customers')

@section('content')
<div class="flex flex-col gap-6">
    <div>
        <h1 class="text-xl font-bold text-foreground">Customers</h1>
        <p class="text-xs text-muted-foreground">The people and organisations you invoice.</p>
    </div>

    @include('finance::partials.nav')

    @can('finance.contacts.manage')
    <div class="kt-card">
        <div class="kt-card-header"><h3 class="kt-card-title">Add a customer</h3></div>
        <form method="POST" action="{{ route('finance.contacts.store') }}" class="kt-card-content grid grid-cols-1 md:grid-cols-3 gap-3">
            @csrf
            <input class="kt-input" name="name" value="{{ old('name') }}" placeholder="Name" required>
            <input class="kt-input" name="company" value="{{ old('company') }}" placeholder="Company">
            <input class="kt-input" type="email" name="email" value="{{ old('email') }}" placeholder="Email (needed for payment links)">
            <input class="kt-input" name="phone" value="{{ old('phone') }}" placeholder="Phone">
            <input class="kt-input" name="tax_number" value="{{ old('tax_number') }}" placeholder="TIN / tax number">
            <input class="kt-input" type="number" min="0" max="365" name="payment_terms_days" value="{{ old('payment_terms_days') }}" placeholder="Payment terms (days)">
            <textarea class="kt-input md:col-span-3" name="address" rows="2" placeholder="Address">{{ old('address') }}</textarea>
            <div><button class="kt-btn kt-btn-primary" type="submit">Save customer</button></div>
        </form>
    </div>
    @endcan

    <div class="kt-card">
        <div class="kt-card-header">
            <form method="GET" class="flex gap-2">
                <input class="kt-input" name="search" value="{{ $search }}" placeholder="Search name or email">
                <button class="kt-btn kt-btn-outline" type="submit">Search</button>
            </form>
        </div>
        <div class="kt-card-table kt-scrollable-x-auto">
            <table class="kt-table table-auto kt-table-border">
                <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>TIN</th><th></th></tr></thead>
                <tbody>
                @forelse($contacts as $contact)
                    <tr>
                        <td>{{ $contact->name }} @unless($contact->is_active)<span class="text-xs text-muted-foreground">(off)</span>@endunless<div class="text-xs text-muted-foreground">{{ $contact->company }}</div></td>
                        <td>{{ $contact->email }}</td>
                        <td>{{ $contact->phone }}</td>
                        <td>{{ $contact->tax_number }}</td>
                        <td class="text-end">
                            @can('finance.contacts.manage')
                            <form method="POST" action="{{ route('finance.contacts.destroy', $contact->uuid) }}" onsubmit="return confirm('Delete this customer?')">
                                @csrf @method('DELETE')
                                <button class="kt-btn kt-btn-xs kt-btn-outline" type="submit">Delete</button>
                            </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted-foreground py-6">No customers yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    {{ $contacts->links() }}
</div>
@endsection
