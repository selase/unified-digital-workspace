<?php

declare(strict_types=1);

use App\Modules\Reports\Http\Controllers\Web\ReportsWebController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ReportsWebController::class, 'index'])->name('index');
Route::get('/create', [ReportsWebController::class, 'create'])->name('create');
Route::post('/', [ReportsWebController::class, 'store'])->name('store');
Route::get('/{report}', [ReportsWebController::class, 'show'])->name('show');
Route::get('/{report}/export/csv', [ReportsWebController::class, 'exportCsv'])->name('export.csv');
Route::delete('/{report}', [ReportsWebController::class, 'destroy'])->name('destroy');
