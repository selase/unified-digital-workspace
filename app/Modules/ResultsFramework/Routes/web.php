<?php

declare(strict_types=1);

use App\Modules\ResultsFramework\Http\Controllers\IndicatorController;
use Illuminate\Support\Facades\Route;

Route::get('/', [IndicatorController::class, 'index'])->name('index');
Route::post('/indicators', [IndicatorController::class, 'store'])->name('indicators.store');
Route::get('/indicators/{indicator}', [IndicatorController::class, 'show'])->name('indicators.show');
Route::post('/indicators/{indicator}/readings', [IndicatorController::class, 'reading'])->name('indicators.readings.store');
Route::post('/indicators/{indicator}/toggle', [IndicatorController::class, 'toggle'])->name('indicators.toggle');
Route::get('/report', [IndicatorController::class, 'report'])->name('report');
