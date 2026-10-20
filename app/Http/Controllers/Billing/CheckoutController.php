<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Contracts\PaymentGateway;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Tenant;
use App\Models\UsageEvent;
use App\Models\UsageRollup;
use App\Services\Tenancy\TenantContext;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CheckoutController extends Controller
{
    public function store(Request $request, TenantContext $tenantContext)
    {
        $tenant = $tenantContext->getTenant();
        if (! $tenant) {
            abort(404, 'Tenant not found');
        }

        // Handle Invoice Payment
        if ($request->has('invoice_id')) {
            return $this->handleInvoiceCheckout($request, app(PaymentGateway::class), $tenant);
        }

        // Handle Plan Subscription
        return $this->handlePlanCheckout($request, $tenant);
    }

    private function handleInvoiceCheckout(Request $request, PaymentGateway $gateway, $tenant)
    {
        $invoice = Invoice::where('tenant_id', $tenant->id)
            ->where('status', Invoice::STATUS_ISSUED)
            ->findOrFail($request->input('invoice_id'));

        $request->attributes->set('platform_billing_driver', $invoice->currency === 'GHS' ? 'paystack' : 'stripe');
        $customerId = $this->getOrCreateCustomerId($request, $gateway, $tenant);
        $amount = (int) round((float) $invoice->total * 100);
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

    private function handlePlanCheckout(Request $request, $tenant)
    {
        $validated = $request->validate([
            'plan' => 'required|string',
        ]);

        if (array_key_exists($validated['plan'], config('packages.plans', []))) {
            $this->authorize('core.settings.manage');
            $package = Package::where('slug', $validated['plan'])->where('is_active', true)->firstOrFail();
            if ($package->priceForCurrency('USD') !== 0.0 || $package->price_ghs === null || $package->priceForCurrency('GHS') !== 0.0) {
                return back()->with('error', 'This usage plan must have explicit zero base prices in USD and GHS before it can be selected.');
            }

            $changed = DB::connection('landlord')->transaction(function () use ($tenant, $package): bool {
                $currentTenant = Tenant::query()->whereKey($tenant->id)->lockForUpdate()->firstOrFail();
                if ($currentTenant->package_id !== null && (string) $currentTenant->package_id !== (string) $package->id && $this->hasCurrentMonthUsage($currentTenant)) {
                    return false;
                }
                $tenant->update(['package_id' => $package->id]);
                $tenant->unsetRelation('package');
                $tenant->syncFeaturesFromPackage();

                return true;
            });

            if (! $changed) {
                return back()->with('error', 'Your plan cannot be changed directly because usage has already been recorded this month. Contact an administrator to arrange a change for the next billing period. Your current plan and pricing remain in place.');
            }

            return redirect()->route('tenant.billing.index')->with('success', 'Usage plan selected. Usage will be invoiced later; no upfront payment is due.');
        }

        $package = Package::where('slug', $validated['plan'])->first();
        if ($package) {
            try {
                $package->priceForCurrency($tenant->billingCurrency());
            } catch (DomainException $exception) {
                return back()->with('error', $exception->getMessage());
            }
        }

        $gateway = app(PaymentGateway::class);
        $customerId = $this->getOrCreateCustomerId($request, $gateway, $tenant);
        $redirectUrl = route('tenant.billing.index');

        $checkoutUrl = $gateway->createCheckoutSession(
            $customerId,
            $validated['plan'],
            $redirectUrl
        );

        return redirect($checkoutUrl);
    }

    private function hasCurrentMonthUsage(Tenant $tenant): bool
    {
        $start = now()->startOfMonth();
        $nextMonth = $start->copy()->addMonth();

        return UsageRollup::where('tenant_id', $tenant->id)
            ->where('period_start', '>=', $start)
            ->where('period_start', '<', $nextMonth)
            ->where('value', '>', 0)
            ->exists()
            || UsageEvent::where('tenant_id', $tenant->id)
                ->where('occurred_at', '>=', $start)
                ->where('occurred_at', '<', $nextMonth)
                ->where('quantity', '>', 0)
                ->exists();
    }

    private function getOrCreateCustomerId(Request $request, PaymentGateway $gateway, $tenant): string
    {
        $driver = $request->attributes->get('platform_billing_driver', $tenant->billingCurrency() === 'GHS' ? 'paystack' : 'stripe');
        $metaKey = "{$driver}_id";
        $customerId = $tenant->meta[$metaKey] ?? null;

        if (! $customerId) {
            $customerId = $gateway->createCustomer($request->user()->email, $tenant->name);
            $meta = $tenant->meta ?? [];
            $meta[$metaKey] = $customerId;
            $tenant->update(['meta' => $meta]);
        }

        return $customerId;
    }
}
