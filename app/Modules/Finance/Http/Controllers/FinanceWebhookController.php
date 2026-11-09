<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Contracts\FinancePaymentGateway;
use App\Modules\Finance\Events\GatewayChargeSucceeded;
use App\Modules\Finance\Services\OnlinePaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

final class FinanceWebhookController extends Controller
{
    public function paystack(Request $request, string $tenant, FinancePaymentGateway $gateway, OnlinePaymentService $online): JsonResponse
    {
        if (! $gateway->isValidWebhookSignature($tenant, $request->getContent(), $request->header('x-paystack-signature'))) {
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        if ($request->input('event') === 'charge.success') {
            $reference = (string) $request->input('data.reference', '');

            try {
                if ($reference !== '') {
                    $online->recordVerified($tenant, $reference);
                    event(new GatewayChargeSucceeded($tenant, $reference));
                }
            } catch (Throwable $error) {
                report($error);

                return response()->json(['status' => 'retry'], 500);
            }
        }

        return response()->json(['status' => 'ok']);
    }
}
