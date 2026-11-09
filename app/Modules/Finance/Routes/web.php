<?php

declare(strict_types=1);

use App\Modules\Finance\Http\Controllers\AccountController;
use App\Modules\Finance\Http\Controllers\BillController;
use App\Modules\Finance\Http\Controllers\ContactController;
use App\Modules\Finance\Http\Controllers\ExpenseController;
use App\Modules\Finance\Http\Controllers\FinanceDashboardController;
use App\Modules\Finance\Http\Controllers\InvoiceController;
use App\Modules\Finance\Http\Controllers\ItemController;
use App\Modules\Finance\Http\Controllers\JournalController;
use App\Modules\Finance\Http\Controllers\PaymentController;
use App\Modules\Finance\Http\Controllers\RecurringInvoiceController;
use App\Modules\Finance\Http\Controllers\ReportController;
use App\Modules\Finance\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

// '/finance' itself belongs to the tenant revenue page, so the module home lives at /finance/overview.
Route::get('/overview', [FinanceDashboardController::class, 'index'])->name('index');

Route::get('/accounts', [AccountController::class, 'index'])->name('accounts.index');
Route::post('/accounts', [AccountController::class, 'store'])->name('accounts.store');
Route::put('/accounts/{account}', [AccountController::class, 'update'])->name('accounts.update');
Route::post('/accounts/{account}/toggle', [AccountController::class, 'toggle'])->name('accounts.toggle');

Route::get('/journals', [JournalController::class, 'index'])->name('journals.index');
Route::get('/journals/create', [JournalController::class, 'create'])->name('journals.create');
Route::post('/journals', [JournalController::class, 'store'])->name('journals.store');
Route::get('/journals/{journal}', [JournalController::class, 'show'])->name('journals.show');
Route::post('/journals/{journal}/reverse', [JournalController::class, 'reverse'])->name('journals.reverse');

Route::get('/contacts', [ContactController::class, 'index'])->name('contacts.index');
Route::post('/contacts', [ContactController::class, 'store'])->name('contacts.store');
Route::put('/contacts/{contact}', [ContactController::class, 'update'])->name('contacts.update');
Route::delete('/contacts/{contact}', [ContactController::class, 'destroy'])->name('contacts.destroy');

Route::get('/items', [ItemController::class, 'index'])->name('items.index');
Route::post('/items', [ItemController::class, 'store'])->name('items.store');
Route::delete('/items/{item}', [ItemController::class, 'destroy'])->name('items.destroy');

Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
Route::get('/invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
Route::post('/invoices', [InvoiceController::class, 'store'])->name('invoices.store');
Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');
Route::get('/invoices/{invoice}/edit', [InvoiceController::class, 'edit'])->name('invoices.edit');
Route::put('/invoices/{invoice}', [InvoiceController::class, 'update'])->name('invoices.update');
Route::post('/invoices/{invoice}/send', [InvoiceController::class, 'send'])->name('invoices.send');
Route::post('/invoices/{invoice}/email', [InvoiceController::class, 'email'])->name('invoices.email');
Route::post('/invoices/{invoice}/credit-note', [InvoiceController::class, 'creditNote'])->name('invoices.credit-note');
Route::post('/invoices/{invoice}/convert', [InvoiceController::class, 'convert'])->name('invoices.convert');
Route::post('/invoices/{invoice}/void', [InvoiceController::class, 'void'])->name('invoices.void');

Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
Route::post('/payments', [PaymentController::class, 'store'])->name('payments.store');
Route::post('/payments/{payment}/void', [PaymentController::class, 'void'])->name('payments.void');

Route::get('/bills', [BillController::class, 'index'])->name('bills.index');
Route::get('/bills/create', [BillController::class, 'create'])->name('bills.create');
Route::post('/bills', [BillController::class, 'store'])->name('bills.store');
Route::get('/bills/{bill}', [BillController::class, 'show'])->name('bills.show');
Route::get('/bills/{bill}/edit', [BillController::class, 'edit'])->name('bills.edit');
Route::put('/bills/{bill}', [BillController::class, 'update'])->name('bills.update');
Route::post('/bills/{bill}/approve', [BillController::class, 'approve'])->name('bills.approve');
Route::post('/bills/{bill}/void', [BillController::class, 'void'])->name('bills.void');
Route::post('/bills/{bill}/pay', [BillController::class, 'pay'])->name('bills.pay');
Route::post('/bill-payments/{payment}/void', [BillController::class, 'voidPayment'])->name('bills.payments.void');

Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
Route::post('/expenses/{expense}/void', [ExpenseController::class, 'void'])->name('expenses.void');

Route::get('/recurring', [RecurringInvoiceController::class, 'index'])->name('recurring.index');
Route::post('/recurring', [RecurringInvoiceController::class, 'store'])->name('recurring.store');
Route::post('/recurring/{recurring}/toggle', [RecurringInvoiceController::class, 'toggle'])->name('recurring.toggle');

Route::get('/reports/aged-receivables', [ReportController::class, 'agedReceivables'])->name('reports.aged-receivables');
Route::get('/reports/aged-payables', [ReportController::class, 'agedPayables'])->name('reports.aged-payables');
Route::get('/reports/trial-balance', [ReportController::class, 'trialBalance'])->name('reports.trial-balance');

Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
Route::post('/settings/funds', [SettingsController::class, 'storeFund'])->name('settings.funds.store');
Route::post('/settings/taxes/ghana', [SettingsController::class, 'addGhanaTax'])->name('settings.taxes.ghana');
Route::post('/settings/taxes/{tax}/toggle', [SettingsController::class, 'toggleTax'])->name('settings.taxes.toggle');
