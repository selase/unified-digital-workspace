<?php

declare(strict_types=1);

use App\Modules\RealEstate\Http\Controllers\Web\ClientPortalWebController;
use App\Modules\RealEstate\Http\Controllers\Web\RealEstateWebController;
use Illuminate\Support\Facades\Route;

Route::get('/', [RealEstateWebController::class, 'index'])->name('index');
Route::post('/projects', [RealEstateWebController::class, 'storeProject'])->name('projects.store');
Route::post('/parcels', [RealEstateWebController::class, 'storeParcel'])->name('parcels.store');
Route::post('/parcels/{parcel}/compliance', [RealEstateWebController::class, 'updateCompliance'])->name('parcels.compliance.update');
Route::post('/parcels/{parcel}/transition', [RealEstateWebController::class, 'transitionStage'])->name('parcels.transition');
Route::post('/parcels/{parcel}/documents', [RealEstateWebController::class, 'storeDocument'])->name('documents.store');
Route::get('/documents/{document}/download', [RealEstateWebController::class, 'downloadDocument'])->name('documents.download');
Route::post('/parcels/{parcel}/allocate', [RealEstateWebController::class, 'allocatePlot'])->name('parcels.allocate');
Route::post('/installments/{installment}/pay', [RealEstateWebController::class, 'payInstallment'])->name('installments.pay');
Route::post('/parcels/{parcel}/order-vendor', [RealEstateWebController::class, 'orderExpertOrMaterial'])->name('parcels.order-vendor');

Route::get('/client-portal', [ClientPortalWebController::class, 'index'])->name('portal.index');
