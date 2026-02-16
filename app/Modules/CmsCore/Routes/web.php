<?php

declare(strict_types=1);

use App\Modules\CmsCore\Http\Controllers\Web\CmsHubController;
use Illuminate\Support\Facades\Route;

Route::get('/', [CmsHubController::class, 'index'])->name('index');
