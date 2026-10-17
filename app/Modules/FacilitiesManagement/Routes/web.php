<?php

declare(strict_types=1);

use App\Modules\FacilitiesManagement\Http\Controllers\Web\FacilitiesManagementWebController;
use Illuminate\Support\Facades\Route;

Route::get('/', [FacilitiesManagementWebController::class, 'index'])->name('index');
Route::post('/buildings', [FacilitiesManagementWebController::class, 'storeBuilding'])->name('buildings.store');
Route::post('/assets', [FacilitiesManagementWebController::class, 'storeAsset'])->name('assets.store');
Route::post('/assets/{asset}/schedules', [FacilitiesManagementWebController::class, 'storeSchedule'])->name('assets.schedules.store');
Route::post('/work-orders/institutional', [FacilitiesManagementWebController::class, 'storeInstitutionalWorkOrder'])->name('work-orders.institutional.store');
Route::post('/work-orders/occupant', [FacilitiesManagementWebController::class, 'storeOccupantWorkOrder'])->name('work-orders.occupant.store');
Route::post('/work-orders/{workOrder}/complete', [FacilitiesManagementWebController::class, 'completeWorkOrder'])->name('work-orders.complete');
