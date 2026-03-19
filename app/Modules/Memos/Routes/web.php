<?php

declare(strict_types=1);

use App\Modules\Memos\Http\Controllers\Web\MemoHubController;
use App\Modules\Memos\Http\Controllers\Web\MemoListController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/memos/hub');

Route::get('/hub', [MemoHubController::class, 'index'])->name('index');
Route::get('/list', [MemoListController::class, 'index'])->name('list');
