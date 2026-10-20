<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Contracts\PaymentGateway;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Exception;
use Illuminate\Http\Request;
use Log;

final class RefundController extends Controller
{
    public function store(Request $request, Transaction $transaction)
    {
        $this->authorize('admin.tenants.update');

        if ($transaction->status !== 'success' && $transaction->status !== 'succeeded') { // Handle both terminologies if mixed
            abort(403, 'Transaction is not eligible for refund.');
        }

        // 2. Process Refund via Gateway
        try {
            $gateway = app(PaymentGateway::class);
            $refundResult = $gateway->refund($transaction->provider_transaction_id);

            // 3. Update Transaction Status
            $transaction->update([
                'status' => 'refunded',
                'meta' => array_merge($transaction->meta ?? [], ['refund_info' => $refundResult]),
            ]);

            return back()->with('success', 'Refund initiated successfully.');

        } catch (Exception $e) {
            // Log error
            Log::error('Refund failed', ['error' => $e->getMessage(), 'transaction_id' => $transaction->id]);

            return back()->with('error', 'Refund failed: '.$e->getMessage());
        }
    }
}
