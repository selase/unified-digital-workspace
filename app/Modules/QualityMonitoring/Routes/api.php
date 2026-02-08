<?php

declare(strict_types=1);

use App\Modules\QualityMonitoring\Http\Controllers\Api\V1\WorkplanController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'module' => 'quality-monitoring',
        'version' => config('modules.quality-monitoring.version', '1.0.0'),
        'status' => 'active',
    ]);
})->name('index');

Route::prefix('v1')->name('v1.')->group(function (): void {
    Route::get('workplans', [WorkplanController::class, 'index'])->name('workplans.index');
    Route::post('workplans', [WorkplanController::class, 'store'])->name('workplans.store');
    Route::get('workplans/{workplan}', [WorkplanController::class, 'show'])->name('workplans.show');
    Route::put('workplans/{workplan}', [WorkplanController::class, 'update'])->name('workplans.update');
    Route::delete('workplans/{workplan}', [WorkplanController::class, 'destroy'])->name('workplans.destroy');
});
