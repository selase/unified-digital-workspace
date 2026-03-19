<?php

declare(strict_types=1);

use App\Modules\HrmsCore\Http\Controllers\Web\HrmsDepartmentDirectoryController;
use App\Modules\HrmsCore\Http\Controllers\Web\HrmsEmployeeDirectoryController;
use App\Modules\HrmsCore\Http\Controllers\Web\HrmsHubController;
use App\Modules\HrmsCore\Http\Controllers\Web\HrmsLeaveRequestController;
use App\Modules\HrmsCore\Http\Controllers\Web\HrmsRecruitmentController;
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

Route::get('/', [HrmsHubController::class, 'index'])->name('index');
Route::prefix('employees')->name('employees.')->group(function (): void {
    Route::get('/', [HrmsEmployeeDirectoryController::class, 'index'])->name('index');
    Route::get('/create', [HrmsEmployeeDirectoryController::class, 'create'])->name('create');
    Route::post('/', [HrmsEmployeeDirectoryController::class, 'store'])->name('store');
    Route::get('/{employee}', [HrmsEmployeeDirectoryController::class, 'show'])->name('show');
    Route::get('/{employee}/edit', [HrmsEmployeeDirectoryController::class, 'edit'])->name('edit');
    Route::put('/{employee}', [HrmsEmployeeDirectoryController::class, 'update'])->name('update');
    Route::delete('/{employee}', [HrmsEmployeeDirectoryController::class, 'destroy'])->name('destroy');
});
Route::get('/departments', [HrmsDepartmentDirectoryController::class, 'index'])->name('departments.index');
Route::get('/leave-requests', [HrmsLeaveRequestController::class, 'index'])->name('leave-requests.index');
Route::get('/recruitment', [HrmsRecruitmentController::class, 'index'])->name('recruitment.index');
