# HRMS Core Module - Implementation Plan

## Overview

Port the existing HRMS codebase as a module (`hrms-core`) within the new multi-tenant system, leveraging the module infrastructure from Track 03.

## Key Architectural Decisions

### 1. Single Module with Domain Namespacing

**Decision**: Single `hrms-core` module with internal domain namespacing (not sub-modules)

**Rationale**:
- HRMS domains are tightly coupled (Employee is referenced by Leave, Promotions, Appraisals, Recruitment)
- Grade model is central to multiple domains
- Simplifies dependency management and migration ordering
- Internal namespacing (`Models/Employees/`, `Models/Leave/`) allows future splitting

### 2. Tenant ID Strategy

Uses `BelongsToTenant` trait from starterkit which:
- Adds `TenantScope` global scope
- Auto-assigns `tenant_id` from `TenantContext` on create
- All models use `landlord` connection

### 3. Table Naming Convention

All HRMS tables prefixed with `hrms_` to avoid collisions:
- `hrms_employees`, `hrms_departments`, `hrms_annual_leaves`, etc.

---

## File Structure

```
app/Modules/HrmsCore/
├── Config/
│   └── module.php
├── Database/
│   ├── Factories/
│   ├── Migrations/
│   └── Seeders/
├── Enums/
│   ├── AppraisalCycle.php
│   ├── AppraisalRating.php
│   ├── AppraisalStatus.php
│   ├── ApplicationStatus.php
│   ├── Gender.php
│   ├── GoalStatus.php
│   ├── InterviewStatus.php
│   ├── LeaveStatus.php
│   ├── MaritalStatus.php
│   ├── OfferStatus.php
│   ├── PromotionCategory.php
│   ├── PromotionStatus.php
│   ├── RecommendationType.php
│   └── RequisitionStatus.php
├── Http/
│   ├── Controllers/
│   ├── Requests/
│   └── Resources/
├── Models/
│   ├── Concerns/
│   │   └── HasHrmsUuid.php
│   ├── Appraisal/       (13 models)
│   ├── Employees/       (11 models)
│   ├── Leave/           (4 models)
│   ├── Organization/    (6 models)
│   ├── Promotion/       (1 model)
│   ├── Recruitment/     (14 models)
│   └── Salary/          (7 models)
├── Providers/
│   └── HrmsCoreServiceProvider.php
└── Routes/
    ├── web.php
    └── api.php
```

---

## Model Pattern

All HRMS models follow this pattern:

```php
<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Models\{Domain};

use App\Modules\HrmsCore\Models\Concerns\HasHrmsUuid;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

final class {ModelName} extends Model
{
    use BelongsToTenant;
    use HasHrmsUuid;

    protected $connection = 'landlord';
    protected $table = 'hrms_{table_name}';
    protected $fillable = [...];

    protected function casts(): array
    {
        return [...];
    }

    // Relationships...
    // Business methods...
    // Query scopes...
}
```

---

## Domain Models Summary

### Organization (6 models)
- Department, DepartmentType, Directorate, Unit, Center, Grade

### Employees (11 models)
- Employee, CurrentJob, EmployeeParent, Children, NextOfKin
- BankDetails, EducationalBackground, ProfessionalQualification
- PreviousWorkExperience, EmployeeEmergencyContact, EmployeeDependent

### Leave (4 models)
- LeaveCategory, LeaveRequest, LeaveBalance, Holiday

### Salary (7 models)
- SalaryLevel, SalaryStep, AllowanceType, Allowance
- EmployeeAllowance, MarketPremium, OtherAllowance

### Appraisal (13 models)
- AppraisalPeriod, AppraisalTemplate, AppraisalSection, AppraisalCriterion
- AppraisalRatingScale, Appraisal, AppraisalResponse, AppraisalGoal
- AppraisalCompetency, AppraisalReview, AppraisalComment
- AppraisalScore, AppraisalRecommendation

### Promotion (1 model)
- StaffPromotion

### Recruitment (14 models)
- JobRequisition, JobPosting, Candidate, CandidateApplication
- CandidateDocument, InterviewStage, Interview, InterviewPanel
- InterviewEvaluation, CandidateAssessment, CandidateReference
- JobOffer, OfferNegotiation, OnboardingTask

---

## Workflow Definitions

### Leave Request Workflow
```
Draft → Submitted → SupervisorReview → HrVerification → HodApproval → Approved/Rejected
```

### Appraisal Workflow
```
Draft → SelfAssessment → SupervisorReview → HodReview → HrReview → Complete/Cancelled
```

### Promotion Workflow
```
Pending → AwaitingSupervisorApproval → AwaitingHrApproval → Approved/Rejected
```

### Recruitment Workflow
```
Application: Submitted → Screening → Shortlisted → Interview → Assessment → Offer → Hired/Rejected
Offer: Draft → PendingApproval → Sent → Accepted/Rejected/Withdrawn
```

---

## Phase 8: Integration Tasks

### API Routes (`routes/api.php`)

```php
Route::prefix('v1/hrms')->middleware(['auth:sanctum', 'module:hrms-core'])->group(function () {
    // Employees
    Route::apiResource('employees', EmployeeController::class);

    // Leave
    Route::apiResource('leave-requests', LeaveRequestController::class);
    Route::post('leave-requests/{id}/approve', [LeaveRequestController::class, 'approve']);

    // Appraisals
    Route::apiResource('appraisals', AppraisalController::class);
    Route::post('appraisals/{id}/submit', [AppraisalController::class, 'submit']);

    // Recruitment
    Route::apiResource('requisitions', JobRequisitionController::class);
    Route::apiResource('candidates', CandidateController::class);
    Route::apiResource('applications', CandidateApplicationController::class);
});
```

### API Resources

Create Laravel API Resources for JSON transformation:
- `EmployeeResource`, `EmployeeCollection`
- `LeaveRequestResource`
- `AppraisalResource`
- `CandidateResource`, `ApplicationResource`

### Integration Tests

Test complete workflows:
1. Hire a candidate (requisition → posting → application → interview → offer → hire)
2. Complete leave request cycle
3. Complete appraisal cycle
4. Process promotion

---

## Verification Steps

```bash
# 1. Run all tests
php artisan test --filter=HrmsCore

# 2. Code style
vendor/bin/pint --dirty

# 3. Static analysis
vendor/bin/phpstan analyse app/Modules/HrmsCore

# 4. Verify routes
php artisan route:list --path=api/v1/hrms
```
