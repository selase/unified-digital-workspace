<?php

declare(strict_types=1);

use App\Modules\Volunteers\Http\Controllers\HoursController;
use App\Modules\Volunteers\Http\Controllers\OpportunityController;
use App\Modules\Volunteers\Http\Controllers\VolunteerController;
use Illuminate\Support\Facades\Route;

Route::get('/', [VolunteerController::class, 'overview'])->name('index');

Route::get('/people', [VolunteerController::class, 'index'])->name('volunteers.index');
Route::post('/people', [VolunteerController::class, 'store'])->name('volunteers.store');
Route::get('/people/{volunteer}', [VolunteerController::class, 'show'])->name('volunteers.show');
Route::put('/people/{volunteer}', [VolunteerController::class, 'update'])->name('volunteers.update');
Route::post('/people/{volunteer}/status', [VolunteerController::class, 'status'])->name('volunteers.status');
Route::post('/people/{volunteer}/signup', [VolunteerController::class, 'signup'])->name('volunteers.signup');

Route::get('/opportunities', [OpportunityController::class, 'index'])->name('opportunities.index');
Route::post('/opportunities', [OpportunityController::class, 'store'])->name('opportunities.store');
Route::post('/opportunities/{opportunity}/toggle', [OpportunityController::class, 'toggle'])->name('opportunities.toggle');

Route::get('/hours', [HoursController::class, 'index'])->name('hours.index');
Route::post('/hours', [HoursController::class, 'store'])->name('hours.store');
Route::post('/hours/{hour}/review', [HoursController::class, 'review'])->name('hours.review');
