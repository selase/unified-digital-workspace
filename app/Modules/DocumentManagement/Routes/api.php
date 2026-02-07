<?php

declare(strict_types=1);

use App\Modules\DocumentManagement\Http\Controllers\Api\V1\DocumentAuditController;
use App\Modules\DocumentManagement\Http\Controllers\Api\V1\DocumentController;
use App\Modules\DocumentManagement\Http\Controllers\Api\V1\DocumentQuizAttemptController;
use App\Modules\DocumentManagement\Http\Controllers\Api\V1\DocumentQuizController;
use App\Modules\DocumentManagement\Http\Controllers\Api\V1\DocumentVersionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'module' => 'document-management',
        'version' => config('modules.document-management.version', '1.0.0'),
        'status' => 'active',
    ]);
})->name('index');

Route::prefix('v1')->name('v1.')->group(function (): void {
    Route::get('documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::post('documents', [DocumentController::class, 'store'])->name('documents.store');
    Route::get('documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
    Route::put('documents/{document}', [DocumentController::class, 'update'])->name('documents.update');
    Route::delete('documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');

    Route::post('documents/{document}/versions', [DocumentVersionController::class, 'store'])->name('documents.versions.store');
    Route::get('documents/{document}/versions', [DocumentVersionController::class, 'index'])->name('documents.versions.index');
    Route::get('documents/{document}/download/{version?}', [DocumentVersionController::class, 'download'])->name('documents.download');

    Route::post('documents/{document}/quizzes', [DocumentQuizController::class, 'store'])->name('documents.quizzes.store');
    Route::get('documents/{document}/quizzes/{quiz}', [DocumentQuizController::class, 'show'])->name('documents.quizzes.show');
    Route::post('quizzes/{quiz}/attempts', [DocumentQuizAttemptController::class, 'store'])->name('quizzes.attempts.store');

    Route::get('documents/{document}/audits', [DocumentAuditController::class, 'index'])->name('documents.audits.index');
});
