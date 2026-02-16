<?php

declare(strict_types=1);

use App\Modules\QualityMonitoring\Http\Controllers\Web\QualityMonitoringHubController;
use Illuminate\Support\Facades\Route;

Route::get('/', [QualityMonitoringHubController::class, 'index'])->name('index');
