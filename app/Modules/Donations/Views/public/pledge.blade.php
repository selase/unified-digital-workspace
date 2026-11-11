<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Your pledge payment · {{ $profile->legal_name ?: $tenant->name }}</title>
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
    @php use App\Modules\Finance\Support\Money; @endphp
    <h1>Hello, {{ $pledge->donor->name }}</h1>
    <div class="muted">{{ $profile->legal_name ?: $tenant->name }}</div>
    @if(session('error'))<div class="bad">{{ session('error') }}</div>@endif
    @if($enabled)
    <form method="POST" action="{{ url()->full() }}" class="card">
        @csrf
        <div style="font-size:15px;">Your {{ strtolower(\App\Modules\Donations\Models\DonPledge::FREQUENCIES[$pledge->frequency]) }} pledge</div>
        <div style="font-size:28px;font-weight:700;margin:6px 0;">{{ Money::format($pledge->amount, $profile->base_currency) }}</div>
        @if($pledge->frequency !== 'once')
        <label style="display:flex;gap:10px;align-items:flex-start;font-weight:400;"><input type="checkbox" name="allow_auto_charge" value="1" style="width:auto;margin-top:3px;">
            <span>Charge my card automatically for my future instalments of {{ Money::format($pledge->amount, $profile->base_currency) }}. I can stop this at any time from the link in each receipt.</span></label>
        @endif
        <button type="submit" class="btn">Pay now</button>
    </form>
    @else
        <div class="bad">This pledge cannot be paid online right now. Please contact {{ $profile->legal_name ?: $tenant->name }}.</div>
    @endif
</div>
</body>
</html>
