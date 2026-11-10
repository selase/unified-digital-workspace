@extends('layouts.metronic.app')

@section('title', $member->name)

@section('content')
@php use App\Modules\Memberships\Models\MemMember; use App\Modules\Finance\Support\Money; @endphp
<div class="flex flex-col gap-6">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div><h1 class="text-xl font-bold text-foreground">{{ $member->name }}</h1><p class="text-xs text-muted-foreground">{{ $member->number }} · {{ MemMember::STATUSES[$member->status] }} · {{ $member->email ?: 'no email' }} · {{ $member->phone }}</p></div>
        @can('memberships.manage')
        <div class="flex flex-wrap gap-2">
            @if($member->status !== 'cancelled' && $member->type->fee > 0 && ! $renewals->contains('status', 'billed'))<form method="POST" action="{{ route('memberships.members.bill', $member->uuid) }}">@csrf<button class="kt-btn kt-btn-primary" type="submit">{{ $member->status === 'active' ? 'Invoice next renewal' : 'Invoice dues' }}</button></form>@endif
            @if($member->status !== 'cancelled' && ! $renewals->contains('status', 'billed'))<form method="POST" action="{{ route('memberships.members.complimentary', $member->uuid) }}">@csrf<button class="kt-btn kt-btn-outline" type="submit">Activate without invoice</button></form>@endif
            @if($member->status === 'cancelled')<form method="POST" action="{{ route('memberships.members.reinstate', $member->uuid) }}">@csrf<button class="kt-btn kt-btn-outline" type="submit">Reinstate</button></form>
            @else<form method="POST" action="{{ route('memberships.members.cancel', $member->uuid) }}" onsubmit="return confirm('Cancel this membership? An unpaid dues invoice will be voided.')">@csrf<button class="kt-btn kt-btn-outline" type="submit">Cancel membership</button></form>@endif
        </div>
        @endcan
    </div>

    @include('memberships::partials.nav')

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="rounded-lg bg-muted/30 p-4"><div class="text-xs text-muted-foreground">Type</div><div class="mt-1 text-lg font-semibold">{{ $member->type->name }}</div></div>
        <div class="rounded-lg bg-muted/30 p-4"><div class="text-xs text-muted-foreground">Member since</div><div class="mt-1 text-lg font-semibold">{{ $member->joined_on?->toInstitutionalDate() ?? '—' }}</div></div>
        <div class="rounded-lg bg-muted/30 p-4"><div class="text-xs text-muted-foreground">Paid until</div><div class="mt-1 text-lg font-semibold">{{ $member->expires_on?->toInstitutionalDate() ?? '—' }}</div></div>
        <div class="rounded-lg bg-muted/30 p-4"><div class="text-xs text-muted-foreground">Fee</div><div class="mt-1 text-lg font-semibold">{{ Money::format($member->type->fee) }} / {{ $member->type->period_months }} mo</div></div>
    </div>

    @if($payUrl)
    <div class="rounded-lg bg-muted/30 p-4 text-sm flex flex-col md:flex-row md:items-center gap-3" x-data="{ copied: false }">
        <div class="flex-1"><div class="font-medium text-foreground">Payment link for the open dues invoice</div><div class="text-xs text-muted-foreground">Send this to the member. It stays valid for 60 days.</div></div>
        <input class="kt-input md:w-96" readonly id="pay-link" value="{{ $payUrl }}" onclick="this.select()">
        <button type="button" class="kt-btn kt-btn-outline" @click="navigator.clipboard.writeText(document.getElementById('pay-link').value); copied = true"><span x-text="copied ? 'Copied' : 'Copy link'"></span></button>
    </div>
    @endif

    @can('memberships.manage')
    <form method="POST" action="{{ route('memberships.members.update', $member->uuid) }}" class="kt-card">@csrf @method('PUT')
        <div class="kt-card-header"><h3 class="kt-card-title">Details</h3></div>
        <div class="kt-card-content grid grid-cols-1 md:grid-cols-4 gap-3">
            <input class="kt-input" name="name" value="{{ old('name', $member->name) }}" required>
            <input class="kt-input" type="email" name="email" value="{{ old('email', $member->email) }}" placeholder="Email">
            <input class="kt-input" name="phone" value="{{ old('phone', $member->phone) }}" placeholder="Phone">
            <select class="kt-select" name="type_id" required>@foreach($types as $type)<option value="{{ $type->id }}" @selected($member->type_id === $type->id)>{{ $type->name }}</option>@endforeach</select>
            <input class="kt-input md:col-span-3" name="notes" value="{{ old('notes', $member->notes) }}" placeholder="Notes">
            <div><button class="kt-btn kt-btn-outline" type="submit">Save</button></div>
        </div>
    </form>
    @endcan

    <div class="kt-card">
        <div class="kt-card-header"><h3 class="kt-card-title">Dues history</h3></div>
        <div class="kt-card-table kt-scrollable-x-auto"><table class="kt-table table-auto kt-table-border">
            <thead><tr><th>Period</th><th>Invoice</th><th>Status</th><th class="text-end">Amount</th></tr></thead>
            <tbody>
            @forelse($renewals as $renewal)
                <tr><td>{{ $renewal->period_start->toInstitutionalDate() }} to {{ $renewal->period_end->toInstitutionalDate() }}</td><td>@if($renewal->invoice)<a class="text-primary" href="{{ route('finance.invoices.show', $renewal->invoice->uuid) }}">{{ $renewal->invoice->number }}</a>@endif</td><td>{{ ucfirst($renewal->status) }}</td><td class="text-end">{{ Money::format($renewal->amount) }}</td></tr>
            @empty
                <tr><td colspan="4" class="text-center text-muted-foreground py-6">No dues invoiced yet.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>
</div>
@endsection
