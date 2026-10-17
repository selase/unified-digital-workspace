<?php

declare(strict_types=1);

use App\Modules\QualityMonitoring\Http\Controllers\Web\AlertListController;
use App\Modules\QualityMonitoring\Http\Controllers\Web\QualityMonitoringHubController;
use App\Modules\QualityMonitoring\Http\Controllers\Web\WorkplanListController;
use App\Modules\QualityMonitoring\Http\Controllers\Web\WorkplanWebController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/quality-monitoring/hub');

Route::get('/hub', [QualityMonitoringHubController::class, 'index'])->name('index');

// Workplans Register & Authoring
Route::get('/workplans', [WorkplanListController::class, 'index'])->name('workplans.index');
Route::get('/workplans/create', [WorkplanWebController::class, 'create'])->name('workplans.create');
Route::post('/workplans', [WorkplanWebController::class, 'store'])->name('workplans.store');
Route::get('/workplans/{workplan}', [WorkplanWebController::class, 'show'])->name('workplans.show');

// Workplan Stage-Gate & Governance Lifecycle
Route::post('/workplans/{workplan}/submit', [WorkplanWebController::class, 'submit'])->name('workplans.submit');
Route::post('/workplans/{workplan}/approve', [WorkplanWebController::class, 'approve'])->name('workplans.approve');
Route::post('/workplans/{workplan}/reject', [WorkplanWebController::class, 'reject'])->name('workplans.reject');

// Clinical / Operational In-Workspace Logs
Route::post('/workplans/{workplan}/kpis/{kpi}/updates', [WorkplanWebController::class, 'storeKpiUpdate'])->name('workplans.kpi-update');
Route::post('/workplans/{workplan}/variances', [WorkplanWebController::class, 'storeVariance'])->name('workplans.variance');

// Performance Alerts & Triage
Route::get('/alerts', [AlertListController::class, 'index'])->name('alerts.index');
Route::post('/alerts/{alert}/acknowledge', [AlertListController::class, 'acknowledge'])->name('alerts.acknowledge');
