<?php

declare(strict_types=1);

use App\Modules\Forms\Http\Controllers\Web\FormsWebController;
use Illuminate\Support\Facades\Route;

Route::get('/', [FormsWebController::class, 'index'])->name('index');
Route::get('/create', [FormsWebController::class, 'create'])->name('create');
Route::post('/', [FormsWebController::class, 'store'])->name('store');
Route::get('/f/{slug}', [FormsWebController::class, 'publicShow'])->name('public.show');
Route::post('/f/{slug}/submit', [FormsWebController::class, 'publicSubmit'])->name('public.submit');

Route::get('/{form}', [FormsWebController::class, 'show'])->name('show');
Route::post('/{form}/publish', [FormsWebController::class, 'publish'])->name('publish');
Route::post('/{form}/close', [FormsWebController::class, 'close'])->name('close');
Route::post('/{form}/submissions/{submission}/review', [FormsWebController::class, 'reviewSubmission'])->name('submissions.review');
