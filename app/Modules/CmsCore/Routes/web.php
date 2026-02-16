<?php

declare(strict_types=1);

use App\Modules\CmsCore\Http\Controllers\Web\CmsHubController;
use App\Modules\CmsCore\Http\Controllers\Web\CmsMediaLibraryController;
use App\Modules\CmsCore\Http\Controllers\Web\CmsMenuLibraryController;
use App\Modules\CmsCore\Http\Controllers\Web\CmsPostLibraryController;
use Illuminate\Support\Facades\Route;

Route::get('/', [CmsHubController::class, 'index'])->name('index');
Route::get('/posts', [CmsPostLibraryController::class, 'index'])->name('posts.index');
Route::get('/media', [CmsMediaLibraryController::class, 'index'])->name('media.index');
Route::get('/menus', [CmsMenuLibraryController::class, 'index'])->name('menus.index');
