<?php

declare(strict_types=1);

use App\Modules\Beneficiaries\Http\Controllers\BeneficiaryController;
use Illuminate\Support\Facades\Route;

Route::get('/', [BeneficiaryController::class, 'overview'])->name('index');

Route::get('/register', [BeneficiaryController::class, 'index'])->name('beneficiaries.index');
Route::post('/register', [BeneficiaryController::class, 'store'])->name('beneficiaries.store');
Route::get('/register/{beneficiary}', [BeneficiaryController::class, 'show'])->name('beneficiaries.show');
Route::put('/register/{beneficiary}', [BeneficiaryController::class, 'update'])->name('beneficiaries.update');
Route::post('/register/{beneficiary}/consent', [BeneficiaryController::class, 'consent'])->name('beneficiaries.consent');
Route::post('/register/{beneficiary}/withdraw-consent', [BeneficiaryController::class, 'withdrawConsent'])->name('beneficiaries.withdraw-consent');
Route::post('/register/{beneficiary}/exit', [BeneficiaryController::class, 'exit'])->name('beneficiaries.exit');
Route::post('/register/{beneficiary}/anonymise', [BeneficiaryController::class, 'anonymise'])->name('beneficiaries.anonymise');
Route::post('/register/{beneficiary}/cases', [BeneficiaryController::class, 'openCase'])->name('beneficiaries.cases.store');
Route::post('/register/{beneficiary}/services', [BeneficiaryController::class, 'recordService'])->name('beneficiaries.services.store');
Route::post('/register/{beneficiary}/documents', [BeneficiaryController::class, 'addDocument'])->name('beneficiaries.documents.store');
Route::get('/documents/{document}', [BeneficiaryController::class, 'downloadDocument'])->name('beneficiaries.documents.download');
Route::delete('/documents/{document}', [BeneficiaryController::class, 'removeDocument'])->name('beneficiaries.documents.destroy');
Route::post('/register/{beneficiary}/referrals', [BeneficiaryController::class, 'refer'])->name('beneficiaries.referrals.store');
Route::post('/referrals/{referral}', [BeneficiaryController::class, 'updateReferral'])->name('beneficiaries.referrals.update');
Route::post('/cases/{case}/close', [BeneficiaryController::class, 'closeCase'])->name('beneficiaries.cases.close');
Route::post('/cases/{case}/notes', [BeneficiaryController::class, 'addNote'])->name('beneficiaries.cases.notes');
Route::get('/access-log', [BeneficiaryController::class, 'auditLog'])->name('audit');
