<?php

declare(strict_types=1);

use App\Modules\CmsCore\Http\Controllers\Web\CmsCategoryController;
use App\Modules\CmsCore\Http\Controllers\Web\CmsHubController;
use App\Modules\CmsCore\Http\Controllers\Web\CmsMediaLibraryController;
use App\Modules\CmsCore\Http\Controllers\Web\CmsMenuLibraryController;
use App\Modules\CmsCore\Http\Controllers\Web\CmsPostLibraryController;
use App\Modules\CmsCore\Http\Controllers\Web\CmsSettingsController;
use App\Modules\CmsCore\Http\Controllers\Web\CmsTagController;
use Illuminate\Support\Facades\Route;

Route::get('/', [CmsHubController::class, 'index'])->name('index');
Route::prefix('posts')->name('posts.')->group(function (): void {
    Route::get('/', [CmsPostLibraryController::class, 'index'])->name('index');
    Route::get('/create', [CmsPostLibraryController::class, 'create'])->name('create');
    Route::post('/', [CmsPostLibraryController::class, 'store'])->name('store');
    Route::get('/{post}', [CmsPostLibraryController::class, 'show'])->name('show');
    Route::get('/{post}/edit', [CmsPostLibraryController::class, 'edit'])->name('edit');
    Route::put('/{post}', [CmsPostLibraryController::class, 'update'])->name('update');
    Route::delete('/{post}', [CmsPostLibraryController::class, 'destroy'])->name('destroy');
});
Route::prefix('media')->name('media.')->group(function (): void {
    Route::get('/', [CmsMediaLibraryController::class, 'index'])->name('index');
    Route::get('/upload', [CmsMediaLibraryController::class, 'create'])->name('create');
    Route::post('/', [CmsMediaLibraryController::class, 'store'])->name('store');
    Route::post('/upload-inline', [CmsMediaLibraryController::class, 'uploadInline'])->name('upload-inline');
    Route::get('/{media}', [CmsMediaLibraryController::class, 'show'])->name('show');
    Route::get('/{media}/edit', [CmsMediaLibraryController::class, 'edit'])->name('edit');
    Route::put('/{media}', [CmsMediaLibraryController::class, 'update'])->name('update');
    Route::delete('/{media}', [CmsMediaLibraryController::class, 'destroy'])->name('destroy');
});
Route::prefix('menus')->name('menus.')->group(function (): void {
    Route::get('/', [CmsMenuLibraryController::class, 'index'])->name('index');
    Route::get('/create', [CmsMenuLibraryController::class, 'create'])->name('create');
    Route::post('/', [CmsMenuLibraryController::class, 'store'])->name('store');
    Route::get('/{menu}/edit', [CmsMenuLibraryController::class, 'edit'])->name('edit');
    Route::put('/{menu}', [CmsMenuLibraryController::class, 'update'])->name('update');
    Route::delete('/{menu}', [CmsMenuLibraryController::class, 'destroy'])->name('destroy');
});
Route::prefix('categories')->name('categories.')->group(function (): void {
    Route::get('/', [CmsCategoryController::class, 'index'])->name('index');
    Route::get('/create', [CmsCategoryController::class, 'create'])->name('create');
    Route::post('/', [CmsCategoryController::class, 'store'])->name('store');
    Route::get('/{category}/edit', [CmsCategoryController::class, 'edit'])->name('edit');
    Route::put('/{category}', [CmsCategoryController::class, 'update'])->name('update');
    Route::delete('/{category}', [CmsCategoryController::class, 'destroy'])->name('destroy');
});
Route::prefix('tags')->name('tags.')->group(function (): void {
    Route::get('/', [CmsTagController::class, 'index'])->name('index');
    Route::get('/create', [CmsTagController::class, 'create'])->name('create');
    Route::post('/', [CmsTagController::class, 'store'])->name('store');
    Route::get('/{tag}/edit', [CmsTagController::class, 'edit'])->name('edit');
    Route::put('/{tag}', [CmsTagController::class, 'update'])->name('update');
    Route::delete('/{tag}', [CmsTagController::class, 'destroy'])->name('destroy');
});
Route::prefix('settings')->name('settings.')->group(function (): void {
    Route::get('/', [CmsSettingsController::class, 'index'])->name('index');
    Route::put('/', [CmsSettingsController::class, 'update'])->name('update');
});
