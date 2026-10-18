<?php

declare(strict_types=1);

use App\Modules\RealEstate\Http\Controllers\Web\ClientPortalWebController;
use App\Modules\RealEstate\Http\Controllers\Web\RealEstateWebController;
use Illuminate\Support\Facades\Route;

Route::get('/', [RealEstateWebController::class, 'index'])->name('index');

// Projects
Route::post('/projects', [RealEstateWebController::class, 'storeProject'])->name('projects.store');
Route::get('/projects/{project}/edit', [RealEstateWebController::class, 'editProject'])->name('projects.edit');
Route::put('/projects/{project}', [RealEstateWebController::class, 'updateProject'])->name('projects.update');
Route::delete('/projects/{project}', [RealEstateWebController::class, 'destroyProject'])->name('projects.destroy');

// Parcels
Route::post('/parcels', [RealEstateWebController::class, 'storeParcel'])->name('parcels.store');
Route::get('/parcels/{parcel}/edit', [RealEstateWebController::class, 'editParcel'])->name('parcels.edit');
Route::put('/parcels/{parcel}', [RealEstateWebController::class, 'updateParcel'])->name('parcels.update');
Route::delete('/parcels/{parcel}', [RealEstateWebController::class, 'destroyParcel'])->name('parcels.destroy');
Route::post('/parcels/{parcel}/compliance', [RealEstateWebController::class, 'updateCompliance'])->name('parcels.compliance.update');
Route::post('/parcels/{parcel}/transition', [RealEstateWebController::class, 'transitionStage'])->name('parcels.transition');
Route::post('/parcels/{parcel}/documents', [RealEstateWebController::class, 'storeDocument'])->name('documents.store');
Route::get('/documents/{document}/download', [RealEstateWebController::class, 'downloadDocument'])->name('documents.download');
Route::post('/parcels/{parcel}/allocate', [RealEstateWebController::class, 'allocatePlot'])->name('parcels.allocate');
Route::post('/parcels/{parcel}/order-vendor', [RealEstateWebController::class, 'orderExpertOrMaterial'])->name('parcels.order-vendor');

// Installments & Receipts
Route::post('/installments/{installment}/pay', [RealEstateWebController::class, 'payInstallment'])->name('installments.pay');
Route::get('/installments/{installment}/receipt', [RealEstateWebController::class, 'receipt'])->name('installments.receipt');

// Listings Admin
Route::post('/listings', [RealEstateWebController::class, 'storeListing'])->name('listings.store');
Route::get('/listings/{listing}/edit', [RealEstateWebController::class, 'editListing'])->name('listings.edit');
Route::put('/listings/{listing}', [RealEstateWebController::class, 'updateListing'])->name('listings.update');
Route::delete('/listings/{listing}', [RealEstateWebController::class, 'destroyListing'])->name('listings.destroy');

// Client Portal
Route::get('/client-portal', [ClientPortalWebController::class, 'index'])->name('portal.index');
