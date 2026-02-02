<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Core Module API Routes
|--------------------------------------------------------------------------
|
| These routes are loaded by the CoreServiceProvider and are prefixed
| with 'api/core' and use the 'api.core.' route name prefix.
|
*/

// Example API route - can be removed or replaced
Route::get('/', function () {
    return response()->json([
        'module' => 'core',
        'status' => 'active',
        'version' => config('modules.core.version'),
    ]);
})->name('index');
