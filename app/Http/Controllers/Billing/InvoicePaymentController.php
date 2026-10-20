<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Contracts\PaymentGateway;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\Request;

final class InvoicePaymentController extends Controller
{
    public function checkout(Request $request, PaymentGateway $gateway, TenantContext $tenantContext)
    {
        $tenant = $tenantContext->getTenant();
        if (! $tenant) {
            abort(404, 'Tenant context not found.');
        }

        $invoice = Invoice::query()->where('tenant_id', $tenant->id)
            ->whereIn('status', [Invoice::STATUS_ISSUED, Invoice::STATUS_OVERDUE])
            ->findOrFail($request->input('invoice_id'));

        return redirect()->away(app(\App\Services\Billing\InvoiceCheckoutService::class)->checkout($invoice));
    }
}
