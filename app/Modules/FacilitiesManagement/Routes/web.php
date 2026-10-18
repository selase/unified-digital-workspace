<?php

declare(strict_types=1);

use App\Modules\FacilitiesManagement\Http\Controllers\Web\FacilitiesManagementWebController;
use Illuminate\Support\Facades\Route;

Route::get('/', [FacilitiesManagementWebController::class, 'index'])->name('index');

// Buildings
Route::post('/buildings', [FacilitiesManagementWebController::class, 'storeBuilding'])->name('buildings.store');
Route::get('/buildings/{building:uuid}', [FacilitiesManagementWebController::class, 'showBuilding'])->name('buildings.show');
Route::put('/buildings/{building:uuid}', [FacilitiesManagementWebController::class, 'updateBuilding'])->name('buildings.update');
Route::delete('/buildings/{building:uuid}', [FacilitiesManagementWebController::class, 'destroyBuilding'])->name('buildings.destroy');

// Spaces
Route::post('/buildings/{building:uuid}/spaces', [FacilitiesManagementWebController::class, 'storeSpace'])->name('buildings.spaces.store');
Route::put('/spaces/{space:uuid}', [FacilitiesManagementWebController::class, 'updateSpace'])->name('spaces.update');
Route::delete('/spaces/{space:uuid}', [FacilitiesManagementWebController::class, 'destroySpace'])->name('spaces.destroy');

// Assets
Route::post('/assets', [FacilitiesManagementWebController::class, 'storeAsset'])->name('assets.store');
Route::get('/assets/{asset:uuid}', [FacilitiesManagementWebController::class, 'showAsset'])->name('assets.show');
Route::put('/assets/{asset:uuid}', [FacilitiesManagementWebController::class, 'updateAsset'])->name('assets.update');
Route::delete('/assets/{asset:uuid}', [FacilitiesManagementWebController::class, 'destroyAsset'])->name('assets.destroy');

// Schedules
Route::post('/assets/{asset}/schedules', [FacilitiesManagementWebController::class, 'storeSchedule'])->name('assets.schedules.store');
Route::put('/schedules/{schedule:uuid}', [FacilitiesManagementWebController::class, 'updateSchedule'])->name('schedules.update');
Route::post('/schedules/{schedule:uuid}/toggle', [FacilitiesManagementWebController::class, 'toggleSchedule'])->name('schedules.toggle');
Route::delete('/schedules/{schedule:uuid}', [FacilitiesManagementWebController::class, 'destroySchedule'])->name('schedules.destroy');

// Work Orders
Route::post('/work-orders/institutional', [FacilitiesManagementWebController::class, 'storeInstitutionalWorkOrder'])->name('work-orders.institutional.store');
Route::post('/work-orders/occupant', [FacilitiesManagementWebController::class, 'storeOccupantWorkOrder'])->name('work-orders.occupant.store');
Route::get('/work-orders/{workOrder:uuid}', [FacilitiesManagementWebController::class, 'showWorkOrder'])->name('work-orders.show');
Route::put('/work-orders/{workOrder:uuid}', [FacilitiesManagementWebController::class, 'updateWorkOrder'])->name('work-orders.update');
Route::post('/work-orders/{workOrder:uuid}/assign', [FacilitiesManagementWebController::class, 'assignWorkOrder'])->name('work-orders.assign');
Route::post('/work-orders/{workOrder:uuid}/status', [FacilitiesManagementWebController::class, 'updateWorkOrderStatus'])->name('work-orders.status');
Route::post('/work-orders/{workOrder:uuid}/cancel', [FacilitiesManagementWebController::class, 'cancelWorkOrder'])->name('work-orders.cancel');
Route::post('/work-orders/{workOrder}/complete', [FacilitiesManagementWebController::class, 'completeWorkOrder'])->name('work-orders.complete');
