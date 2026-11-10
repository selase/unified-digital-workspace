<?php

declare(strict_types=1);

use App\Modules\Donations\Http\Controllers\PublicDonationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:20,1'])->group(function (): void {
    Route::get('/give/{tenant}', [PublicDonationController::class, 'show'])->name('donations.public.give');
    Route::post('/give/{tenant}', [PublicDonationController::class, 'start'])->name('donations.public.start');
    Route::get('/give/{tenant}/pledge/{pledge}', [PublicDonationController::class, 'payPledge'])->middleware('signed')->name('donations.public.pledge');
    Route::get('/give/{tenant}/thanks', [PublicDonationController::class, 'thanks'])->name('donations.public.thanks');
});
