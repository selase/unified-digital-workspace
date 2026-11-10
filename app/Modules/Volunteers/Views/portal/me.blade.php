@extends('volunteers::portal.layout')

@section('title', 'My volunteering')

@section('content')
<div class="row" style="align-items:center;"><div><h1>Hello, {{ $volunteer->name }}</h1><div class="muted">{{ $profile->legal_name ?: $tenant->name }}</div></div>
    <form method="POST" action="{{ route('volunteers.portal.logout', ['tenant' => $tenant->slug]) }}" style="flex:0;">@csrf<button class="btn light" type="submit" style="margin:0;">Sign out</button></form></div>

@if(session('success'))<div class="ok">{{ session('success') }}</div>@endif
@if(session('error'))<div class="bad">{{ session('error') }}</div>@endif
@if($errors->any())<div class="bad">@foreach($errors->all() as $message)<div>{{ $message }}</div>@endforeach</div>@endif

<div class="stats">
    <div class="stat"><span class="muted">Approved hours</span><b>{{ number_format($approvedMinutes / 60, 1) }}</b></div>
    <div class="stat"><span class="muted">Waiting for approval</span><b>{{ number_format($pendingMinutes / 60, 1) }}</b></div>
</div>
@if($approvedMinutes > 0)<p><a class="btn light" href="{{ route('volunteers.portal.certificate', ['tenant' => $tenant->slug]) }}">Download my certificate</a></p>@endif

<form method="POST" action="{{ route('volunteers.portal.hours', ['tenant' => $tenant->slug]) }}" class="card">@csrf
    <h2>Log hours</h2>
    <div class="row"><label>Date<input type="date" name="worked_on" value="{{ old('worked_on', today()->toDateString()) }}" max="{{ today()->toDateString() }}" required></label><label>Hours<input type="number" name="hours" step="0.25" min="0.25" max="24" value="{{ old('hours') }}" required></label></div>
    <label>What did you do?<input name="activity" value="{{ old('activity') }}" maxlength="255"></label>
    <label>For an opportunity (optional)<select name="opportunity_id"><option value="">None</option>@foreach($opportunities as $op)<option value="{{ $op->id }}">{{ $op->title }}</option>@endforeach</select></label>
    <button class="btn" type="submit">Submit hours</button>
    <div class="muted" style="margin-top:8px;">Staff approve your hours before they count.</div>
</form>

<div class="card"><h2>Coming up</h2>
    <table>
    @forelse($opportunities as $op)
        <tr><td>{{ $op->starts_on->format('j M Y') }}</td><td>{{ $op->title }}<div class="muted">{{ $op->location }}{{ $op->spots > 0 ? ' · '.$op->volunteers_count.' of '.$op->spots.' places taken' : '' }}</div></td>
            <td style="text-align:right;">@if(in_array($op->id, $signedUpIds, true))<span class="muted">Signed up</span>@else<form method="POST" class="inline" action="{{ route('volunteers.portal.signup', ['tenant' => $tenant->slug, 'opportunity' => $op->id]) }}">@csrf<button class="btn light" type="submit" style="margin:0;">Sign up</button></form>@endif</td></tr>
    @empty
        <tr><td class="muted">Nothing is scheduled right now.</td></tr>
    @endforelse
    </table>
</div>

<div class="card"><h2>My recent hours</h2>
    <table>
    @forelse($hours as $hour)
        <tr><td>{{ $hour->worked_on->format('j M Y') }}</td><td>{{ $hour->activity ?: 'Volunteering' }}{{ $hour->opportunity ? ' ('.$hour->opportunity->title.')' : '' }}</td><td style="text-align:right;">{{ number_format($hour->hoursDecimal(), 2) }} h · {{ ucfirst($hour->status) }}</td></tr>
    @empty
        <tr><td class="muted">No hours yet.</td></tr>
    @endforelse
    </table>
</div>
@endsection
