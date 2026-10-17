<?php

declare(strict_types=1);

use App\Modules\VendorMarketplace\Http\Controllers\Web\VendorMarketplaceWebController;
use Illuminate\Support\Facades\Route;

Route::get('/', [VendorMarketplaceWebController::class, 'index'])->name('index');
Route::post('/rate-cards', [VendorMarketplaceWebController::class, 'upsertRateCard'])->name('rate-cards.upsert');
Route::post('/vendors', [VendorMarketplaceWebController::class, 'storeVendor'])->name('vendors.store');
Route::post('/jobs', [VendorMarketplaceWebController::class, 'storeJob'])->name('jobs.store');
Route::post('/jobs/{job}/proformas', [VendorMarketplaceWebController::class, 'storeProforma'])->name('proformas.store');
Route::post('/proformas/{proforma}/fund-escrow', [VendorMarketplaceWebController::class, 'fundEscrow'])->name('escrows.fund');
Route::post('/escrows/{escrow}/release', [VendorMarketplaceWebController::class, 'releaseEscrow'])->name('escrows.release');
Route::post('/escrows/{escrow}/settle-payout', [VendorMarketplaceWebController::class, 'settlePayout'])->name('escrows.settle-payout');
Route::post('/escrows/{escrow}/disputes', [VendorMarketplaceWebController::class, 'openDispute'])->name('escrows.disputes.open');
Route::post('/disputes/{dispute}/resolve', [VendorMarketplaceWebController::class, 'resolveDispute'])->name('escrows.disputes.resolve');
