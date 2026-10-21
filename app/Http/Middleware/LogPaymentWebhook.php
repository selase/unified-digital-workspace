<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class LogPaymentWebhook
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $logger = Log::channel('payment_webhooks');
        $context = [
            'attempt_id' => (string) Str::uuid(),
            'provider' => 'paystack',
            'payload_sha256' => hash('sha256', $request->getContent()),
        ];
        $started = hrtime(true);
        $response = null;
        $exception = null;
        $logger->info('payment_webhook.received', $context);

        try {
            $response = $next($request);
            $response->headers->set('X-Webhook-Attempt', $context['attempt_id']);

            if ($response instanceof \Illuminate\Http\Response || $response instanceof \Illuminate\Http\JsonResponse) {
                $exception = $response->exception;
            }

            return $response;
        } catch (Throwable $error) {
            $exception = $error;
            throw $error;
        } finally {
            $status = $response?->getStatusCode() ?? ($exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500);
            $logger->log($status >= 500 ? 'error' : ($status >= 400 ? 'warning' : 'info'), 'payment_webhook.completed', array_filter([
                ...$context,
                'http_status' => $status,
                'duration_ms' => round((hrtime(true) - $started) / 1_000_000, 2),
                'signature_valid' => $request->attributes->get('payment_webhook.signature_valid', false) === true,
                'outcome' => $request->attributes->get('payment_webhook.outcome', $status >= 500 ? 'processing_error' : 'unclassified'),
                'event' => $request->attributes->get('payment_webhook.event'),
                'reference' => $request->attributes->get('payment_webhook.reference'),
                'provider_transaction_id' => $request->attributes->get('payment_webhook.provider_transaction_id'),
                'invoice_id' => $request->attributes->get('payment_webhook.invoice_id'),
                'exception_class' => $exception === null ? null : $exception::class,
            ], static fn (mixed $value): bool => $value !== null));
        }
    }
}
