@extends('memberships::portal.layout')

@section('title', 'Member sign-in')

@section('content')
<h1>Member sign-in</h1>
<div class="muted">{{ $profile->legal_name ?: $tenant->name }}. Enter your email and we will send you a sign-in link. No password needed.</div>
@isset($problem)<div class="bad">{{ $problem }}</div>@endisset
@if($errors->any())<div class="bad">@foreach($errors->all() as $message)<div>{{ $message }}</div>@endforeach</div>@endif
@if($sent)
    <div class="ok">If that address belongs to a member, a sign-in link is on its way. It expires in 30 minutes.</div>
@else
    <form method="POST" action="{{ route('memberships.portal.send', ['tenant' => $tenant->slug]) }}" class="card">
        @csrf
        <input class="hp" type="text" name="website" value="" tabindex="-1" autocomplete="off" aria-hidden="true">
        <label>Email<input name="email" type="email" value="{{ old('email') }}" required></label>
        <button type="submit" class="btn">Email me a link</button>
    </form>
    <p class="muted" style="margin-top:16px;">Not a member yet? <a href="{{ route('memberships.public.join', ['tenant' => $tenant->slug]) }}">Join here</a>.</p>
@endif
@endsection
