<?php

declare(strict_types=1);

use App\Modules\Forums\Http\Controllers\Web\ForumChannelDirectoryController;
use App\Modules\Forums\Http\Controllers\Web\ForumHubController;
use App\Modules\Forums\Http\Controllers\Web\ForumMessageCenterController;
use App\Modules\Forums\Http\Controllers\Web\ForumModerationQueueController;
use App\Modules\Forums\Http\Controllers\Web\ForumThreadWebController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/forums/hub');

Route::get('/hub', [ForumHubController::class, 'index'])->name('hub');

Route::prefix('channels')->name('channels.')->group(function (): void {
    Route::get('/', [ForumChannelDirectoryController::class, 'index'])->name('index');
    Route::get('/create', [ForumChannelDirectoryController::class, 'create'])->name('create');
    Route::post('/', [ForumChannelDirectoryController::class, 'store'])->name('store');
    Route::get('/{channel}', [ForumChannelDirectoryController::class, 'show'])->name('show');
});

Route::prefix('threads')->name('threads.')->group(function (): void {
    Route::get('/', [ForumThreadWebController::class, 'index'])->name('index');
    Route::get('/create', [ForumThreadWebController::class, 'create'])->name('create');
    Route::post('/', [ForumThreadWebController::class, 'store'])->name('store');
    Route::get('/{thread}', [ForumThreadWebController::class, 'show'])->name('show');
    Route::post('/{thread}/reply', [ForumThreadWebController::class, 'reply'])->name('reply');
    Route::post('/{thread}/lock', [ForumThreadWebController::class, 'lock'])->name('lock');
    Route::post('/{thread}/pin', [ForumThreadWebController::class, 'pin'])->name('pin');
    Route::post('/{thread}/flag', [ForumThreadWebController::class, 'flag'])->name('flag');
});

Route::prefix('messages')->name('messages.')->group(function (): void {
    Route::get('/', [ForumMessageCenterController::class, 'index'])->name('index');
    Route::get('/create', [ForumMessageCenterController::class, 'create'])->name('create');
    Route::post('/', [ForumMessageCenterController::class, 'store'])->name('store');
    Route::get('/{message}', [ForumMessageCenterController::class, 'show'])->name('show');
});

Route::get('/moderation', [ForumModerationQueueController::class, 'index'])->name('moderation.index');
