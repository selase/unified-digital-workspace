<?php

declare(strict_types=1);

use App\Modules\DocumentManagement\Http\Controllers\Web\DocumentHubController;
use App\Modules\DocumentManagement\Http\Controllers\Web\DocumentQuizAnalyticsController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DocumentHubController::class, 'index'])->name('index');
Route::get('/analytics', [DocumentQuizAnalyticsController::class, 'index'])->name('analytics.index');
