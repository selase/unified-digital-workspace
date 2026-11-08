<?php

declare(strict_types=1);

use App\Modules\HrmsCore\Http\Controllers\Web\HrmsPublicCareersController;
use Illuminate\Support\Facades\Route;

Route::get('/careers', [HrmsPublicCareersController::class, 'index'])->name('careers.index');
Route::get('/careers/{slug}', [HrmsPublicCareersController::class, 'show'])->name('careers.show');
Route::post('/careers/{slug}/apply', [HrmsPublicCareersController::class, 'apply'])->middleware('throttle:5,10')->name('careers.apply');
