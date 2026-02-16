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
Route::get('/employees', [HrmsEmployeeDirectoryController::class, 'index'])->name('employees.index');
Route::get('/departments', [HrmsDepartmentDirectoryController::class, 'index'])->name('departments.index');
Route::get('/leave-requests', [HrmsLeaveRequestController::class, 'index'])->name('leave-requests.index');
Route::get('/recruitment', [HrmsRecruitmentController::class, 'index'])->name('recruitment.index');
