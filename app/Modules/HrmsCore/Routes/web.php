<?php

declare(strict_types=1);

use App\Modules\HrmsCore\Http\Controllers\Web\HrmsAppraisalWebController;
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

Route::prefix('holidays')->name('holidays.')->group(function (): void {
    Route::get('/', [App\Modules\HrmsCore\Http\Controllers\Web\HrmsHolidayWebController::class, 'index'])->name('index');
    Route::post('/', [App\Modules\HrmsCore\Http\Controllers\Web\HrmsHolidayWebController::class, 'store'])->name('store');
    Route::put('/{holiday}', [App\Modules\HrmsCore\Http\Controllers\Web\HrmsHolidayWebController::class, 'update'])->name('update');
    Route::delete('/{holiday}', [App\Modules\HrmsCore\Http\Controllers\Web\HrmsHolidayWebController::class, 'destroy'])->name('destroy');
});

Route::prefix('recruitment')->name('recruitment.')->group(function (): void {
    Route::get('/', [HrmsRecruitmentController::class, 'index'])->name('index');

    Route::post('/requisitions', [HrmsRecruitmentController::class, 'storeRequisition'])->name('requisitions.store');
    Route::put('/requisitions/{requisition}', [HrmsRecruitmentController::class, 'updateRequisition'])->name('requisitions.update');
    Route::post('/requisitions/{requisition}/status', [HrmsRecruitmentController::class, 'transitionRequisitionStatus'])->name('requisitions.status');
    Route::delete('/requisitions/{requisition}', [HrmsRecruitmentController::class, 'destroyRequisition'])->name('requisitions.destroy');

    Route::post('/postings', [HrmsRecruitmentController::class, 'storePosting'])->name('postings.store');
    Route::get('/postings/{posting}', [HrmsRecruitmentController::class, 'showPosting'])->name('postings.show');
    Route::put('/postings/{posting}', [HrmsRecruitmentController::class, 'updatePosting'])->name('postings.update');
    Route::post('/postings/{posting}/toggle-status', [HrmsRecruitmentController::class, 'togglePostingStatus'])->name('postings.toggle-status');
    Route::delete('/postings/{posting}', [HrmsRecruitmentController::class, 'destroyPosting'])->name('postings.destroy');

    Route::post('/postings/{posting}/applications', [HrmsRecruitmentController::class, 'storeApplication'])->name('applications.store');
    Route::put('/applications/{application}/status', [HrmsRecruitmentController::class, 'updateApplicationStatus'])->name('applications.status');
    Route::delete('/applications/{application}', [HrmsRecruitmentController::class, 'destroyApplication'])->name('applications.destroy');

    Route::post('/applications/{application}/interviews', [HrmsRecruitmentController::class, 'storeInterview'])->name('interviews.store');
    Route::put('/interviews/{interview}', [HrmsRecruitmentController::class, 'updateInterview'])->name('interviews.update');
    Route::delete('/interviews/{interview}', [HrmsRecruitmentController::class, 'destroyInterview'])->name('interviews.destroy');

    Route::post('/applications/{application}/offers', [HrmsRecruitmentController::class, 'storeOffer'])->name('offers.store');
    Route::post('/offers/{offer}/send', [HrmsRecruitmentController::class, 'sendOffer'])->name('offers.send');
    Route::post('/offers/{offer}/decision', [HrmsRecruitmentController::class, 'recordOfferDecision'])->name('offers.decision');
    Route::delete('/offers/{offer}', [HrmsRecruitmentController::class, 'destroyOffer'])->name('offers.destroy');
});

Route::prefix('appraisals')->name('appraisals.')->group(function (): void {
    Route::get('/', [HrmsAppraisalWebController::class, 'index'])->name('index');
    Route::post('/', [HrmsAppraisalWebController::class, 'storeAppraisal'])->name('store');
    Route::get('/{appraisal}', [HrmsAppraisalWebController::class, 'show'])->name('show');
    Route::post('/{appraisal}/status', [HrmsAppraisalWebController::class, 'transitionStatus'])->name('status');
    Route::delete('/{appraisal}', [HrmsAppraisalWebController::class, 'destroyAppraisal'])->name('destroy');

    Route::post('/periods', [HrmsAppraisalWebController::class, 'storePeriod'])->name('periods.store');
    Route::put('/periods/{period}', [HrmsAppraisalWebController::class, 'updatePeriod'])->name('periods.update');

    Route::post('/templates', [HrmsAppraisalWebController::class, 'storeTemplate'])->name('templates.store');

    Route::post('/rating-scales', [HrmsAppraisalWebController::class, 'storeRatingScale'])->name('rating-scales.store');
    Route::delete('/rating-scales/{ratingScale}', [HrmsAppraisalWebController::class, 'destroyRatingScale'])->name('rating-scales.destroy');

    Route::post('/{appraisal}/competencies', [HrmsAppraisalWebController::class, 'storeCompetency'])->name('competencies.store');
    Route::put('/{appraisal}/competencies/{competency}', [HrmsAppraisalWebController::class, 'updateCompetency'])->name('competencies.update');
    Route::delete('/{appraisal}/competencies/{competency}', [HrmsAppraisalWebController::class, 'destroyCompetency'])->name('competencies.destroy');

    Route::post('/{appraisal}/goals', [HrmsAppraisalWebController::class, 'storeGoal'])->name('goals.store');
    Route::put('/{appraisal}/goals/{goal}', [HrmsAppraisalWebController::class, 'updateGoal'])->name('goals.update');
    Route::delete('/{appraisal}/goals/{goal}', [HrmsAppraisalWebController::class, 'destroyGoal'])->name('goals.destroy');

    Route::post('/{appraisal}/reviews', [HrmsAppraisalWebController::class, 'storeReview'])->name('reviews.store');

    Route::post('/{appraisal}/comments', [HrmsAppraisalWebController::class, 'storeComment'])->name('comments.store');
    Route::delete('/{appraisal}/comments/{comment}', [HrmsAppraisalWebController::class, 'destroyComment'])->name('comments.destroy');

    Route::post('/{appraisal}/recommendations', [HrmsAppraisalWebController::class, 'storeRecommendation'])->name('recommendations.store');
});
