<?php

declare(strict_types=1);

use App\Modules\QualityMonitoring\Http\Controllers\Web\AlertListController;
use App\Modules\QualityMonitoring\Http\Controllers\Web\QualityMonitoringHubController;
use App\Modules\QualityMonitoring\Http\Controllers\Web\WorkplanListController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/quality-monitoring/hub');

Route::get('/hub', [QualityMonitoringHubController::class, 'index'])->name('index');
Route::get('/workplans', [WorkplanListController::class, 'index'])->name('workplans.index');
Route::get('/alerts', [AlertListController::class, 'index'])->name('alerts.index');
