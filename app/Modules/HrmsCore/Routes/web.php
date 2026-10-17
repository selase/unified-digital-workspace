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
Route::post('/departments/directorates', [HrmsDepartmentDirectoryController::class, 'storeDirectorate'])->name('departments.directorates.store');
Route::post('/departments', [HrmsDepartmentDirectoryController::class, 'storeDepartment'])->name('departments.store');
Route::post('/departments/units', [HrmsDepartmentDirectoryController::class, 'storeUnit'])->name('departments.units.store');
Route::put('/departments/leadership', [HrmsDepartmentDirectoryController::class, 'updateLeadership'])->name('departments.leadership.update');
Route::post('/departments/rbac-assign', [HrmsDepartmentDirectoryController::class, 'assignRbac'])->name('departments.rbac.assign');
Route::delete('/departments/rbac-revoke', [HrmsDepartmentDirectoryController::class, 'revokeRbac'])->name('departments.rbac.revoke');

Route::prefix('leave-requests')->name('leave-requests.')->group(function (): void {
    Route::get('/', [HrmsLeaveRequestController::class, 'index'])->name('index');
    Route::get('/create', [HrmsLeaveRequestController::class, 'create'])->name('create');
    Route::post('/', [HrmsLeaveRequestController::class, 'store'])->name('store');
    Route::post('/{leaveRequest}/approve', [HrmsLeaveRequestController::class, 'approve'])->name('approve');
    Route::post('/{leaveRequest}/reject', [HrmsLeaveRequestController::class, 'reject'])->name('reject');
});

Route::get('/recruitment', [HrmsRecruitmentController::class, 'index'])->name('recruitment.index');
