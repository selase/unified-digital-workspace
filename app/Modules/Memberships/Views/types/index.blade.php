@extends('layouts.metronic.app')

@section('title', 'Membership types')

@section('content')
@php use App\Modules\Finance\Support\Money; @endphp
<div class="flex flex-col gap-6">
    <div><h1 class="text-xl font-bold text-foreground">Membership types</h1><p class="text-xs text-muted-foreground">Each type has a fee and a period. A fee of 0 makes a free or honorary type. Changing a fee only affects invoices raised afterwards.</p></div>

    @include('memberships::partials.nav')

    @can('memberships.manage')
    <form method="POST" action="{{ route('memberships.types.store') }}" class="kt-card">@csrf
        <div class="kt-card-header"><h3 class="kt-card-title">New membership type</h3></div>
        <div class="kt-card-content grid grid-cols-1 md:grid-cols-4 gap-3">
            <input class="kt-input md:col-span-2" name="name" value="{{ old('name') }}" placeholder="Name, e.g. Individual" required>
            <input class="kt-input" type="number" step="0.01" min="0" name="fee" value="{{ old('fee') }}" placeholder="Fee ({{ $currency }})" required>
            <input class="kt-input" type="number" min="1" name="period_months" value="{{ old('period_months', 12) }}" placeholder="Months per payment" required>
            <input class="kt-input md:col-span-2" name="description" value="{{ old('description') }}" placeholder="What members get">
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_public" value="1" checked> On the public join page</label>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="auto_invoice" value="1" checked> Invoice renewals automatically</label>
            <div><button class="kt-btn kt-btn-primary" type="submit">Create</button></div>
        </div>
    </form>
    @endcan

    <div class="flex flex-col gap-3">
    @forelse($types as $type)
        <form method="POST" action="{{ route('memberships.types.update', $type->uuid) }}" class="rounded-lg bg-muted/30 p-4 grid grid-cols-1 md:grid-cols-6 gap-3 items-center">@csrf @method('PUT')
            <input class="kt-input md:col-span-2" name="name" value="{{ $type->name }}" required>
            <input class="kt-input" type="number" step="0.01" min="0" name="fee" value="{{ Money::input($type->fee) }}" required>
            <input class="kt-input" type="number" min="1" name="period_months" value="{{ $type->period_months }}" required>
            <input class="kt-input md:col-span-2" name="description" value="{{ $type->description }}" placeholder="Description">
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_public" value="1" @checked($type->is_public)> Public</label>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="auto_invoice" value="1" @checked($type->auto_invoice)> Auto-renew invoice</label>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked($type->is_active)> Active</label>
            <div class="text-xs text-muted-foreground">{{ $type->active_count }} active</div>
            @can('memberships.manage')<div><button class="kt-btn kt-btn-outline kt-btn-sm" type="submit">Save</button></div>@endcan
        </form>
    @empty
        <div class="rounded-lg bg-muted/30 p-6 text-center text-muted-foreground">No membership types yet.</div>
    @endforelse
    </div>
</div>
@endsection
