<?php

declare(strict_types=1);

use App\Modules\Forums\Http\Controllers\Web\ForumChannelDirectoryController;
use App\Modules\Forums\Http\Controllers\Web\ForumHubController;
use App\Modules\Forums\Http\Controllers\Web\ForumMessageCenterController;
use App\Modules\Forums\Http\Controllers\Web\ForumModerationQueueController;
use App\Modules\Forums\Http\Controllers\Web\ForumThreadQueueController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/forums/hub');

Route::get('/hub', [ForumHubController::class, 'index'])->name('hub');
Route::get('/channels', [ForumChannelDirectoryController::class, 'index'])->name('channels.index');
Route::get('/threads', [ForumThreadQueueController::class, 'index'])->name('threads.index');
Route::get('/messages', [ForumMessageCenterController::class, 'index'])->name('messages.index');
Route::get('/moderation', [ForumModerationQueueController::class, 'index'])->name('moderation.index');
