<?php

declare(strict_types=1);

use App\Modules\DocumentManagement\Http\Controllers\Web\DocumentAuditLibraryController;
use App\Modules\DocumentManagement\Http\Controllers\Web\DocumentHubController;
use App\Modules\DocumentManagement\Http\Controllers\Web\DocumentLibraryController;
use App\Modules\DocumentManagement\Http\Controllers\Web\DocumentQuizAnalyticsController;
use App\Modules\DocumentManagement\Http\Controllers\Web\DocumentQuizLibraryController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DocumentHubController::class, 'index'])->name('index');
Route::get('/documents', [DocumentLibraryController::class, 'index'])->name('documents.index');
Route::get('/quizzes', [DocumentQuizLibraryController::class, 'index'])->name('quizzes.index');
Route::get('/analytics', [DocumentQuizAnalyticsController::class, 'index'])->name('analytics.index');
Route::get('/audits', [DocumentAuditLibraryController::class, 'index'])->name('audits.index');
