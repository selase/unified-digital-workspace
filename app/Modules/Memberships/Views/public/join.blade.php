<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Join {{ $profile->legal_name ?: $tenant->name }}</title>
    <style>
        body { margin: 0; font-family: system-ui, sans-serif; background: #f4f5f7; color: #1f2937; }
        .wrap { max-width: 480px; margin: 0 auto; padding: 32px 16px; }
        .card { background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,.08); margin-top: 20px; }
        h1 { font-size: 22px; margin: 0 0 6px; }
        .muted { color: #6b7280; font-size: 13px; }
        label { display: block; font-size: 14px; font-weight: 600; margin-top: 16px; }
        input, select, textarea { width: 100%; box-sizing: border-box; margin-top: 6px; padding: 10px 12px; border: 0; border-radius: 8px; background: #f3f4f6; font-size: 15px; font-family: inherit; }
        .btn { width: 100%; margin-top: 22px; padding: 13px 20px; border: 0; border-radius: 8px; background: #2563eb; color: #fff; font-size: 16px; font-weight: 600; cursor: pointer; }
        .bad { background: #fef2f2; color: #b91c1c; padding: 10px 14px; border-radius: 8px; margin-top: 16px; font-size: 14px; }
        .ok { background: #ecfdf5; color: #047857; padding: 14px; border-radius: 8px; margin-top: 20px; }
        .hp { position: absolute; left: -9999px; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Join {{ $profile->legal_name ?: $tenant->name }}</h1>
    <div class="muted">Choose a membership and tell us about yourself.</div>
    @if($done)
        <div class="ok">Thank you! We have your application and will be in touch about your membership.</div>
    @else
        @if($errors->any())<div class="bad">@foreach($errors->all() as $message)<div>{{ $message }}</div>@endforeach</div>@endif
        <form method="POST" action="{{ route('memberships.public.store', ['tenant' => $tenant->slug]) }}" class="card">
            @csrf
            <input class="hp" type="text" name="website" value="" tabindex="-1" autocomplete="off" aria-hidden="true">
            <label>Membership<select name="type_id" required><option value="">Choose…</option>@foreach($types as $type)<option value="{{ $type->id }}" @selected((int) old('type_id') === $type->id)>{{ $type->name }} — {{ $type->fee > 0 ? \App\Modules\Finance\Support\Money::format($type->fee, $profile->base_currency) . ' every ' . ($type->period_months === 12 ? 'year' : $type->period_months . ' months') : 'free' }}</option>@endforeach</select></label>
            <label>Your name<input name="name" value="{{ old('name') }}" required></label>
            <label>Email<input name="email" type="email" value="{{ old('email') }}" required></label>
            <label>Phone (optional)<input name="phone" value="{{ old('phone') }}"></label>
            <button type="submit" class="btn">Apply for membership</button>
        </form>
    @endif
    <p class="muted" style="margin-top:18px;">Already a member? <a href="{{ route('memberships.portal.login', ['tenant' => $tenant->slug]) }}">Sign in to see your membership</a>.</p>
</div>
</body>
</html>
