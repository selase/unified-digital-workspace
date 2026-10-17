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
    Route::prefix('{employee}/dossier')->name('dossier.')->group(function (): void {
        Route::put('/bank-details', [App\Modules\HrmsCore\Http\Controllers\Web\HrmsEmployeeDossierWebController::class, 'updateBankDetails'])->name('bank-details.update');
        Route::put('/next-of-kin', [App\Modules\HrmsCore\Http\Controllers\Web\HrmsEmployeeDossierWebController::class, 'updateNextOfKin'])->name('next-of-kin.update');
        Route::put('/parents', [App\Modules\HrmsCore\Http\Controllers\Web\HrmsEmployeeDossierWebController::class, 'updateParents'])->name('parents.update');
        Route::post('/children', [App\Modules\HrmsCore\Http\Controllers\Web\HrmsEmployeeDossierWebController::class, 'storeChild'])->name('children.store');
        Route::delete('/children/{child}', [App\Modules\HrmsCore\Http\Controllers\Web\HrmsEmployeeDossierWebController::class, 'destroyChild'])->name('children.destroy');
        Route::post('/emergency-contacts', [App\Modules\HrmsCore\Http\Controllers\Web\HrmsEmployeeDossierWebController::class, 'storeEmergencyContact'])->name('emergency-contacts.store');
        Route::put('/emergency-contacts/{contact}', [App\Modules\HrmsCore\Http\Controllers\Web\HrmsEmployeeDossierWebController::class, 'updateEmergencyContact'])->name('emergency-contacts.update');
        Route::delete('/emergency-contacts/{contact}', [App\Modules\HrmsCore\Http\Controllers\Web\HrmsEmployeeDossierWebController::class, 'destroyEmergencyContact'])->name('emergency-contacts.destroy');
        Route::post('/education', [App\Modules\HrmsCore\Http\Controllers\Web\HrmsEmployeeDossierWebController::class, 'storeEducation'])->name('education.store');
        Route::put('/education/{education}', [App\Modules\HrmsCore\Http\Controllers\Web\HrmsEmployeeDossierWebController::class, 'updateEducation'])->name('education.update');
        Route::delete('/education/{education}', [App\Modules\HrmsCore\Http\Controllers\Web\HrmsEmployeeDossierWebController::class, 'destroyEducation'])->name('education.destroy');
        Route::post('/qualifications', [App\Modules\HrmsCore\Http\Controllers\Web\HrmsEmployeeDossierWebController::class, 'storeQualification'])->name('qualifications.store');
        Route::put('/qualifications/{qualification}', [App\Modules\HrmsCore\Http\Controllers\Web\HrmsEmployeeDossierWebController::class, 'updateQualification'])->name('qualifications.update');
        Route::delete('/qualifications/{qualification}', [App\Modules\HrmsCore\Http\Controllers\Web\HrmsEmployeeDossierWebController::class, 'destroyQualification'])->name('qualifications.destroy');
        Route::post('/work-experiences', [App\Modules\HrmsCore\Http\Controllers\Web\HrmsEmployeeDossierWebController::class, 'storeWorkExperience'])->name('work-experiences.store');
        Route::put('/work-experiences/{experience}', [App\Modules\HrmsCore\Http\Controllers\Web\HrmsEmployeeDossierWebController::class, 'updateWorkExperience'])->name('work-experiences.update');
        Route::delete('/work-experiences/{experience}', [App\Modules\HrmsCore\Http\Controllers\Web\HrmsEmployeeDossierWebController::class, 'destroyWorkExperience'])->name('work-experiences.destroy');
        Route::post('/employment-statuses', [App\Modules\HrmsCore\Http\Controllers\Web\HrmsEmployeeDossierWebController::class, 'storeEmploymentStatus'])->name('employment-statuses.store');
        Route::delete('/employment-statuses/{status}', [App\Modules\HrmsCore\Http\Controllers\Web\HrmsEmployeeDossierWebController::class, 'destroyEmploymentStatus'])->name('employment-statuses.destroy');
    });
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
