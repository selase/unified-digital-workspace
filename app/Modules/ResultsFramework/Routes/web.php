<?php

declare(strict_types=1);

use App\Modules\ResultsFramework\Http\Controllers\IndicatorController;
use App\Modules\ResultsFramework\Http\Controllers\ProgrammeController;
use App\Modules\ResultsFramework\Http\Controllers\ResultNodeController;
use Illuminate\Support\Facades\Route;

Route::get('/', [IndicatorController::class, 'index'])->name('index');
Route::post('/indicators', [IndicatorController::class, 'store'])->name('indicators.store');
Route::get('/indicators/{indicator}', [IndicatorController::class, 'show'])->name('indicators.show');
Route::post('/indicators/{indicator}/readings', [IndicatorController::class, 'reading'])->name('indicators.readings.store');
Route::post('/indicators/{indicator}/toggle', [IndicatorController::class, 'toggle'])->name('indicators.toggle');
Route::get('/report', [IndicatorController::class, 'report'])->name('report');

Route::resource('programmes', ProgrammeController::class)->except('destroy');
Route::post('programmes/{programme}/close', [ProgrammeController::class, 'close'])->name('programmes.close');

Route::post('programmes/{programme}/results', [ResultNodeController::class, 'store'])->name('programmes.results.store');
Route::patch('programmes/{programme}/results/{result}', [ResultNodeController::class, 'move'])->name('programmes.results.move');
Route::delete('programmes/{programme}/results/{result}', [ResultNodeController::class, 'destroy'])->name('programmes.results.destroy');

Route::get('/indicators/{indicator}/edit', [IndicatorController::class, 'edit'])->name('indicators.edit');
Route::put('/indicators/{indicator}', [IndicatorController::class, 'update'])->name('indicators.update');
