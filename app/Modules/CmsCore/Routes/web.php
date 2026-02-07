<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'module' => 'cms-core',
        'status' => 'active',
    ]);
})->name('index');
