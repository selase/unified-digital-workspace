<?php

declare(strict_types=1);

use App\Modules\ProjectManagement\Http\Controllers\Web\ProjectHubController;
use App\Modules\ProjectManagement\Http\Controllers\Web\ProjectListController;
use App\Modules\ProjectManagement\Http\Controllers\Web\ProjectWebController;
use App\Modules\ProjectManagement\Http\Controllers\Web\TaskListController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/project-management/hub');

Route::get('/hub', [ProjectHubController::class, 'index'])->name('index');
Route::get('/projects', [ProjectListController::class, 'index'])->name('projects.index');
Route::get('/projects/create', [ProjectWebController::class, 'create'])->name('projects.create');
Route::post('/projects', [ProjectWebController::class, 'store'])->name('projects.store');
Route::get('/projects/{project:uuid}', [ProjectWebController::class, 'show'])->name('projects.show');
Route::get('/projects/{project:uuid}/edit', [ProjectWebController::class, 'edit'])->name('projects.edit');
Route::put('/projects/{project:uuid}', [ProjectWebController::class, 'update'])->name('projects.update');
Route::delete('/projects/{project:uuid}', [ProjectWebController::class, 'destroy'])->name('projects.destroy');
Route::post('/projects/{project:uuid}/budget', [ProjectWebController::class, 'updateBudget'])->name('projects.budget.update');

Route::post('/projects/{project:uuid}/stage-gate', [ProjectWebController::class, 'reviewStageGate'])->name('projects.stage-gate');
Route::post('/projects/{project:uuid}/milestones', [ProjectWebController::class, 'storeMilestone'])->name('projects.milestones.store');
Route::post('/projects/{project:uuid}/milestones/{milestone}/toggle', [ProjectWebController::class, 'toggleMilestone'])->name('projects.milestones.toggle');

Route::post('/projects/{project:uuid}/tasks', [ProjectWebController::class, 'storeTask'])->name('projects.tasks.store');
Route::post('/projects/{project:uuid}/tasks/{task:uuid}/update', [ProjectWebController::class, 'updateTask'])->name('projects.tasks.update');
Route::post('/projects/{project:uuid}/tasks/{task:uuid}/delete', [ProjectWebController::class, 'destroyTask'])->name('projects.tasks.destroy');
Route::post('/projects/{project:uuid}/tasks/{task:uuid}/status', [ProjectWebController::class, 'updateTaskStatus'])->name('projects.tasks.status');

Route::post('/projects/{project:uuid}/risks', [ProjectWebController::class, 'storeRisk'])->name('projects.risks.store');
Route::post('/projects/{project:uuid}/risks/{risk:uuid}/update', [ProjectWebController::class, 'updateRisk'])->name('projects.risks.update');
Route::post('/projects/{project:uuid}/risks/{risk:uuid}/delete', [ProjectWebController::class, 'destroyRisk'])->name('projects.risks.destroy');

Route::post('/projects/{project:uuid}/time-entries', [ProjectWebController::class, 'storeTimeEntry'])->name('projects.time-entries.store');
Route::post('/projects/{project:uuid}/time-entries/{timeEntry}/delete', [ProjectWebController::class, 'destroyTimeEntry'])->name('projects.time-entries.destroy');

Route::post('/projects/{project:uuid}/members', [ProjectWebController::class, 'storeMember'])->name('projects.members.store');

Route::get('/tasks', [TaskListController::class, 'index'])->name('tasks.index');
Route::post('/tasks/{task:uuid}/status', [TaskListController::class, 'updateStatus'])->name('tasks.status');
