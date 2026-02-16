<?php

declare(strict_types=1);

use App\Modules\Memos\Http\Controllers\Web\MemoHubController;
use Illuminate\Support\Facades\Route;

Route::get('/', [MemoHubController::class, 'index'])->name('index');
