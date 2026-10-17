<?php

declare(strict_types=1);

use App\Modules\Memos\Http\Controllers\Web\MemoHubController;
use App\Modules\Memos\Http\Controllers\Web\MemoListController;
use App\Modules\Memos\Http\Controllers\Web\MemoWebController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/memos/hub');

Route::get('/hub', [MemoHubController::class, 'index'])->name('index');
Route::get('/list', [MemoListController::class, 'index'])->name('list');

Route::get('/create', [MemoWebController::class, 'create'])->name('create');
Route::post('/', [MemoWebController::class, 'store'])->name('store');
Route::get('/{memo}', [MemoWebController::class, 'show'])->name('show');
Route::post('/{memo}/minutes', [MemoWebController::class, 'minute'])->name('minute');
Route::post('/{memo}/sign', [MemoWebController::class, 'sign'])->name('sign');
Route::post('/{memo}/acknowledge', [MemoWebController::class, 'acknowledge'])->name('acknowledge');
