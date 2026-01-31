<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Contracts\PaymentGateway;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Transaction;
use App\Services\Tenancy\TenantContext;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class CallbackController extends Controller
{
    public function __invoke(Request $request, PaymentGateway $gateway, TenantContext $tenantContext)
    {
        $reference = $request->query('session_id') ?? $request->query('reference');

        if (! $reference) {
            $tenant = $tenantContext->getTenant();

            return redirect()->route('billing.index', ['subdomain' => $tenant->slug])->with('error', 'Invalid payment reference.');
        }

        try {
            $result = $gateway->verifyTransaction($reference);

            if ($result['status'] === 'succeeded' || $result['status'] === 'success' || $result['status'] === 'paid') {

                // Check if this was an invoice payment
                $invoiceId = data_get($result, 'metadata.invoice_id');

                if ($invoiceId) {
                    return $this->handleInvoiceFulfillment((string) $invoiceId, $result, $tenantContext);
                }

                // Check if this was an LLM token purchase
                $type = data_get($result, 'metadata.type');
                if ($type === 'llm_token_purchase') {
                    return $this->handleLlmTokenFulfillment($result, $tenantContext);
                }

                // Default plan fulfillment message (usually handled by webhook, but we show success)
                $tenant = $tenantContext->getTenant();

                return redirect()->route('billing.index', ['subdomain' => $tenant->slug])->with('success', 'Payment successful! Your subscription is being updated.');
            }

            $tenant = $tenantContext->getTenant();

            return redirect()->route('billing.index', ['subdomain' => $tenant->slug])->with('info', 'Payment status: '.$result['status']);

        } catch (Exception $e) {
            Log::error('Payment verification failed: '.$e->getMessage());
            $tenant = $tenantContext->getTenant();

            return redirect()->route('billing.index', ['subdomain' => $tenant->slug])->with('error', 'Unable to verify payment status.');
        }
    }

    private function handleInvoiceFulfillment(string $invoiceId, array $result, TenantContext $tenantContext)
    {
        $tenant = $tenantContext->getTenant();
        $invoice = Invoice::where('tenant_id', $tenant->id)->findOrFail($invoiceId);

        if ($invoice->status !== Invoice::STATUS_PAID) {
            // Update Invoice
            $invoice->update([
                'status' => Invoice::STATUS_PAID,
                'paid_at' => now(),
            ]);

            // Create Transaction Record
            Transaction::create([
                'tenant_id' => $tenant->id,
                'invoice_id' => $invoice->id,
                'amount' => $invoice->total * 100, // store in cents
                'currency' => $invoice->currency,
                'status' => 'success',
                'provider' => config('services.payment.default', 'paystack'),
                'provider_transaction_id' => (string) ($result['transaction_id'] ?? $invoice->number),
            ]);
        }

        return redirect()->route('billing.invoices.show', $invoice->id)
            ->with('success', 'Invoice paid successfully! Thank you for your payment.');
    }

    private function handleLlmTokenFulfillment(array $result, TenantContext $tenantContext)
    {
        $tenant = $tenantContext->getTenant();
        $packKey = data_get($result, 'metadata.pack_key');
        $packs = Config::get('llm.token_packs', []);

        if (! isset($packs[$packKey])) {
            Log::error("Invalid token pack {$packKey} on fulfillment for tenant {$tenant->id}");

            return redirect()->route('tenant.llm-usage.index', ['subdomain' => $tenant->slug])
                ->with('error', 'Invalid token pack fulfillment.');
        }

        $pack = $packs[$packKey];
        $tokens = (int) $pack['tokens'];

        DB::connection('landlord')->table('tenants')
            ->where('id', $tenant->id)
            ->increment('llm_topup_balance', $tokens);

        // Create Transaction Record
        Transaction::create([
            'tenant_id' => $tenant->id,
            'amount' => (int) round((float) $pack['price'] * 100),
            'currency' => $pack['currency'],
            'status' => 'success',
            'type' => 'credit',
            'provider' => config('services.payment.default', 'stripe'),
            'provider_transaction_id' => (string) ($result['transaction_id'] ?? 'token_purchase_'.now()->timestamp),
            'meta' => [
                'type' => 'llm_token_purchase',
                'pack_key' => $packKey,
                'tokens' => $tokens,
            ],
        ]);

        return redirect()->route('tenant.llm-usage.index', ['subdomain' => $tenant->slug])
            ->with('success', 'Success! '.number_format($tokens).' tokens have been added to your balance.');
    }
}
