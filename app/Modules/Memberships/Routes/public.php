<?php

declare(strict_types=1);

use App\Modules\Memberships\Http\Controllers\PublicMembershipController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:10,1'])->group(function (): void {
    Route::get('/join/{tenant}', [PublicMembershipController::class, 'show'])->name('memberships.public.join');
    Route::post('/join/{tenant}', [PublicMembershipController::class, 'store'])->name('memberships.public.store');
});
