<?php

declare(strict_types=1);

use App\Modules\ProjectManagement\Http\Controllers\Web\ProjectHubController;
use App\Modules\ProjectManagement\Http\Controllers\Web\ProjectListController;
use App\Modules\ProjectManagement\Http\Controllers\Web\TaskListController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/project-management/hub');

Route::get('/hub', [ProjectHubController::class, 'index'])->name('index');
Route::get('/projects', [ProjectListController::class, 'index'])->name('projects.index');
Route::get('/tasks', [TaskListController::class, 'index'])->name('tasks.index');
