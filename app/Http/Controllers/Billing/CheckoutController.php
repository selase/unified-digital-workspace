<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Contracts\PaymentGateway;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\Request;

final class CheckoutController extends Controller
{
    public function store(Request $request, PaymentGateway $gateway, TenantContext $tenantContext)
    {
        $tenant = $tenantContext->getTenant();
        if (!$tenant) {
            abort(404, 'Tenant not found');
        }

        // Handle Invoice Payment
        if ($request->has('invoice_id')) {
            return $this->handleInvoiceCheckout($request, $gateway, $tenant);
        }

        // Handle Plan Subscription
        return $this->handlePlanCheckout($request, $gateway, $tenant);
    }

    private function handleInvoiceCheckout(Request $request, PaymentGateway $gateway, $tenant)
    {
        $invoice = Invoice::where('tenant_id', $tenant->id)
            ->where('status', Invoice::STATUS_ISSUED)
            ->findOrFail($request->input('invoice_id'));

        $customerId = $this->getOrCreateCustomerId($request, $gateway, $tenant);
        $amount = (int) round((float)$invoice->total * 100);
        $redirectUrl = route('billing.invoices.show', $invoice->id);

        $checkoutUrl = $gateway->createOneTimeCheckoutSession(
            $customerId,
            $amount,
            $invoice->currency ?: 'USD',
            $redirectUrl,
            [
                'invoice_id' => $invoice->id,
                'type' => 'invoice_payment',
                'description' => "Invoice #{$invoice->number}",
            ]
        );

        return redirect($checkoutUrl);
    }

    private function handlePlanCheckout(Request $request, PaymentGateway $gateway, $tenant)
    {
        $validated = $request->validate([
            'plan' => 'required|string',
        ]);

        $customerId = $this->getOrCreateCustomerId($request, $gateway, $tenant);
        $redirectUrl = route('billing.index');

        $checkoutUrl = $gateway->createCheckoutSession(
            $customerId,
            $validated['plan'],
            $redirectUrl
        );

        return redirect($checkoutUrl);
    }

    private function getOrCreateCustomerId(Request $request, PaymentGateway $gateway, $tenant): string
    {
        $driver = config('services.payment.default', 'paystack');
        $metaKey = "{$driver}_id";
        $customerId = $tenant->meta[$metaKey] ?? null;

        if (!$customerId) {
            $customerId = $gateway->createCustomer($request->user()->email, $tenant->name);
            $meta = $tenant->meta ?? [];
            $meta[$metaKey] = $customerId;
            $tenant->update(['meta' => $meta]);
        }

        return $customerId;
    }
}
