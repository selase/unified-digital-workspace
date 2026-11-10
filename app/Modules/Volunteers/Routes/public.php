<?php

declare(strict_types=1);

use App\Modules\Volunteers\Http\Controllers\PublicVolunteerController;
use App\Modules\Volunteers\Http\Controllers\VolunteerPortalController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:10,1'])->group(function (): void {
    Route::get('/volunteer/{tenant}', [PublicVolunteerController::class, 'show'])->name('volunteers.public.signup');
    Route::post('/volunteer/{tenant}', [PublicVolunteerController::class, 'store'])->name('volunteers.public.store');
});

Route::middleware(['throttle:20,1'])->prefix('/volunteer/{tenant}/portal')->group(function (): void {
    Route::get('/', [VolunteerPortalController::class, 'login'])->name('volunteers.portal.login');
    Route::post('/', [VolunteerPortalController::class, 'sendLink'])->name('volunteers.portal.send');
    Route::get('/enter/{volunteer}', [VolunteerPortalController::class, 'enter'])->middleware('signed')->name('volunteers.portal.enter');
    Route::get('/me', [VolunteerPortalController::class, 'me'])->name('volunteers.portal.me');
    Route::post('/hours', [VolunteerPortalController::class, 'logHours'])->name('volunteers.portal.hours');
    Route::post('/signup/{opportunity}', [VolunteerPortalController::class, 'signUp'])->name('volunteers.portal.signup');
    Route::get('/certificate', [VolunteerPortalController::class, 'certificate'])->name('volunteers.portal.certificate');
    Route::post('/logout', [VolunteerPortalController::class, 'logout'])->name('volunteers.portal.logout');
});
