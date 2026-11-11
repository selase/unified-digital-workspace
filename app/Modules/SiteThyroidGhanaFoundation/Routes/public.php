<?php

declare(strict_types=1);

use App\Modules\SiteThyroidGhanaFoundation\Http\Controllers\BoardPageController;
use App\Modules\SiteThyroidGhanaFoundation\Http\Controllers\DonatePageController;
use App\Modules\SiteThyroidGhanaFoundation\Http\Controllers\ImpactPageController;
use App\Modules\SiteThyroidGhanaFoundation\Http\Controllers\MembershipPageController;
use App\Modules\SiteThyroidGhanaFoundation\Http\Controllers\PartnerPageController;
use App\Modules\SiteThyroidGhanaFoundation\Http\Controllers\VolunteerPageController;
use Illuminate\Support\Facades\Route;

// Define custom public routes here.
// These routes are registered at both /site prefix (subdomain) and root (custom domain).
// They take priority over the CMS Core catch-all /{slug} route.
//
// Example:
// Route::get('/contact', [ContactController::class, 'show'])->name('contact');
// Route::post('/contact', [ContactController::class, 'submit'])->name('contact.submit');

Route::get('/membership', [MembershipPageController::class, 'show'])->name('membership.show');
Route::post('/membership', [MembershipPageController::class, 'store'])->middleware('throttle:10,1')->name('membership.store');
Route::get('/donate', [DonatePageController::class, 'show'])->name('donate.show');
Route::post('/donate', [DonatePageController::class, 'store'])->middleware('throttle:10,1')->name('donate.store');
Route::get('/volunteer', [VolunteerPageController::class, 'show'])->name('volunteer.show');
Route::post('/volunteer', [VolunteerPageController::class, 'store'])->middleware('throttle:10,1')->name('volunteer.store');
Route::get('/partner-us', [PartnerPageController::class, 'show'])->name('partner.show');
Route::get('/our-board', [BoardPageController::class, 'show'])->name('board.show');
Route::get('/our-impact', [ImpactPageController::class, 'show'])->name('impact.show');
