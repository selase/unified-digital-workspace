@php use App\Modules\Finance\Support\Money; @endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Thank you</title>
    <style>
        body { margin: 0; font-family: system-ui, sans-serif; background: #f4f5f7; color: #1f2937; text-align: center; }
        .wrap { max-width: 480px; margin: 0 auto; padding: 48px 16px; }
        h1 { font-size: 24px; }
        .muted { color: #6b7280; font-size: 14px; }
    </style>
</head>
<body>
<div class="wrap">
    @if($gift)
        <h1>Thank you!</h1>
        <p>We received your gift of <strong>{{ Money::format($gift->amount, $gift->currency) }}</strong> for {{ $profile->legal_name ?: $tenant->name }}.</p>
        <p class="muted">Your receipt number is {{ $gift->receipt_number }}.</p>
    @else
        <h1>We are confirming your gift</h1>
        <p class="muted">If you completed the payment, it will appear shortly. If you were charged and hear nothing, please contact us.</p>
    @endif
    <p><a href="{{ route('donations.public.give', ['tenant' => $tenant->slug]) }}">Back to the giving page</a></p>
</div>
</body>
</html>
