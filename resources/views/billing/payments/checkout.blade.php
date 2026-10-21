<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>Payment status · {{ $invoice->number }}</title>
    <style>
        body { font: 1rem/1.6 system-ui, sans-serif; color: #182639; background: #f3f5f7; margin: 0; padding: 20px; }
        main { max-width: 640px; margin: 32px auto; padding: 24px; background: white; border: 1px solid #d9dfe5; border-radius: 12px; }
        h1 { font-size: 1.7rem; line-height: 1.3; } p, a { overflow-wrap: anywhere; }
        a { color: #173f68; } .button, button { display: inline-block; box-sizing: border-box; padding: 12px 18px; border-radius: 6px; background: #173f68; color: white; text-decoration: none; font: inherit; border: 0; cursor: pointer; }
        button { background: #eef3f8; color: #173f68; } button:disabled { opacity: .65; cursor: wait; }
        a:focus-visible, button:focus-visible { outline: 3px solid #b56612; outline-offset: 3px; }
        .notice { padding: 16px; background: #eef3f8; border-radius: 8px; } footer { margin-top: 24px; font-size: .9rem; }
    </style>
</head>
<body>
<main>
    <p>{{ config('app.name') }} · Billing</p>
    <h1>{{ $invoice->status === \App\Models\Invoice::STATUS_PAID ? 'Confirming your payment' : 'Complete your payment' }}</h1>
    <p>Invoice <strong>{{ $invoice->number }}</strong> · <strong>{{ $invoice->currency }} {{ number_format((float) $invoice->total, 2) }}</strong></p>
    @if($checkoutUrl)
        <p><a id="open-checkout" class="button" href="{{ $checkoutUrl }}" target="_blank" rel="noopener noreferrer">Open secure Paystack checkout</a></p>
        <p>Keep this page open. Paystack opens in a separate window. Once we confirm payment, this page will take you to your receipt.</p>
        <p>If Paystack stays on “Checking transaction status”, return here. We check your payment independently. Do not pay again if you have been debited.</p>
    @else
        <p>We are checking your existing payment. You do not need to pay again.</p>
    @endif
    <p id="payment-status" class="notice" role="status" aria-live="polite">{{ session('checkout_status', 'Waiting for payment confirmation. We will check automatically.') }}</p>
    <form id="check-payment" method="POST" action="{{ $checkUrl }}">
        @csrf
        <button id="check-button" type="submit">Check payment</button>
    </form>
    <noscript><p>Automatic checks need JavaScript. After paying, use “Check payment” to open your receipt.</p></noscript>
    <footer><a href="{{ $invoiceUrl }}">Return to invoice</a> · Keep this private link secure.</footer>
</main>
<script>
(() => {
    const form = document.getElementById('check-payment');
    const button = document.getElementById('check-button');
    const notice = document.getElementById('payment-status');
    const checkoutLink = document.getElementById('open-checkout');
    const deadline = Date.now() + 10 * 60 * 1000;
    let checkoutWindow = null;
    let busy = false;
    let automatic = true;
    let timer = null;

    if (checkoutLink) {
        checkoutLink.addEventListener('click', (event) => {
            const opened = window.open('about:blank', 'udw-payment-' + Date.now(), 'popup,width=560,height=780');
            if (!opened) return;
            try {
                opened.opener = null;
                opened.location.replace(checkoutLink.href);
                checkoutWindow = opened;
                event.preventDefault();
            } catch (_) {
                try { opened.close(); } catch (_) {}
            }
        });
    }

    async function checkPayment() {
        if (busy) return;
        busy = true;
        clearTimeout(timer);
        button.disabled = true;
        button.textContent = 'Checking…';
        let delay = 10000;
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 15000);
        try {
            const response = await fetch(form.action, {
                method: 'POST', credentials: 'same-origin', signal: controller.signal,
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: new FormData(form),
            });
            if (response.status === 403 || response.status === 419) {
                automatic = false;
                notice.textContent = 'This payment link or session has expired. Open your invoice email again. Do not pay again if you were debited.';
                return;
            }
            if (response.status === 429) {
                delay = Math.max(10000, Math.min(60000, Number(response.headers.get('Retry-After') || 30) * 1000));
                notice.textContent = 'We are waiting briefly before checking again. Do not pay again if you were debited.';
                return;
            }
            const result = await response.json();
            if (response.ok && result.status === 'paid' && typeof result.redirect === 'string') {
                const destination = new URL(result.redirect, window.location.href);
                if (destination.origin !== window.location.origin) throw new Error('Invalid return address');
                automatic = false;
                notice.textContent = 'Payment confirmed. Opening your receipt…';
                try { if (checkoutWindow && !checkoutWindow.closed) checkoutWindow.close(); } catch (_) {}
                window.location.replace(destination.href);
                return;
            }
            if (result.status === 'failed' || result.status === 'mismatch') automatic = false;
            notice.textContent = result.message || 'Confirmation is taking longer. Do not pay again if you were debited.';
            if (!response.ok) delay = 20000;
        } catch (_) {
            delay = 20000;
            notice.textContent = 'We could not check payment just now. We will try again. Do not pay again if you were debited.';
        } finally {
            clearTimeout(timeout);
            busy = false;
            button.disabled = false;
            button.textContent = 'Check payment';
            if (automatic && Date.now() < deadline) timer = setTimeout(checkPayment, delay);
            else if (automatic) {
                automatic = false;
                notice.textContent = 'Automatic checks have paused. Use “Check payment” to check again. If you were debited, do not pay again.';
            }
        }
    }
    form.addEventListener('submit', (event) => { event.preventDefault(); checkPayment(); });
    checkPayment();
})();
</script>
</body>
</html>
