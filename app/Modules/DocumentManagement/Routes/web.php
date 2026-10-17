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

// Controlled Documents Library & CRUD
Route::get('/documents', [DocumentLibraryController::class, 'index'])->name('documents.index');
Route::get('/documents/create', [DocumentWebController::class, 'create'])->name('documents.create');
Route::post('/documents', [DocumentWebController::class, 'store'])->name('documents.store');
Route::get('/documents/{document}', [DocumentWebController::class, 'show'])->name('documents.show');
Route::get('/documents/{document}/edit', [DocumentWebController::class, 'edit'])->name('documents.edit');
Route::put('/documents/{document}', [DocumentWebController::class, 'update'])->name('documents.update');
Route::delete('/documents/{document}', [DocumentWebController::class, 'destroy'])->name('documents.destroy');

// ISO Lifecycle State Transitions
Route::post('/documents/{document}/submit-review', [DocumentWebController::class, 'submitForReview'])->name('documents.submit-review');
Route::post('/documents/{document}/approve', [DocumentWebController::class, 'approve'])->name('documents.approve');
Route::post('/documents/{document}/reject', [DocumentWebController::class, 'reject'])->name('documents.reject');
Route::post('/documents/{document}/publish', [DocumentWebController::class, 'publish'])->name('documents.publish');
Route::post('/documents/{document}/revise', [DocumentWebController::class, 'revise'])->name('documents.revise');
Route::post('/documents/{document}/obsolete', [DocumentWebController::class, 'markObsolete'])->name('documents.obsolete');
Route::post('/documents/{document}/archive', [DocumentWebController::class, 'archive'])->name('documents.archive');

// Version Control & File Downloads
Route::post('/documents/{document}/versions', [DocumentWebController::class, 'storeVersion'])->name('documents.versions.store');
Route::get('/documents/{document}/download/{version?}', [DocumentWebController::class, 'download'])->name('documents.download');

// Read Acknowledgements & Compliance Auditing
Route::post('/documents/{document}/acknowledge', [DocumentWebController::class, 'acknowledge'])->name('documents.acknowledge');
Route::get('/documents/{document}/compliance', [DocumentWebController::class, 'documentCompliance'])->name('documents.compliance');
Route::get('/compliance', [DocumentWebController::class, 'complianceOverview'])->name('compliance.index');

// Web Quizzes & Comprehension Verification
Route::get('/quizzes', [DocumentQuizLibraryController::class, 'index'])->name('quizzes.index');
Route::get('/documents/{document}/quizzes/create', [DocumentWebController::class, 'createQuiz'])->name('documents.quizzes.create');
Route::post('/documents/{document}/quizzes', [DocumentWebController::class, 'storeQuiz'])->name('documents.quizzes.store');
Route::get('/quizzes/{quiz}', [DocumentWebController::class, 'showQuiz'])->name('quizzes.show');
Route::post('/quizzes/{quiz}/attempt', [DocumentWebController::class, 'attemptQuiz'])->name('quizzes.attempt');

Route::get('/analytics', [DocumentQuizAnalyticsController::class, 'index'])->name('analytics.index');
Route::get('/audits', [DocumentAuditLibraryController::class, 'index'])->name('audits.index');
