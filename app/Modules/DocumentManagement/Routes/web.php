<?php

declare(strict_types=1);

use App\Modules\DocumentManagement\Http\Controllers\Web\DocumentAuditLibraryController;
use App\Modules\DocumentManagement\Http\Controllers\Web\DocumentHubController;
use App\Modules\DocumentManagement\Http\Controllers\Web\DocumentLibraryController;
use App\Modules\DocumentManagement\Http\Controllers\Web\DocumentQuizAnalyticsController;
use App\Modules\DocumentManagement\Http\Controllers\Web\DocumentQuizLibraryController;
use App\Modules\DocumentManagement\Http\Controllers\Web\DocumentWebController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DocumentHubController::class, 'index'])->name('index');
Route::get('/documents', [DocumentLibraryController::class, 'index'])->name('documents.index');
Route::get('/documents/create', [DocumentWebController::class, 'create'])->name('documents.create');
Route::post('/documents', [DocumentWebController::class, 'store'])->name('documents.store');
Route::get('/documents/{document}', [DocumentWebController::class, 'show'])->name('documents.show');
Route::post('/documents/{document}/versions', [DocumentWebController::class, 'storeVersion'])->name('documents.versions.store');
Route::post('/documents/{document}/publish', [DocumentWebController::class, 'publish'])->name('documents.publish');
Route::post('/documents/{document}/archive', [DocumentWebController::class, 'archive'])->name('documents.archive');
Route::get('/documents/{document}/download/{version?}', [DocumentWebController::class, 'download'])->name('documents.download');

Route::get('/quizzes', [DocumentQuizLibraryController::class, 'index'])->name('quizzes.index');
Route::get('/analytics', [DocumentQuizAnalyticsController::class, 'index'])->name('analytics.index');
Route::get('/audits', [DocumentAuditLibraryController::class, 'index'])->name('audits.index');
