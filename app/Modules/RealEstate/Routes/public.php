<?php

declare(strict_types=1);

use App\Modules\RealEstate\Http\Controllers\Web\PublicRealEstateWebController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicRealEstateWebController::class, 'catalog'])->name('catalog');
Route::get('/{listing}', [PublicRealEstateWebController::class, 'show'])->name('show');
Route::post('/{listing}/inquire', [PublicRealEstateWebController::class, 'inquire'])->name('inquire');
