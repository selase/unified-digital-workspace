<?php

declare(strict_types=1);

use App\Modules\Finance\Http\Controllers\FinanceWebhookController;
use App\Modules\Finance\Http\Controllers\PublicInvoiceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['signed', 'throttle:30,1', 'cache.headers:no_store;private'])->group(function (): void {
    Route::get('/finance-pay/{tenant}/{invoice}', [PublicInvoiceController::class, 'show'])->name('finance.public.invoice');
    Route::get('/finance-pay/{tenant}/{invoice}/pdf', [PublicInvoiceController::class, 'pdf'])->name('finance.public.pdf');
    Route::post('/finance-pay/{tenant}/{invoice}/pay', [PublicInvoiceController::class, 'pay'])->name('finance.public.pay');
    Route::get('/finance-pay/{tenant}/{invoice}/callback', [PublicInvoiceController::class, 'callback'])->name('finance.public.callback');
});

Route::post('/webhooks/finance/paystack/{tenant}', [FinanceWebhookController::class, 'paystack'])->name('finance.webhooks.paystack');
