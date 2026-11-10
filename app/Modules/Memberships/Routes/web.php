<?php

declare(strict_types=1);

use App\Modules\Memberships\Http\Controllers\MemberController;
use App\Modules\Memberships\Http\Controllers\TypeController;
use Illuminate\Support\Facades\Route;

Route::get('/', [MemberController::class, 'overview'])->name('index');

Route::get('/members', [MemberController::class, 'index'])->name('members.index');
Route::post('/members', [MemberController::class, 'store'])->name('members.store');
Route::get('/members/{member}', [MemberController::class, 'show'])->name('members.show');
Route::put('/members/{member}', [MemberController::class, 'update'])->name('members.update');
Route::post('/members/{member}/bill', [MemberController::class, 'bill'])->name('members.bill');
Route::post('/members/{member}/complimentary', [MemberController::class, 'complimentary'])->name('members.complimentary');
Route::post('/members/{member}/cancel', [MemberController::class, 'cancel'])->name('members.cancel');
Route::post('/members/{member}/reinstate', [MemberController::class, 'reinstate'])->name('members.reinstate');

Route::get('/types', [TypeController::class, 'index'])->name('types.index');
Route::post('/types', [TypeController::class, 'store'])->name('types.store');
Route::put('/types/{type}', [TypeController::class, 'update'])->name('types.update');
