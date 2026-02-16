<?php

declare(strict_types=1);

use App\Modules\IncidentManagement\Http\Controllers\Web\IncidentHubController;
use App\Modules\IncidentManagement\Http\Controllers\Web\IncidentListController;
use App\Modules\IncidentManagement\Http\Controllers\Web\IncidentReportController;
use App\Modules\IncidentManagement\Http\Controllers\Web\IncidentTaskBoardController;
use Illuminate\Support\Facades\Route;

Route::get('/', [IncidentHubController::class, 'index'])->name('index');
Route::get('/incidents', [IncidentListController::class, 'index'])->name('incidents.index');
Route::get('/tasks', [IncidentTaskBoardController::class, 'index'])->name('tasks.index');
Route::get('/reports', [IncidentReportController::class, 'index'])->name('reports.index');
