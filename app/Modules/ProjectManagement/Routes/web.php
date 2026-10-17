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
Route::post('/projects/{project:uuid}/stage-gate', [ProjectWebController::class, 'reviewStageGate'])->name('projects.stage-gate');
Route::post('/projects/{project:uuid}/milestones', [ProjectWebController::class, 'storeMilestone'])->name('projects.milestones.store');
Route::post('/projects/{project:uuid}/milestones/{milestone}/toggle', [ProjectWebController::class, 'toggleMilestone'])->name('projects.milestones.toggle');
Route::post('/projects/{project:uuid}/tasks', [ProjectWebController::class, 'storeTask'])->name('projects.tasks.store');
Route::post('/projects/{project:uuid}/tasks/{task:uuid}/status', [ProjectWebController::class, 'updateTaskStatus'])->name('projects.tasks.status');
Route::post('/projects/{project:uuid}/members', [ProjectWebController::class, 'storeMember'])->name('projects.members.store');

Route::get('/tasks', [TaskListController::class, 'index'])->name('tasks.index');
Route::post('/tasks/{task:uuid}/status', [TaskListController::class, 'updateStatus'])->name('tasks.status');
