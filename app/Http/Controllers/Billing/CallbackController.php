<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Contracts\PaymentGateway;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\Billing\PaymentFulfillmentService;
use App\Services\Tenancy\TenantContext;
use DomainException;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class CallbackController extends Controller
{
    public function __invoke(
        Request $request,
        PaymentGateway $gateway,
        TenantContext $tenantContext,
        PaymentFulfillmentService $fulfillmentService
    ) {
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
                    return $this->handleInvoiceFulfillment((string) $invoiceId, $result, $tenantContext, $fulfillmentService);
                }

                // Check if this was an LLM token purchase
                $type = data_get($result, 'metadata.type');
                if ($type === 'llm_token_purchase') {
                    return $this->handleLlmTokenFulfillment($result, $tenantContext, $fulfillmentService);
                }

                $metadata = (array) data_get($result, 'metadata', []);
                $amount = (int) data_get($result, 'amount', 0);
                $txId = (string) (data_get($result, 'transaction_id') ?? $reference);
                $provider = (string) config('services.payment.default', 'paystack');
                $currency = mb_strtoupper((string) (data_get($result, 'currency') ?? data_get($metadata, 'currency') ?? 'GHS'));

                if ($type === 'real_estate_installment') {
                    $fulfillmentService->fulfillRealEstateInstallment($metadata, $amount, $txId, $provider, $currency);

                    return redirect()->route('real-estate.portal.index')
                        ->with('status', 'success')
                        ->with('message', 'Payment verified successfully! Your installment has been credited.');
                }

                if ($type === 'escrow_funding') {
                    $fulfillmentService->fulfillEscrowFunding($metadata, $amount, $txId, $provider, $currency);

                    return redirect()->route('vendor-marketplace.index')
                        ->with('status', 'success')
                        ->with('message', 'Escrow funded successfully upon verified payment.');
                }

                if ($type === 'store_order') {
                    $fulfillmentService->fulfillStoreOrder($metadata, $amount, $txId, $provider, $currency);

                    return redirect()->route('store.orders')
                        ->with('status', 'success')
                        ->with('message', 'Store order payment verified successfully.');
                }

                if ($type === 'platform_credit_purchase') {
                    $fulfillmentService->fulfillPlatformCreditPurchase($metadata, $amount, $txId, $provider, $currency);

                    return redirect()->route('store.platform')
                        ->with('status', 'success')
                        ->with('message', 'Platform credits purchased and credited to your wallet.');
                }

                // Default plan fulfillment message (usually handled by webhook, but we show success)
                $tenant = $tenantContext->getTenant();
                $subdomain = $tenant ? $tenant->slug : 'default';

                return redirect()->route('billing.index', ['subdomain' => $subdomain])->with('success', 'Payment successful! Your subscription is being updated.');
            }

            $tenant = $tenantContext->getTenant();
            $subdomain = $tenant ? $tenant->slug : 'default';

            return redirect()->route('billing.index', ['subdomain' => $subdomain])->with('info', 'Payment status: '.$result['status']);

        } catch (DomainException $e) {
            Log::warning('Payment fulfillment domain error: '.$e->getMessage());

            return back()->with('error', $e->getMessage());
        } catch (Exception $e) {
            Log::error('Payment verification failed: '.$e->getMessage());
            $tenant = $tenantContext->getTenant();
            $subdomain = $tenant ? $tenant->slug : 'default';

            return redirect()->route('billing.index', ['subdomain' => $subdomain])->with('error', 'Unable to verify payment status.');
        }
    }

    private function handleInvoiceFulfillment(string $invoiceId, array $result, TenantContext $tenantContext, PaymentFulfillmentService $fulfillmentService)
    {
        $tenant = $tenantContext->getTenant();
        $invoice = Invoice::where('tenant_id', $tenant->id)->findOrFail($invoiceId);
        $provider = (string) config('services.payment.default', 'paystack');
        $providerTransactionId = (string) ($result['transaction_id'] ?? $invoice->number);

        $fulfillmentService->fulfillInvoicePayment($invoice, $providerTransactionId, $provider);

        return redirect()->route('billing.invoices.show', $invoice->id)
            ->with('success', 'Invoice paid successfully! Thank you for your payment.');
    }

    private function handleLlmTokenFulfillment(array $result, TenantContext $tenantContext, PaymentFulfillmentService $fulfillmentService)
    {
        $tenant = $tenantContext->getTenant();
        $packKey = (string) data_get($result, 'metadata.pack_key');
        $provider = (string) config('services.payment.default', 'stripe');
        $providerTransactionId = (string) ($result['transaction_id'] ?? 'token_purchase_'.now()->timestamp);

        try {
            $fulfillment = $fulfillmentService->fulfillLlmTokenPurchase($tenant, $packKey, $providerTransactionId, $provider);

            return redirect()->route('tenant.llm-usage.index', ['subdomain' => $tenant->slug])
                ->with('success', 'Success! '.number_format($fulfillment['tokens']).' tokens have been added to your balance.');
        } catch (DomainException $e) {
            return redirect()->route('tenant.llm-usage.index', ['subdomain' => $tenant->slug])
                ->with('error', 'Invalid token pack fulfillment.');
        }
    }
}
