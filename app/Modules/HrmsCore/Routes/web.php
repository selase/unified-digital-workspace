<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| HRMS Core Web Routes
|--------------------------------------------------------------------------
|
| Routes are automatically prefixed with 'hrms-core' and use the
| 'hrms-core.' route name prefix.
|
| Middleware applied: web, auth, module:hrms-core
|
*/

Route::get('/', function () {
    return response()->json([
        'module' => 'hrms-core',
        'message' => 'HRMS Core module is active',
    ]);
})->name('index');

// Organization routes will be added here
// Route::prefix('organization')->name('organization.')->group(function () {
//     Route::resource('departments', DepartmentController::class);
//     Route::resource('directorates', DirectorateController::class);
//     Route::resource('units', UnitController::class);
//     Route::resource('centers', CenterController::class);
//     Route::resource('grades', GradeController::class);
// });

// Employee routes will be added here
// Route::resource('employees', EmployeeController::class);

// Leave routes will be added here
// Route::prefix('leave')->name('leave.')->group(function () {
//     Route::resource('annual', AnnualLeaveController::class);
//     Route::resource('other', OtherLeaveController::class);
//     Route::resource('categories', LeaveCategoryController::class);
//     Route::resource('holidays', HolidayController::class);
// });
