<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Core Module Web Routes
|--------------------------------------------------------------------------
|
| These routes are loaded by the CoreServiceProvider and are prefixed
| with 'core' and use the 'core.' route name prefix.
|
*/

// Example route - can be removed or replaced
Route::get('/', function () {
    return response()->json([
        'module' => 'core',
        'status' => 'active',
        'message' => 'Core module is running',
    ]);
})->name('index');
