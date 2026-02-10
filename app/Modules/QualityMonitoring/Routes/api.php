<?php

declare(strict_types=1);

use App\Modules\QualityMonitoring\Http\Controllers\Api\V1\ActivityController;
use App\Modules\QualityMonitoring\Http\Controllers\Api\V1\AlertController;
use App\Modules\QualityMonitoring\Http\Controllers\Api\V1\DataSourceController;
use App\Modules\QualityMonitoring\Http\Controllers\Api\V1\IndicatorController;
use App\Modules\QualityMonitoring\Http\Controllers\Api\V1\KpiController;
use App\Modules\QualityMonitoring\Http\Controllers\Api\V1\KpiUpdateController;
use App\Modules\QualityMonitoring\Http\Controllers\Api\V1\ObjectiveController;
use App\Modules\QualityMonitoring\Http\Controllers\Api\V1\VarianceController;
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
    Route::post('workplans/{workplan}/submit', [WorkplanController::class, 'submit'])->name('workplans.submit');
    Route::post('workplans/{workplan}/approve', [WorkplanController::class, 'approve'])->name('workplans.approve');
    Route::post('workplans/{workplan}/reject', [WorkplanController::class, 'reject'])->name('workplans.reject');
    Route::get('workplans/{workplan}/dashboard', [WorkplanController::class, 'dashboard'])->name('workplans.dashboard');

    Route::post('workplans/{workplan}/objectives', [ObjectiveController::class, 'store'])->name('objectives.store');
    Route::put('workplans/{workplan}/objectives/{objective}', [ObjectiveController::class, 'update'])->name('objectives.update');
    Route::delete('workplans/{workplan}/objectives/{objective}', [ObjectiveController::class, 'destroy'])->name('objectives.destroy');

    Route::post('objectives/{objective}/activities', [ActivityController::class, 'store'])->name('activities.store');
    Route::put('objectives/{objective}/activities/{activity}', [ActivityController::class, 'update'])->name('activities.update');
    Route::delete('objectives/{objective}/activities/{activity}', [ActivityController::class, 'destroy'])->name('activities.destroy');

    Route::post('activities/{activity}/kpis', [KpiController::class, 'store'])->name('kpis.store');
    Route::put('activities/{activity}/kpis/{kpi}', [KpiController::class, 'update'])->name('kpis.update');
    Route::delete('activities/{activity}/kpis/{kpi}', [KpiController::class, 'destroy'])->name('kpis.destroy');

    Route::post('kpis/{kpi}/updates', [KpiUpdateController::class, 'store'])->name('kpis.updates.store');

    Route::get('indicators', [IndicatorController::class, 'index'])->name('indicators.index');
    Route::post('indicators', [IndicatorController::class, 'store'])->name('indicators.store');

    Route::get('data-sources', [DataSourceController::class, 'index'])->name('data-sources.index');
    Route::post('data-sources', [DataSourceController::class, 'store'])->name('data-sources.store');

    Route::post('activities/{activity}/variances', [VarianceController::class, 'store'])->name('variances.store');

    Route::get('alerts', [AlertController::class, 'index'])->name('alerts.index');
    Route::post('alerts/{alert}/ack', [AlertController::class, 'acknowledge'])->name('alerts.ack');
});
