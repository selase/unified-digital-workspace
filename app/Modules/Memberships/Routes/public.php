<?php

declare(strict_types=1);

use App\Modules\Memberships\Http\Controllers\MemberPortalController;
use App\Modules\Memberships\Http\Controllers\PublicMembershipController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:10,1'])->group(function (): void {
    Route::get('/join/{tenant}', [PublicMembershipController::class, 'show'])->name('memberships.public.join');
    Route::post('/join/{tenant}', [PublicMembershipController::class, 'store'])->name('memberships.public.store');
});

Route::middleware(['throttle:20,1'])->prefix('/join/{tenant}/portal')->group(function (): void {
    Route::get('/', [MemberPortalController::class, 'login'])->name('memberships.portal.login');
    Route::post('/', [MemberPortalController::class, 'sendLink'])->name('memberships.portal.send');
    Route::get('/enter/{member}', [MemberPortalController::class, 'enter'])->middleware('signed')->name('memberships.portal.enter');
    Route::get('/me', [MemberPortalController::class, 'me'])->name('memberships.portal.me');
    Route::post('/renew', [MemberPortalController::class, 'renew'])->name('memberships.portal.renew');
    Route::post('/logout', [MemberPortalController::class, 'logout'])->name('memberships.portal.logout');
});
