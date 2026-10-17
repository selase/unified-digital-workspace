<?php

declare(strict_types=1);

use App\Modules\Crm\Http\Controllers\Web\CrmWebController;
use Illuminate\Support\Facades\Route;

Route::get('/', [CrmWebController::class, 'index'])->name('index');
Route::get('/pipeline', [CrmWebController::class, 'pipeline'])->name('pipeline');

// Leads CRUD & Lifecycle
Route::get('/leads', [CrmWebController::class, 'leadsIndex'])->name('leads.index');
Route::post('/leads', [CrmWebController::class, 'storeLead'])->name('leads.store');
Route::get('/leads/{lead}', [CrmWebController::class, 'showLead'])->name('leads.show');
Route::get('/leads/{lead}/edit', [CrmWebController::class, 'editLead'])->name('leads.edit');
Route::put('/leads/{lead}', [CrmWebController::class, 'updateLead'])->name('leads.update');
Route::delete('/leads/{lead}', [CrmWebController::class, 'destroyLead'])->name('leads.destroy');
Route::post('/leads/{lead}/convert', [CrmWebController::class, 'convertLead'])->name('leads.convert');
Route::post('/leads/{lead}/status', [CrmWebController::class, 'updateLeadStatus'])->name('leads.status');
Route::post('/leads/{lead}/activities', [CrmWebController::class, 'storeLeadActivity'])->name('leads.activities.store');

// Client Accounts CRUD & Contacts
Route::get('/accounts', [CrmWebController::class, 'accountsIndex'])->name('accounts.index');
Route::post('/accounts', [CrmWebController::class, 'storeAccount'])->name('accounts.store');
Route::get('/accounts/{account}', [CrmWebController::class, 'showAccount'])->name('accounts.show');
Route::get('/accounts/{account}/edit', [CrmWebController::class, 'editAccount'])->name('accounts.edit');
Route::put('/accounts/{account}', [CrmWebController::class, 'updateAccount'])->name('accounts.update');
Route::delete('/accounts/{account}', [CrmWebController::class, 'destroyAccount'])->name('accounts.destroy');
Route::post('/accounts/{account}/contacts', [CrmWebController::class, 'storeAccountContact'])->name('accounts.contacts.store');
Route::get('/contacts/{contact}/edit', [CrmWebController::class, 'editContact'])->name('contacts.edit');
Route::put('/contacts/{contact}', [CrmWebController::class, 'updateContact'])->name('contacts.update');
Route::delete('/contacts/{contact}', [CrmWebController::class, 'destroyContact'])->name('contacts.destroy');
Route::post('/accounts/{account}/activities', [CrmWebController::class, 'storeAccountActivity'])->name('accounts.activities.store');

// Deals CRUD & Pipeline
Route::get('/deals', [CrmWebController::class, 'dealsIndex'])->name('deals.index');
Route::post('/deals', [CrmWebController::class, 'storeDeal'])->name('deals.store');
Route::get('/deals/{deal}', [CrmWebController::class, 'showDeal'])->name('deals.show');
Route::get('/deals/{deal}/edit', [CrmWebController::class, 'editDeal'])->name('deals.edit');
Route::put('/deals/{deal}', [CrmWebController::class, 'updateDeal'])->name('deals.update');
Route::delete('/deals/{deal}', [CrmWebController::class, 'destroyDeal'])->name('deals.destroy');
Route::post('/deals/{deal}/stage', [CrmWebController::class, 'updateDealStage'])->name('deals.stage');
Route::post('/deals/{deal}/activities', [CrmWebController::class, 'storeDealActivity'])->name('deals.activities.store');
