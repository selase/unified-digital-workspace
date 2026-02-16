<?php

declare(strict_types=1);

use App\Modules\IncidentManagement\Http\Controllers\Web\IncidentHubController;
use Illuminate\Support\Facades\Route;

Route::get('/', [IncidentHubController::class, 'index'])->name('index');
