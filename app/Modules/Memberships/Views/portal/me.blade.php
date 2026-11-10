@extends('memberships::portal.layout')

@section('title', 'My membership')

@section('content')
@php use App\Modules\Finance\Support\Money; use App\Modules\Memberships\Models\MemMember; @endphp
<div class="row" style="align-items:center;"><div><h1>Hello, {{ $member->name }}</h1><div class="muted">{{ $profile->legal_name ?: $tenant->name }} · {{ $member->number }}</div></div>
    <form method="POST" action="{{ route('memberships.portal.logout', ['tenant' => $tenant->slug]) }}" style="flex:0;">@csrf<button class="btn light" type="submit" style="margin:0;">Sign out</button></form></div>

@if(session('success'))<div class="ok">{{ session('success') }}</div>@endif
@if(session('error'))<div class="bad">{{ session('error') }}</div>@endif

<div class="stats">
    <div class="stat"><span class="muted">Membership</span><b>{{ $member->type->name }}</b></div>
    <div class="stat"><span class="muted">Status</span><b>{{ MemMember::STATUSES[$member->status] }}</b></div>
    <div class="stat"><span class="muted">Paid until</span><b>{{ $member->expires_on?->format('j M Y') ?? '—' }}</b></div>
</div>

@if($open)
<div class="card"><h2>Dues to pay</h2>
    <p>{{ Money::format($open->amount, $profile->base_currency) }} for {{ $open->period_start->format('j M Y') }} to {{ $open->period_end->format('j M Y') }}{{ $open->invoice ? ' (invoice '.$open->invoice->number.')' : '' }}.</p>
    @if($payUrl)<a class="btn" href="{{ $payUrl }}">Pay online</a>@else<p class="muted">Online payment is not available. Please contact {{ $profile->legal_name ?: $tenant->name }}{{ $profile->email ? ' at '.$profile->email : '' }} to pay.</p>@endif
</div>
@elseif($canRenew)
<form method="POST" action="{{ route('memberships.portal.renew', ['tenant' => $tenant->slug]) }}" class="card"><h2>Renew</h2>
    <p class="muted">Create your dues invoice for the next period ({{ Money::format($member->type->fee, $profile->base_currency) }}).</p>@csrf<button class="btn" type="submit">Renew my membership</button></form>
@endif

<div class="card"><h2>Dues history</h2>
    <table>
    @forelse($renewals as $renewal)
        <tr><td>{{ $renewal->period_start->format('j M Y') }} to {{ $renewal->period_end->format('j M Y') }}</td><td style="text-align:right;">{{ Money::format($renewal->amount, $profile->base_currency) }} · {{ ucfirst($renewal->status) }}</td></tr>
    @empty
        <tr><td class="muted">No dues yet.</td></tr>
    @endforelse
    </table>
</div>
@endsection
