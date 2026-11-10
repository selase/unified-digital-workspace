<?php

declare(strict_types=1);

use App\Modules\Grants\Http\Controllers\FunderController;
use App\Modules\Grants\Http\Controllers\GrantController;
use Illuminate\Support\Facades\Route;

Route::get('/', [GrantController::class, 'overview'])->name('index');

Route::get('/funders', [FunderController::class, 'index'])->name('funders.index');
Route::post('/funders', [FunderController::class, 'store'])->name('funders.store');
Route::put('/funders/{funder}', [FunderController::class, 'update'])->name('funders.update');

Route::get('/agreements', [GrantController::class, 'index'])->name('grants.index');
Route::post('/agreements', [GrantController::class, 'store'])->name('grants.store');
Route::get('/agreements/{grant}', [GrantController::class, 'show'])->name('grants.show');
Route::post('/agreements/{grant}/status', [GrantController::class, 'status'])->name('grants.status');
Route::post('/agreements/{grant}/instalments', [GrantController::class, 'addInstalment'])->name('grants.instalments.store');
Route::post('/agreements/{grant}/budget', [GrantController::class, 'saveBudgetLine'])->name('grants.budget.store');
Route::post('/agreements/{grant}/budget/{account}/remove', [GrantController::class, 'removeBudgetLine'])->name('grants.budget.remove');
Route::get('/agreements/{grant}/report', [GrantController::class, 'funderReport'])->name('grants.report');
Route::post('/agreements/{grant}/reports', [GrantController::class, 'addReport'])->name('grants.reports.store');
Route::post('/instalments/{instalment}/receive', [GrantController::class, 'receiveInstalment'])->name('grants.instalments.receive');
Route::post('/instalments/{instalment}/void', [GrantController::class, 'voidInstalment'])->name('grants.instalments.void');
Route::post('/reports/{report}/submit', [GrantController::class, 'submitReport'])->name('grants.reports.submit');
