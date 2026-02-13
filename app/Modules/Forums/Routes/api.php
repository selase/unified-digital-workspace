<?php

declare(strict_types=1);

use App\Modules\Forums\Http\Controllers\Api\V1\ForumChannelController;
use App\Modules\Forums\Http\Controllers\Api\V1\ForumMessageController;
use App\Modules\Forums\Http\Controllers\Api\V1\ForumPostController;
use App\Modules\Forums\Http\Controllers\Api\V1\ForumThreadController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'module' => 'forums',
        'version' => config('modules.forums.version', '1.0.0'),
        'status' => 'active',
    ]);
})->name('index');

Route::prefix('v1')->name('v1.')->group(function (): void {
    Route::get('moderation/flags', [ForumThreadController::class, 'flaggedQueue'])->name('moderation.flags');
    Route::get('moderation/logs', [ForumThreadController::class, 'moderationLogs'])->name('moderation.logs');

    Route::get('channels', [ForumChannelController::class, 'index'])->name('channels.index');
    Route::post('channels', [ForumChannelController::class, 'store'])->name('channels.store');
    Route::put('channels/{channel}', [ForumChannelController::class, 'update'])->name('channels.update');
    Route::delete('channels/{channel}', [ForumChannelController::class, 'destroy'])->name('channels.destroy');

    Route::post('channels/{channel}/threads', [ForumThreadController::class, 'store'])->name('threads.store');
    Route::get('threads/{thread}', [ForumThreadController::class, 'show'])->name('threads.show');
    Route::post('threads/{thread}/posts', [ForumPostController::class, 'store'])->name('posts.store');
    Route::post('threads/{thread}/moderate', [ForumThreadController::class, 'moderate'])->name('threads.moderate');

    Route::post('posts/{post}/reply', [ForumPostController::class, 'reply'])->name('posts.reply');
    Route::post('posts/{post}/react', [ForumPostController::class, 'react'])->name('posts.react');
    Route::delete('posts/{post}/react', [ForumPostController::class, 'unreact'])->name('posts.unreact');
    Route::post('posts/{post}/mark-best', [ForumPostController::class, 'markBest'])->name('posts.mark-best');

    Route::get('messages', [ForumMessageController::class, 'index'])->name('messages.index');
    Route::post('messages', [ForumMessageController::class, 'store'])->name('messages.store');
    Route::post('messages/{message}/read', [ForumMessageController::class, 'read'])->name('messages.read');
});
