<?php

declare(strict_types=1);

use App\Modules\Intranet\Http\Controllers\Web\IntranetWebController;
use Illuminate\Support\Facades\Route;

// Hub & Announcements
Route::get('/', [IntranetWebController::class, 'index'])->name('index');
Route::get('/announcements', [IntranetWebController::class, 'announcements'])->name('announcements');

// Message Boards
Route::post('/boards', [IntranetWebController::class, 'storeBoard'])->name('boards.store');
Route::get('/boards/{board}', [IntranetWebController::class, 'showBoard'])->name('boards.show');
Route::put('/boards/{board}', [IntranetWebController::class, 'updateBoard'])->name('boards.update');
Route::delete('/boards/{board}', [IntranetWebController::class, 'destroyBoard'])->name('boards.destroy');

// Board Posts, Replies & Pinning
Route::post('/boards/{board}/posts', [IntranetWebController::class, 'storeBoardPost'])->name('boards.posts.store');
Route::put('/posts/{post}', [IntranetWebController::class, 'updateBoardPost'])->name('posts.update');
Route::delete('/posts/{post}', [IntranetWebController::class, 'destroyBoardPost'])->name('posts.destroy');
Route::post('/posts/{post}/pin', [IntranetWebController::class, 'togglePinBoardPost'])->name('posts.pin');
Route::post('/posts/{post}/replies', [IntranetWebController::class, 'storePostReply'])->name('posts.replies.store');

// Institutional Events & RSVPs
Route::post('/events', [IntranetWebController::class, 'storeEvent'])->name('events.store');
Route::put('/events/{event}', [IntranetWebController::class, 'updateEvent'])->name('events.update');
Route::delete('/events/{event}', [IntranetWebController::class, 'destroyEvent'])->name('events.destroy');
Route::post('/events/{event}/rsvp', [IntranetWebController::class, 'rsvpEvent'])->name('events.rsvp');
