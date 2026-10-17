<?php

declare(strict_types=1);

use App\Modules\WorkflowAutomation\Http\Controllers\Web\WorkflowAutomationWebController;
use Illuminate\Support\Facades\Route;

Route::get('/', [WorkflowAutomationWebController::class, 'index'])->name('index');
Route::get('/create', [WorkflowAutomationWebController::class, 'create'])->name('create');
Route::post('/', [WorkflowAutomationWebController::class, 'store'])->name('store');
Route::get('/{rule}', [WorkflowAutomationWebController::class, 'show'])->name('show');
Route::post('/{rule}/toggle', [WorkflowAutomationWebController::class, 'toggle'])->name('toggle');
Route::post('/{rule}/test-run', [WorkflowAutomationWebController::class, 'testRun'])->name('test-run');
Route::delete('/{rule}', [WorkflowAutomationWebController::class, 'destroy'])->name('destroy');
