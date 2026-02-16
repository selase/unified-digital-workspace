<?php

declare(strict_types=1);

use App\Modules\ProjectManagement\Http\Controllers\Web\ProjectHubController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ProjectHubController::class, 'index'])->name('index');
