<?php

declare(strict_types=1);

use App\Modules\Governance\Http\Controllers\ActionController;
use App\Modules\Governance\Http\Controllers\MeetingController;
use App\Modules\Governance\Http\Controllers\MemberController;
use Illuminate\Support\Facades\Route;

Route::get('/', [MeetingController::class, 'overview'])->name('index');

Route::get('/meetings', [MeetingController::class, 'index'])->name('meetings.index');
Route::post('/meetings', [MeetingController::class, 'store'])->name('meetings.store');
Route::get('/meetings/{meeting}', [MeetingController::class, 'show'])->name('meetings.show');
Route::get('/meetings/{meeting}/pack', [MeetingController::class, 'pack'])->name('meetings.pack');
Route::post('/meetings/{meeting}/agenda', [MeetingController::class, 'agenda'])->name('meetings.agenda');
Route::post('/meetings/{meeting}/attendance', [MeetingController::class, 'attendance'])->name('meetings.attendance');
Route::post('/meetings/{meeting}/hold', [MeetingController::class, 'hold'])->name('meetings.hold');
Route::post('/meetings/{meeting}/cancel', [MeetingController::class, 'cancel'])->name('meetings.cancel');
Route::post('/meetings/{meeting}/minutes', [MeetingController::class, 'minutes'])->name('meetings.minutes');
Route::post('/meetings/{meeting}/approve-minutes', [MeetingController::class, 'approveMinutes'])->name('meetings.approve-minutes');
Route::post('/meetings/{meeting}/resolutions', [MeetingController::class, 'resolution'])->name('meetings.resolutions.store');

Route::get('/members', [MemberController::class, 'index'])->name('members.index');
Route::post('/members', [MemberController::class, 'store'])->name('members.store');
Route::post('/members/{member}/status', [MemberController::class, 'status'])->name('members.status');

Route::get('/actions', [ActionController::class, 'index'])->name('actions.index');
Route::post('/actions', [ActionController::class, 'store'])->name('actions.store');
Route::post('/actions/{action}/toggle', [ActionController::class, 'toggle'])->name('actions.toggle');
