<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Modules\HrmsCore\Enums\PromotionCategory;
use App\Modules\HrmsCore\Enums\PromotionStatus;
use App\Modules\HrmsCore\Models\Employees\Employee;
use App\Modules\HrmsCore\Models\Organization\Grade;
use App\Modules\HrmsCore\Models\Promotion\StaffPromotion;
use App\Services\Tenancy\TenantContext;

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create([
        'name' => 'Promotion Test Company',
        'slug' => 'promotion-test-company',
    ]);
    app(TenantContext::class)->setTenant($this->tenant);
    $this->employee = Employee::factory()->forTenant($this->tenant->id)->create();
    $this->supervisor = Employee::factory()->forTenant($this->tenant->id)->create();
    $this->hrApprover = Employee::factory()->forTenant($this->tenant->id)->create();
    $this->grade1 = Grade::factory()->forTenant($this->tenant->id)->sortOrder(1)->create();
    $this->grade2 = Grade::factory()->forTenant($this->tenant->id)->sortOrder(2)->create();
});

describe('PromotionStatus Enum', function (): void {
    test('has all expected statuses', function (): void {
        expect(PromotionStatus::cases())->toHaveCount(5)
            ->and(PromotionStatus::Pending->value)->toBe('pending')
            ->and(PromotionStatus::AwaitingSupervisorApproval->value)->toBe('awaiting_supervisor_approval')
            ->and(PromotionStatus::AwaitingHrApproval->value)->toBe('awaiting_hr_approval')
            ->and(PromotionStatus::Approved->value)->toBe('approved')
            ->and(PromotionStatus::Rejected->value)->toBe('rejected');
    });

    test('provides labels', function (): void {
        expect(PromotionStatus::Pending->label())->toBe('Pending')
            ->and(PromotionStatus::AwaitingSupervisorApproval->label())->toBe('Awaiting Supervisor Approval')
            ->and(PromotionStatus::Approved->label())->toBe('Approved');
    });

    test('provides CSS classes', function (): void {
        expect(PromotionStatus::Pending->cssClass())->toContain('yellow')
            ->and(PromotionStatus::Approved->cssClass())->toContain('green')
            ->and(PromotionStatus::Rejected->cssClass())->toContain('red');
    });

    test('identifies final statuses', function (): void {
        expect(PromotionStatus::Pending->isFinal())->toBeFalse()
            ->and(PromotionStatus::AwaitingSupervisorApproval->isFinal())->toBeFalse()
            ->and(PromotionStatus::Approved->isFinal())->toBeTrue()
            ->and(PromotionStatus::Rejected->isFinal())->toBeTrue();
    });
});

describe('PromotionCategory Enum', function (): void {
    test('has all expected categories', function (): void {
        expect(PromotionCategory::cases())->toHaveCount(4)
            ->and(PromotionCategory::Promotion->value)->toBe('promotion')
            ->and(PromotionCategory::Regrading->value)->toBe('regrading')
            ->and(PromotionCategory::Upgrade->value)->toBe('upgrade')
            ->and(PromotionCategory::Conversion->value)->toBe('conversion');
    });

    test('provides labels', function (): void {
        expect(PromotionCategory::Promotion->label())->toBe('Promotion')
            ->and(PromotionCategory::Regrading->label())->toBe('Re-grading');
    });

    test('provides descriptions', function (): void {
        expect(PromotionCategory::Promotion->description())->toContain('Advancement')
            ->and(PromotionCategory::Upgrade->description())->toContain('salary step');
    });
});

describe('StaffPromotion Model', function (): void {
    test('can create a promotion', function (): void {
        $promotion = StaffPromotion::create([
            'employee_id' => $this->employee->id,
            'from_grade_id' => $this->grade1->id,
            'to_grade_id' => $this->grade2->id,
            'reason' => 'Excellent performance',
        ]);

        expect($promotion)->toBeInstanceOf(StaffPromotion::class)
            ->and($promotion->uuid)->not->toBeNull()
            ->and($promotion->category)->toBe(PromotionCategory::Promotion)
            ->and($promotion->status)->toBe(PromotionStatus::Pending);
    });

    test('has employee relationship', function (): void {
        $promotion = StaffPromotion::create([
            'employee_id' => $this->employee->id,
        ]);

        expect($promotion->employee)->toBeInstanceOf(Employee::class)
            ->and($promotion->employee->id)->toBe($this->employee->id);
    });

    test('has grade relationships', function (): void {
        $promotion = StaffPromotion::create([
            'employee_id' => $this->employee->id,
            'from_grade_id' => $this->grade1->id,
            'to_grade_id' => $this->grade2->id,
        ]);

        expect($promotion->fromGrade->id)->toBe($this->grade1->id)
            ->and($promotion->toGrade->id)->toBe($this->grade2->id);
    });

    test('has supervisor relationship', function (): void {
        $promotion = StaffPromotion::create([
            'employee_id' => $this->employee->id,
            'supervisor_id' => $this->supervisor->id,
        ]);

        expect($promotion->supervisor->id)->toBe($this->supervisor->id);
    });

    test('has HR approver relationship', function (): void {
        $promotion = StaffPromotion::create([
            'employee_id' => $this->employee->id,
            'hr_approver_id' => $this->hrApprover->id,
        ]);

        expect($promotion->hrApprover->id)->toBe($this->hrApprover->id);
    });

    test('casts category to enum', function (): void {
        $promotion = StaffPromotion::create([
            'employee_id' => $this->employee->id,
            'category' => 'regrading',
        ]);

        expect($promotion->category)->toBe(PromotionCategory::Regrading);
    });

    test('casts status to enum', function (): void {
        $promotion = StaffPromotion::create([
            'employee_id' => $this->employee->id,
            'status' => 'awaiting_supervisor_approval',
        ]);

        expect($promotion->status)->toBe(PromotionStatus::AwaitingSupervisorApproval);
    });

    test('casts dates correctly', function (): void {
        $promotion = StaffPromotion::create([
            'employee_id' => $this->employee->id,
            'effective_date' => '2024-07-01',
            'requested_date' => '2024-06-15',
        ]);

        expect($promotion->effective_date)->toBeInstanceOf(\DateTimeInterface::class)
            ->and($promotion->effective_date->format('Y-m-d'))->toBe('2024-07-01')
            ->and($promotion->requested_date->format('Y-m-d'))->toBe('2024-06-15');
    });

    test('casts supporting_documents to array', function (): void {
        $docs = ['/docs/file1.pdf', '/docs/file2.pdf'];
        $promotion = StaffPromotion::create([
            'employee_id' => $this->employee->id,
            'supporting_documents' => $docs,
        ]);

        expect($promotion->supporting_documents)->toBeArray()
            ->and($promotion->supporting_documents)->toBe($docs);
    });
});

describe('StaffPromotion Status Methods', function (): void {
    test('isPending returns correct value', function (): void {
        $promotion = StaffPromotion::create([
            'employee_id' => $this->employee->id,
            'status' => PromotionStatus::Pending,
        ]);

        expect($promotion->isPending())->toBeTrue()
            ->and($promotion->isAwaitingSupervisorApproval())->toBeFalse();
    });

    test('isAwaitingSupervisorApproval returns correct value', function (): void {
        $promotion = StaffPromotion::create([
            'employee_id' => $this->employee->id,
            'status' => PromotionStatus::AwaitingSupervisorApproval,
        ]);

        expect($promotion->isAwaitingSupervisorApproval())->toBeTrue()
            ->and($promotion->isPending())->toBeFalse();
    });

    test('isAwaitingHrApproval returns correct value', function (): void {
        $promotion = StaffPromotion::create([
            'employee_id' => $this->employee->id,
            'status' => PromotionStatus::AwaitingHrApproval,
        ]);

        expect($promotion->isAwaitingHrApproval())->toBeTrue();
    });

    test('isApproved returns correct value', function (): void {
        $promotion = StaffPromotion::create([
            'employee_id' => $this->employee->id,
            'status' => PromotionStatus::Approved,
        ]);

        expect($promotion->isApproved())->toBeTrue()
            ->and($promotion->isFinal())->toBeTrue();
    });

    test('isRejected returns correct value', function (): void {
        $promotion = StaffPromotion::create([
            'employee_id' => $this->employee->id,
            'status' => PromotionStatus::Rejected,
        ]);

        expect($promotion->isRejected())->toBeTrue()
            ->and($promotion->isFinal())->toBeTrue();
    });
});

describe('StaffPromotion Category Methods', function (): void {
    test('isPromotion returns correct value', function (): void {
        $promotion = StaffPromotion::create([
            'employee_id' => $this->employee->id,
            'category' => PromotionCategory::Promotion,
        ]);

        expect($promotion->isPromotion())->toBeTrue()
            ->and($promotion->isRegrading())->toBeFalse();
    });

    test('isRegrading returns correct value', function (): void {
        $promotion = StaffPromotion::create([
            'employee_id' => $this->employee->id,
            'category' => PromotionCategory::Regrading,
        ]);

        expect($promotion->isRegrading())->toBeTrue();
    });

    test('isUpgrade returns correct value', function (): void {
        $promotion = StaffPromotion::create([
            'employee_id' => $this->employee->id,
            'category' => PromotionCategory::Upgrade,
        ]);

        expect($promotion->isUpgrade())->toBeTrue();
    });

    test('isConversion returns correct value', function (): void {
        $promotion = StaffPromotion::create([
            'employee_id' => $this->employee->id,
            'category' => PromotionCategory::Conversion,
        ]);

        expect($promotion->isConversion())->toBeTrue();
    });
});

describe('StaffPromotion Workflow', function (): void {
    test('can submit for supervisor review', function (): void {
        $promotion = StaffPromotion::create([
            'employee_id' => $this->employee->id,
        ]);

        expect($promotion->submitForSupervisorReview())->toBeTrue()
            ->and($promotion->isAwaitingSupervisorApproval())->toBeTrue();
    });

    test('cannot submit for supervisor review if not pending', function (): void {
        $promotion = StaffPromotion::create([
            'employee_id' => $this->employee->id,
            'status' => PromotionStatus::Approved,
        ]);

        expect($promotion->submitForSupervisorReview())->toBeFalse()
            ->and($promotion->isApproved())->toBeTrue();
    });

    test('can approve by supervisor', function (): void {
        $promotion = StaffPromotion::create([
            'employee_id' => $this->employee->id,
            'status' => PromotionStatus::AwaitingSupervisorApproval,
        ]);

        expect($promotion->approveBySupervisor($this->supervisor->id, 'Recommended'))->toBeTrue()
            ->and($promotion->isAwaitingHrApproval())->toBeTrue()
            ->and($promotion->supervisor_id)->toBe($this->supervisor->id)
            ->and($promotion->supervisor_approved)->toBeTrue()
            ->and($promotion->supervisor_comments)->toBe('Recommended')
            ->and($promotion->supervisor_reviewed_at)->not->toBeNull();
    });

    test('cannot approve by supervisor if not awaiting supervisor', function (): void {
        $promotion = StaffPromotion::create([
            'employee_id' => $this->employee->id,
            'status' => PromotionStatus::Pending,
        ]);

        expect($promotion->approveBySupervisor($this->supervisor->id))->toBeFalse()
            ->and($promotion->isPending())->toBeTrue();
    });

    test('can reject by supervisor', function (): void {
        $promotion = StaffPromotion::create([
            'employee_id' => $this->employee->id,
            'status' => PromotionStatus::AwaitingSupervisorApproval,
        ]);

        expect($promotion->rejectBySupervisor($this->supervisor->id, 'Not qualified'))->toBeTrue()
            ->and($promotion->isRejected())->toBeTrue()
            ->and($promotion->supervisor_approved)->toBeFalse()
            ->and($promotion->rejection_reason)->toBe('Not qualified')
            ->and($promotion->completed_at)->not->toBeNull();
    });

    test('can approve by HR', function (): void {
        $promotion = StaffPromotion::create([
            'employee_id' => $this->employee->id,
            'status' => PromotionStatus::AwaitingHrApproval,
        ]);

        expect($promotion->approveByHr($this->hrApprover->id, 'All checks passed'))->toBeTrue()
            ->and($promotion->isApproved())->toBeTrue()
            ->and($promotion->hr_approver_id)->toBe($this->hrApprover->id)
            ->and($promotion->hr_approved)->toBeTrue()
            ->and($promotion->hr_comments)->toBe('All checks passed')
            ->and($promotion->completed_at)->not->toBeNull();
    });

    test('cannot approve by HR if not awaiting HR', function (): void {
        $promotion = StaffPromotion::create([
            'employee_id' => $this->employee->id,
            'status' => PromotionStatus::AwaitingSupervisorApproval,
        ]);

        expect($promotion->approveByHr($this->hrApprover->id))->toBeFalse()
            ->and($promotion->isAwaitingSupervisorApproval())->toBeTrue();
    });

    test('can reject by HR', function (): void {
        $promotion = StaffPromotion::create([
            'employee_id' => $this->employee->id,
            'status' => PromotionStatus::AwaitingHrApproval,
        ]);

        expect($promotion->rejectByHr($this->hrApprover->id, 'Budget constraints', 'No budget available'))->toBeTrue()
            ->and($promotion->isRejected())->toBeTrue()
            ->and($promotion->hr_approved)->toBeFalse()
            ->and($promotion->rejection_reason)->toBe('Budget constraints')
            ->and($promotion->hr_comments)->toBe('No budget available');
    });

    test('full approval workflow', function (): void {
        $promotion = StaffPromotion::create([
            'employee_id' => $this->employee->id,
            'from_grade_id' => $this->grade1->id,
            'to_grade_id' => $this->grade2->id,
            'reason' => 'Excellent performance',
        ]);

        // Step 1: Submit for review
        expect($promotion->submitForSupervisorReview())->toBeTrue();

        // Step 2: Supervisor approves
        expect($promotion->approveBySupervisor($this->supervisor->id, 'Highly recommended'))->toBeTrue();

        // Step 3: HR approves
        expect($promotion->approveByHr($this->hrApprover->id, 'Approved'))->toBeTrue();

        // Verify final state
        expect($promotion->isApproved())->toBeTrue()
            ->and($promotion->isFinal())->toBeTrue()
            ->and($promotion->completed_at)->not->toBeNull();
    });
});

describe('StaffPromotion canBeReviewedBy', function (): void {
    test('supervisor can review when awaiting supervisor approval', function (): void {
        $promotion = StaffPromotion::create([
            'employee_id' => $this->employee->id,
            'status' => PromotionStatus::AwaitingSupervisorApproval,
            'supervisor_id' => $this->supervisor->id,
        ]);

        expect($promotion->canBeReviewedBy($this->supervisor))->toBeTrue()
            ->and($promotion->canBeReviewedBy($this->hrApprover))->toBeFalse();
    });

    test('HR can review when awaiting HR approval', function (): void {
        $promotion = StaffPromotion::create([
            'employee_id' => $this->employee->id,
            'status' => PromotionStatus::AwaitingHrApproval,
        ]);

        expect($promotion->canBeReviewedBy($this->hrApprover))->toBeTrue();
    });

    test('no one can review when pending', function (): void {
        $promotion = StaffPromotion::create([
            'employee_id' => $this->employee->id,
            'status' => PromotionStatus::Pending,
        ]);

        expect($promotion->canBeReviewedBy($this->supervisor))->toBeFalse()
            ->and($promotion->canBeReviewedBy($this->hrApprover))->toBeFalse();
    });
});

describe('StaffPromotion Query Scopes', function (): void {
    test('withStatus scope filters by status', function (): void {
        StaffPromotion::create(['employee_id' => $this->employee->id, 'status' => PromotionStatus::Pending]);
        StaffPromotion::create(['employee_id' => $this->employee->id, 'status' => PromotionStatus::Approved]);

        expect(StaffPromotion::withStatus(PromotionStatus::Pending)->count())->toBe(1)
            ->and(StaffPromotion::withStatus(PromotionStatus::Approved)->count())->toBe(1);
    });

    test('ofCategory scope filters by category', function (): void {
        StaffPromotion::create(['employee_id' => $this->employee->id, 'category' => PromotionCategory::Promotion]);
        StaffPromotion::create(['employee_id' => $this->employee->id, 'category' => PromotionCategory::Regrading]);

        expect(StaffPromotion::ofCategory(PromotionCategory::Promotion)->count())->toBe(1)
            ->and(StaffPromotion::ofCategory(PromotionCategory::Regrading)->count())->toBe(1);
    });

    test('forEmployee scope filters by employee', function (): void {
        $employee2 = Employee::factory()->forTenant($this->tenant->id)->create();

        StaffPromotion::create(['employee_id' => $this->employee->id]);
        StaffPromotion::create(['employee_id' => $this->employee->id]);
        StaffPromotion::create(['employee_id' => $employee2->id]);

        expect(StaffPromotion::forEmployee($this->employee->id)->count())->toBe(2)
            ->and(StaffPromotion::forEmployee($employee2->id)->count())->toBe(1);
    });

    test('pending scope excludes completed and rejected', function (): void {
        StaffPromotion::create(['employee_id' => $this->employee->id, 'status' => PromotionStatus::Pending]);
        StaffPromotion::create(['employee_id' => $this->employee->id, 'status' => PromotionStatus::AwaitingSupervisorApproval]);
        StaffPromotion::create(['employee_id' => $this->employee->id, 'status' => PromotionStatus::Approved]);
        StaffPromotion::create(['employee_id' => $this->employee->id, 'status' => PromotionStatus::Rejected]);

        expect(StaffPromotion::pending()->count())->toBe(2)
            ->and(StaffPromotion::count())->toBe(4);
    });

    test('approved scope filters approved only', function (): void {
        StaffPromotion::create(['employee_id' => $this->employee->id, 'status' => PromotionStatus::Pending]);
        StaffPromotion::create(['employee_id' => $this->employee->id, 'status' => PromotionStatus::Approved]);

        expect(StaffPromotion::approved()->count())->toBe(1);
    });

    test('awaitingSupervisor scope filters correctly', function (): void {
        StaffPromotion::create(['employee_id' => $this->employee->id, 'status' => PromotionStatus::AwaitingSupervisorApproval]);
        StaffPromotion::create(['employee_id' => $this->employee->id, 'status' => PromotionStatus::AwaitingHrApproval]);

        expect(StaffPromotion::awaitingSupervisor()->count())->toBe(1);
    });

    test('awaitingHr scope filters correctly', function (): void {
        StaffPromotion::create(['employee_id' => $this->employee->id, 'status' => PromotionStatus::AwaitingSupervisorApproval]);
        StaffPromotion::create(['employee_id' => $this->employee->id, 'status' => PromotionStatus::AwaitingHrApproval]);

        expect(StaffPromotion::awaitingHr()->count())->toBe(1);
    });

    test('effectiveBetween scope filters by date range', function (): void {
        StaffPromotion::create(['employee_id' => $this->employee->id, 'effective_date' => '2024-01-15']);
        StaffPromotion::create(['employee_id' => $this->employee->id, 'effective_date' => '2024-06-15']);
        StaffPromotion::create(['employee_id' => $this->employee->id, 'effective_date' => '2024-12-15']);

        expect(StaffPromotion::effectiveBetween('2024-01-01', '2024-06-30')->count())->toBe(2)
            ->and(StaffPromotion::effectiveBetween('2024-07-01', '2024-12-31')->count())->toBe(1);
    });
});

describe('Employee Promotion Relationships', function (): void {
    test('employee has promotions relationship', function (): void {
        StaffPromotion::create(['employee_id' => $this->employee->id]);
        StaffPromotion::create(['employee_id' => $this->employee->id]);

        expect($this->employee->promotions)->toHaveCount(2);
    });

    test('employee has supervisor promotions relationship', function (): void {
        StaffPromotion::create([
            'employee_id' => $this->employee->id,
            'supervisor_id' => $this->supervisor->id,
        ]);

        expect($this->supervisor->supervisorPromotions)->toHaveCount(1);
    });

    test('employee has HR promotions relationship', function (): void {
        StaffPromotion::create([
            'employee_id' => $this->employee->id,
            'hr_approver_id' => $this->hrApprover->id,
        ]);

        expect($this->hrApprover->hrPromotions)->toHaveCount(1);
    });
});

describe('StaffPromotion Grade Level Change', function (): void {
    test('getGradeLevelChange returns difference in grade levels', function (): void {
        $promotion = StaffPromotion::create([
            'employee_id' => $this->employee->id,
            'from_grade_id' => $this->grade1->id,
            'to_grade_id' => $this->grade2->id,
        ]);

        expect($promotion->getGradeLevelChange())->toBe(1);
    });

    test('getGradeLevelChange returns null when grades not set', function (): void {
        $promotion = StaffPromotion::create([
            'employee_id' => $this->employee->id,
        ]);

        expect($promotion->getGradeLevelChange())->toBeNull();
    });
});

describe('StaffPromotion SoftDeletes', function (): void {
    test('can soft delete a promotion', function (): void {
        $promotion = StaffPromotion::create([
            'employee_id' => $this->employee->id,
        ]);

        $promotion->delete();

        expect(StaffPromotion::count())->toBe(0)
            ->and(StaffPromotion::withTrashed()->count())->toBe(1);
    });

    test('can restore a soft deleted promotion', function (): void {
        $promotion = StaffPromotion::create([
            'employee_id' => $this->employee->id,
        ]);

        $promotion->delete();
        $promotion->restore();

        expect(StaffPromotion::count())->toBe(1);
    });
});
