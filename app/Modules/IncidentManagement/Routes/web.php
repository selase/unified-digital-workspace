<?php

declare(strict_types=1);

use App\Modules\IncidentManagement\Http\Controllers\Web\IncidentHubController;
use App\Modules\IncidentManagement\Http\Controllers\Web\IncidentListController;
use App\Modules\IncidentManagement\Http\Controllers\Web\IncidentReportController;
use App\Modules\IncidentManagement\Http\Controllers\Web\IncidentTaskBoardController;
use Illuminate\Support\Facades\Route;

Route::get('/', [IncidentHubController::class, 'index'])->name('index');
Route::prefix('incidents')->name('incidents.')->group(function (): void {
    Route::get('/', [IncidentListController::class, 'index'])->name('index');
    Route::get('/create', [IncidentListController::class, 'create'])->name('create');
    Route::post('/', [IncidentListController::class, 'store'])->name('store');
    Route::get('/{incident}', [IncidentListController::class, 'show'])->name('show');
    Route::get('/{incident}/edit', [IncidentListController::class, 'edit'])->name('edit');
    Route::put('/{incident}', [IncidentListController::class, 'update'])->name('update');
    Route::delete('/{incident}', [IncidentListController::class, 'destroy'])->name('destroy');
    Route::post('/{incident}/assign', [IncidentListController::class, 'assign'])->name('assign');
    Route::post('/{incident}/escalate', [IncidentListController::class, 'escalate'])->name('escalate');
    Route::post('/{incident}/resolve', [IncidentListController::class, 'resolve'])->name('resolve');
    Route::post('/{incident}/close', [IncidentListController::class, 'close'])->name('close');
});
Route::get('/tasks', [IncidentTaskBoardController::class, 'index'])->name('tasks.index');
Route::get('/reports', [IncidentReportController::class, 'index'])->name('reports.index');
