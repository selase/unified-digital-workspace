<?php

declare(strict_types=1);

use App\Modules\Volunteers\Http\Controllers\PublicVolunteerController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:10,1'])->group(function (): void {
    Route::get('/volunteer/{tenant}', [PublicVolunteerController::class, 'show'])->name('volunteers.public.signup');
    Route::post('/volunteer/{tenant}', [PublicVolunteerController::class, 'store'])->name('volunteers.public.store');
});
