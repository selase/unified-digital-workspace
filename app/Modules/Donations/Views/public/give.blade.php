<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Give to {{ $profile->legal_name ?: $tenant->name }}</title>
    <style>
        body { margin: 0; font-family: system-ui, sans-serif; background: #f4f5f7; color: #1f2937; }
        .wrap { max-width: 480px; margin: 0 auto; padding: 32px 16px; }
        .card { background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,.08); margin-top: 20px; }
        h1 { font-size: 22px; margin: 0 0 6px; }
        .muted { color: #6b7280; font-size: 13px; }
        label { display: block; font-size: 14px; font-weight: 600; margin-top: 16px; }
        input, select { width: 100%; box-sizing: border-box; margin-top: 6px; padding: 10px 12px; border: 0; border-radius: 8px; background: #f3f4f6; font-size: 15px; }
        .btn { width: 100%; margin-top: 22px; padding: 13px 20px; border: 0; border-radius: 8px; background: #2563eb; color: #fff; font-size: 16px; font-weight: 600; cursor: pointer; }
        .bad { background: #fef2f2; color: #b91c1c; padding: 10px 14px; border-radius: 8px; margin-top: 16px; font-size: 14px; }
        .hp { position: absolute; left: -9999px; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Give to {{ $profile->legal_name ?: $tenant->name }}</h1>
    <div class="muted">Your gift goes straight to our work. You will get a receipt by email.</div>

    @if(session('error'))<div class="bad">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="bad">@foreach($errors->all() as $message)<div>{{ $message }}</div>@endforeach</div>@endif

    @if($enabled)
    <form method="POST" action="{{ route('donations.public.start', ['tenant' => $tenant->slug]) }}" class="card">
        @csrf
        <input class="hp" type="text" name="website" value="" tabindex="-1" autocomplete="off" aria-hidden="true">
        <label>Amount ({{ $profile->base_currency }})
            <input name="amount" type="number" step="0.01" min="1" value="{{ old('amount') }}" required placeholder="50.00">
        </label>
        @if($campaigns->isNotEmpty())
        <label>What would you like to support?
            <select name="campaign_id">
                <option value="">Wherever it is needed most</option>
                @foreach($campaigns as $campaign)<option value="{{ $campaign->id }}" @selected((int) old('campaign_id', $selectedCampaign) === $campaign->id)>{{ $campaign->name }}</option>@endforeach
            </select>
        </label>
        @endif
        <label>Your name<input name="name" value="{{ old('name') }}" required></label>
        <label>Email for your receipt<input name="email" type="email" value="{{ old('email') }}" required></label>
        <button type="submit" class="btn">Give securely</button>
        <div class="muted" style="text-align:center;margin-top:12px">Payment is handled by Paystack. We never see your card details.</div>
    </form>
    @else
    <div class="card muted" style="font-size:14px">
        Online giving is not switched on yet.
        @if($profile->email) Please write to <a href="mailto:{{ $profile->email }}">{{ $profile->email }}</a> to give another way.@endif
    </div>
    @endif
</div>
</body>
</html>
