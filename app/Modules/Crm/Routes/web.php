<?php

declare(strict_types=1);

use App\Modules\Crm\Http\Controllers\Web\CrmWebController;
use Illuminate\Support\Facades\Route;

Route::get('/', [CrmWebController::class, 'index'])->name('index');
Route::post('/leads', [CrmWebController::class, 'storeLead'])->name('leads.store');
Route::post('/leads/{lead}/convert', [CrmWebController::class, 'convertLead'])->name('leads.convert');
Route::post('/accounts', [CrmWebController::class, 'storeAccount'])->name('accounts.store');
Route::post('/deals', [CrmWebController::class, 'storeDeal'])->name('deals.store');
Route::post('/deals/{deal}/stage', [CrmWebController::class, 'updateDealStage'])->name('deals.stage');
