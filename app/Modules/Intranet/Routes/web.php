<?php

declare(strict_types=1);

use App\Modules\Intranet\Http\Controllers\Web\IntranetWebController;
use Illuminate\Support\Facades\Route;

Route::get('/', [IntranetWebController::class, 'index'])->name('index');
Route::post('/events', [IntranetWebController::class, 'storeEvent'])->name('events.store');
Route::post('/boards/{board}/posts', [IntranetWebController::class, 'storeBoardPost'])->name('boards.posts.store');
