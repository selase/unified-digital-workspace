<?php

declare(strict_types=1);

use App\Http\Controllers\Billing\InvoicePaymentPortalController;
use App\Http\Controllers\Billing\WebhookController;
use Illuminate\Support\Facades\Route;

Route::middleware(['signed', 'cache.headers:no_store;private', App\Http\Middleware\BillingPaymentHeaders::class])->group(function (): void {
    Route::get('/billing/payment/{invoice}', [InvoicePaymentPortalController::class, 'show'])->middleware('throttle:30,1,billing-payment-read')->name('billing.payment.show');
    Route::post('/billing/payment/{invoice}/pay', [InvoicePaymentPortalController::class, 'pay'])->middleware('throttle:5,1,billing-payment-checkout')->name('billing.payment.pay');
    Route::get('/billing/payment/{invoice}/checkout', [InvoicePaymentPortalController::class, 'checkout'])->middleware('throttle:30,1,billing-payment-read')->name('billing.payment.checkout');
    Route::post('/billing/payment/{invoice}/check', [InvoicePaymentPortalController::class, 'check'])->middleware('throttle:20,1,billing-payment-check')->name('billing.payment.check');
    Route::get('/billing/payment/{invoice}/invoice.pdf', [InvoicePaymentPortalController::class, 'invoice'])->middleware('throttle:30,1,billing-payment-read')->name('billing.payment.invoice');
    Route::get('/billing/payment/{invoice}/receipt.pdf', [InvoicePaymentPortalController::class, 'receipt'])->middleware('throttle:30,1,billing-payment-read')->name('billing.payment.receipt');
});
Route::get('/billing/payment-return/{provider}', [InvoicePaymentPortalController::class, 'callback'])
    ->whereIn('provider', ['paystack', 'stripe'])->middleware(['throttle:30,1,billing-payment-return', App\Http\Middleware\BillingPaymentHeaders::class])->name('billing.payment.callback');
Route::post('/webhooks/paystack', [WebhookController::class, 'handlePaystack'])
    ->middleware(App\Http\Middleware\LogPaymentWebhook::class)->name('webhooks.paystack');
