<?php

declare(strict_types=1);

use App\Modules\IncidentManagement\Http\Controllers\Api\V1\IncidentAttachmentController;
use App\Modules\IncidentManagement\Http\Controllers\Api\V1\IncidentCategoryController;
use App\Modules\IncidentManagement\Http\Controllers\Api\V1\IncidentCommentController;
use App\Modules\IncidentManagement\Http\Controllers\Api\V1\IncidentController;
use App\Modules\IncidentManagement\Http\Controllers\Api\V1\IncidentPriorityController;
use App\Modules\IncidentManagement\Http\Controllers\Api\V1\IncidentProgressCommentController;
use App\Modules\IncidentManagement\Http\Controllers\Api\V1\IncidentProgressReportController;
use App\Modules\IncidentManagement\Http\Controllers\Api\V1\IncidentStatusController;
use App\Modules\IncidentManagement\Http\Controllers\Api\V1\IncidentTaskController;
use App\Modules\IncidentManagement\Http\Controllers\Api\V1\PublicIncidentController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'module' => 'incident-management',
        'version' => config('modules.incident-management.version', '1.0.0'),
        'status' => 'active',
    ]);
})->name('index');

Route::prefix('v1')->name('v1.')->group(function (): void {
    Route::get('incidents', [IncidentController::class, 'index'])->name('incidents.index');
    Route::post('incidents', [IncidentController::class, 'store'])->name('incidents.store');
    Route::get('incidents/stats', [IncidentController::class, 'stats'])->name('incidents.stats');
    Route::get('incidents/export', [IncidentController::class, 'exportAudit'])->name('incidents.export');
    Route::get('incidents/{incident}', [IncidentController::class, 'show'])->name('incidents.show');
    Route::put('incidents/{incident}', [IncidentController::class, 'update'])->name('incidents.update');
    Route::delete('incidents/{incident}', [IncidentController::class, 'destroy'])->name('incidents.destroy');

    Route::post('incidents/{incident}/assign', [IncidentController::class, 'assign'])->name('incidents.assign');
    Route::post('incidents/{incident}/delegate', [IncidentController::class, 'delegate'])->name('incidents.delegate');
    Route::post('incidents/{incident}/escalate', [IncidentController::class, 'escalate'])->name('incidents.escalate');
    Route::post('incidents/{incident}/resolve', [IncidentController::class, 'resolve'])->name('incidents.resolve');
    Route::post('incidents/{incident}/close', [IncidentController::class, 'close'])->name('incidents.close');

    Route::post('incidents/{incident}/tasks', [IncidentTaskController::class, 'store'])->name('incidents.tasks.store');
    Route::put('incidents/{incident}/tasks/{task}', [IncidentTaskController::class, 'update'])->name('incidents.tasks.update');
    Route::post('incidents/{incident}/comments', [IncidentCommentController::class, 'store'])->name('incidents.comments.store');
    Route::post('incidents/{incident}/attachments', [IncidentAttachmentController::class, 'store'])->name('incidents.attachments.store');
    Route::post('incidents/{incident}/progress-reports', [IncidentProgressReportController::class, 'store'])->name('incidents.progress-reports.store');
    Route::post('progress-reports/{progressReport}/comments', [IncidentProgressCommentController::class, 'store'])->name('progress-reports.comments.store');

    Route::post('categories', [IncidentCategoryController::class, 'store'])->name('categories.store');
    Route::put('categories/{category}', [IncidentCategoryController::class, 'update'])->name('categories.update');
    Route::delete('categories/{category}', [IncidentCategoryController::class, 'destroy'])->name('categories.destroy');

    Route::post('priorities', [IncidentPriorityController::class, 'store'])->name('priorities.store');
    Route::put('priorities/{priority}', [IncidentPriorityController::class, 'update'])->name('priorities.update');
    Route::delete('priorities/{priority}', [IncidentPriorityController::class, 'destroy'])->name('priorities.destroy');

    Route::post('statuses', [IncidentStatusController::class, 'store'])->name('statuses.store');
    Route::put('statuses/{status}', [IncidentStatusController::class, 'update'])->name('statuses.update');
    Route::delete('statuses/{status}', [IncidentStatusController::class, 'destroy'])->name('statuses.destroy');

    Route::post('public/submit', [PublicIncidentController::class, 'submit'])
        ->middleware('throttle:incidents-public')
        ->withoutMiddleware('auth:sanctum')
        ->name('public.submit');
});
