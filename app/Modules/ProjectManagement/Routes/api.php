<?php

declare(strict_types=1);

use App\Modules\ProjectManagement\Http\Controllers\Api\V1\MilestoneController;
use App\Modules\ProjectManagement\Http\Controllers\Api\V1\ProjectController;
use App\Modules\ProjectManagement\Http\Controllers\Api\V1\ResourceAllocationController;
use App\Modules\ProjectManagement\Http\Controllers\Api\V1\TaskController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'module' => 'project-management',
        'version' => config('modules.project-management.version', '1.0.0'),
        'status' => 'active',
    ]);
})->name('index');

Route::prefix('v1')->name('v1.')->group(function (): void {
    Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::post('projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::get('projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
    Route::put('projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
    Route::delete('projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');
    Route::get('projects/{project}/gantt', [ProjectController::class, 'gantt'])->name('projects.gantt');
    Route::get('projects/{project}/summary', [ProjectController::class, 'summary'])->name('projects.summary');

    Route::post('projects/{project}/milestones', [MilestoneController::class, 'store'])->name('projects.milestones.store');
    Route::put('projects/{project}/milestones/{milestone}', [MilestoneController::class, 'update'])->name('projects.milestones.update');
    Route::delete('projects/{project}/milestones/{milestone}', [MilestoneController::class, 'destroy'])->name('projects.milestones.destroy');

    Route::get('projects/{project}/tasks', [TaskController::class, 'index'])->name('projects.tasks.index');
    Route::post('projects/{project}/tasks', [TaskController::class, 'store'])->name('projects.tasks.store');
    Route::put('projects/{project}/tasks/{task}', [TaskController::class, 'update'])->name('projects.tasks.update');
    Route::delete('projects/{project}/tasks/{task}', [TaskController::class, 'destroy'])->name('projects.tasks.destroy');

    Route::post('tasks/{task}/assign', [TaskController::class, 'assign'])->name('tasks.assign');
    Route::post('tasks/{task}/dependencies', [TaskController::class, 'addDependency'])->name('tasks.dependencies.store');
    Route::delete('tasks/{task}/dependencies/{dependency}', [TaskController::class, 'removeDependency'])->name('tasks.dependencies.destroy');
    Route::post('tasks/{task}/comment', [TaskController::class, 'comment'])->name('tasks.comment');
    Route::post('tasks/{task}/attach', [TaskController::class, 'attach'])->name('tasks.attach');
    Route::post('tasks/{task}/time-entries', [TaskController::class, 'timeEntry'])->name('tasks.time-entries.store');

    Route::post('projects/{project}/allocations', [ResourceAllocationController::class, 'store'])->name('projects.allocations.store');
    Route::put('projects/{project}/allocations/{allocation}', [ResourceAllocationController::class, 'update'])->name('projects.allocations.update');
    Route::delete('projects/{project}/allocations/{allocation}', [ResourceAllocationController::class, 'destroy'])->name('projects.allocations.destroy');
});
