<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Modules\HrmsCore\Enums\AppraisalCycle;
use App\Modules\HrmsCore\Enums\AppraisalStatus;
use App\Modules\HrmsCore\Enums\GoalStatus;
use App\Modules\HrmsCore\Enums\RecommendationType;
use App\Modules\HrmsCore\Models\Appraisal\Appraisal;
use App\Modules\HrmsCore\Models\Appraisal\AppraisalComment;
use App\Modules\HrmsCore\Models\Appraisal\AppraisalCompetency;
use App\Modules\HrmsCore\Models\Appraisal\AppraisalCriterion;
use App\Modules\HrmsCore\Models\Appraisal\AppraisalGoal;
use App\Modules\HrmsCore\Models\Appraisal\AppraisalPeriod;
use App\Modules\HrmsCore\Models\Appraisal\AppraisalRatingScale;
use App\Modules\HrmsCore\Models\Appraisal\AppraisalRecommendation;
use App\Modules\HrmsCore\Models\Appraisal\AppraisalResponse;
use App\Modules\HrmsCore\Models\Appraisal\AppraisalReview;
use App\Modules\HrmsCore\Models\Appraisal\AppraisalScore;
use App\Modules\HrmsCore\Models\Appraisal\AppraisalSection;
use App\Modules\HrmsCore\Models\Appraisal\AppraisalTemplate;
use App\Modules\HrmsCore\Models\Employees\Employee;
use App\Services\Tenancy\TenantContext;

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create([
        'name' => 'Appraisal Test Company',
        'slug' => 'appraisal-test-company',
    ]);

    app(TenantContext::class)->setTenant($this->tenant);
});

describe('AppraisalPeriod Model', function (): void {
    test('can create an appraisal period', function (): void {
        $period = AppraisalPeriod::create([
            'name' => 'Annual Review 2024',
            'cycle' => AppraisalCycle::Annual,
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
        ]);

        expect($period->id)->not->toBeNull()
            ->and($period->uuid)->not->toBeNull()
            ->and($period->tenant_id)->toBe($this->tenant->id)
            ->and($period->name)->toBe('Annual Review 2024')
            ->and($period->slug)->toBe('annual-review-2024')
            ->and($period->cycle)->toBe(AppraisalCycle::Annual)
            ->and($period->is_active)->toBeTrue();
    });

    test('period generates slug automatically', function (): void {
        $period = AppraisalPeriod::create([
            'name' => 'Mid Year Review Q2',
            'cycle' => AppraisalCycle::Midyear,
            'start_date' => '2024-04-01',
            'end_date' => '2024-06-30',
        ]);

        expect($period->slug)->toBe('mid-year-review-q2');
    });

    test('isCurrentlyActive returns true for active periods within date range', function (): void {
        $period = AppraisalPeriod::create([
            'name' => 'Current Period',
            'cycle' => AppraisalCycle::Annual,
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
            'is_active' => true,
        ]);

        expect($period->isCurrentlyActive())->toBeTrue();
    });

    test('isCurrentlyActive returns false for inactive periods', function (): void {
        $period = AppraisalPeriod::create([
            'name' => 'Inactive Period',
            'cycle' => AppraisalCycle::Annual,
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
            'is_active' => false,
        ]);

        expect($period->isCurrentlyActive())->toBeFalse();
    });

    test('hasEnded returns true for past periods', function (): void {
        $period = AppraisalPeriod::create([
            'name' => 'Past Period',
            'cycle' => AppraisalCycle::Annual,
            'start_date' => now()->subYear(),
            'end_date' => now()->subMonth(),
        ]);

        expect($period->hasEnded())->toBeTrue();
    });

    test('hasNotStarted returns true for future periods', function (): void {
        $period = AppraisalPeriod::create([
            'name' => 'Future Period',
            'cycle' => AppraisalCycle::Annual,
            'start_date' => now()->addMonth(),
            'end_date' => now()->addYear(),
        ]);

        expect($period->hasNotStarted())->toBeTrue();
    });

    test('period has templates relationship', function (): void {
        $period = AppraisalPeriod::create([
            'name' => 'Test Period',
            'cycle' => AppraisalCycle::Annual,
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
        ]);

        AppraisalTemplate::create([
            'name' => 'Default Template',
            'period_id' => $period->id,
        ]);

        expect($period->templates)->toHaveCount(1);
    });

    test('active scope filters correctly', function (): void {
        AppraisalPeriod::create(['name' => 'Active', 'cycle' => 'annual', 'start_date' => '2024-01-01', 'end_date' => '2024-12-31', 'is_active' => true]);
        AppraisalPeriod::create(['name' => 'Inactive', 'cycle' => 'annual', 'start_date' => '2024-01-01', 'end_date' => '2024-12-31', 'is_active' => false]);

        expect(AppraisalPeriod::active()->count())->toBe(1)
            ->and(AppraisalPeriod::count())->toBe(2);
    });

    test('forCycle scope filters by cycle type', function (): void {
        AppraisalPeriod::create(['name' => 'Annual 1', 'cycle' => AppraisalCycle::Annual, 'start_date' => '2024-01-01', 'end_date' => '2024-12-31']);
        AppraisalPeriod::create(['name' => 'Annual 2', 'cycle' => AppraisalCycle::Annual, 'start_date' => '2025-01-01', 'end_date' => '2025-12-31']);
        AppraisalPeriod::create(['name' => 'Midyear', 'cycle' => AppraisalCycle::Midyear, 'start_date' => '2024-04-01', 'end_date' => '2024-06-30']);

        expect(AppraisalPeriod::forCycle(AppraisalCycle::Annual)->count())->toBe(2)
            ->and(AppraisalPeriod::forCycle(AppraisalCycle::Midyear)->count())->toBe(1);
    });
});

describe('AppraisalTemplate Model', function (): void {
    test('can create an appraisal template', function (): void {
        $template = AppraisalTemplate::create([
            'name' => 'Standard Performance Review',
            'description' => 'Default template for annual reviews',
        ]);

        expect($template->id)->not->toBeNull()
            ->and($template->uuid)->not->toBeNull()
            ->and($template->name)->toBe('Standard Performance Review')
            ->and($template->slug)->toBe('standard-performance-review')
            ->and($template->is_default)->toBeFalse()
            ->and($template->is_active)->toBeTrue();
    });

    test('template has sections relationship', function (): void {
        $template = AppraisalTemplate::create(['name' => 'Test Template']);

        AppraisalSection::create(['template_id' => $template->id, 'name' => 'Performance']);
        AppraisalSection::create(['template_id' => $template->id, 'name' => 'Goals']);

        expect($template->sections)->toHaveCount(2);
    });

    test('getTotalSectionWeight calculates sum of section weights', function (): void {
        $template = AppraisalTemplate::create(['name' => 'Test Template']);

        AppraisalSection::create(['template_id' => $template->id, 'name' => 'Performance', 'weight' => 40]);
        AppraisalSection::create(['template_id' => $template->id, 'name' => 'Goals', 'weight' => 30]);
        AppraisalSection::create(['template_id' => $template->id, 'name' => 'Competencies', 'weight' => 30]);

        expect($template->getTotalSectionWeight())->toBe(100.0);
    });

    test('default scope filters correctly', function (): void {
        AppraisalTemplate::create(['name' => 'Default', 'is_default' => true]);
        AppraisalTemplate::create(['name' => 'Not Default', 'is_default' => false]);

        expect(AppraisalTemplate::default()->count())->toBe(1);
    });
});

describe('AppraisalSection Model', function (): void {
    test('can create an appraisal section', function (): void {
        $template = AppraisalTemplate::create(['name' => 'Test Template']);

        $section = AppraisalSection::create([
            'template_id' => $template->id,
            'name' => 'Performance Metrics',
            'weight' => 40,
        ]);

        expect($section->id)->not->toBeNull()
            ->and($section->name)->toBe('Performance Metrics')
            ->and((float) $section->weight)->toBe(40.0)
            ->and($section->is_active)->toBeTrue();
    });

    test('section has criteria relationship', function (): void {
        $template = AppraisalTemplate::create(['name' => 'Test Template']);
        $section = AppraisalSection::create(['template_id' => $template->id, 'name' => 'Performance']);

        AppraisalCriterion::create(['section_id' => $section->id, 'name' => 'Quality of Work']);
        AppraisalCriterion::create(['section_id' => $section->id, 'name' => 'Productivity']);

        expect($section->criteria)->toHaveCount(2);
    });

    test('getTotalCriteriaWeight calculates sum of criteria weights', function (): void {
        $template = AppraisalTemplate::create(['name' => 'Test Template']);
        $section = AppraisalSection::create(['template_id' => $template->id, 'name' => 'Performance']);

        AppraisalCriterion::create(['section_id' => $section->id, 'name' => 'Quality', 'weight' => 50]);
        AppraisalCriterion::create(['section_id' => $section->id, 'name' => 'Speed', 'weight' => 50]);

        expect($section->getTotalCriteriaWeight())->toBe(100.0);
    });
});

describe('AppraisalCriterion Model', function (): void {
    test('can create an appraisal criterion', function (): void {
        $template = AppraisalTemplate::create(['name' => 'Test Template']);
        $section = AppraisalSection::create(['template_id' => $template->id, 'name' => 'Performance']);

        $criterion = AppraisalCriterion::create([
            'section_id' => $section->id,
            'name' => 'Quality of Work',
            'description' => 'Accuracy and thoroughness of work',
            'weight' => 25,
        ]);

        expect($criterion->id)->not->toBeNull()
            ->and($criterion->name)->toBe('Quality of Work')
            ->and((float) $criterion->weight)->toBe(25.0)
            ->and($criterion->is_required)->toBeTrue();
    });

    test('required scope filters correctly', function (): void {
        $template = AppraisalTemplate::create(['name' => 'Test Template']);
        $section = AppraisalSection::create(['template_id' => $template->id, 'name' => 'Performance']);

        AppraisalCriterion::create(['section_id' => $section->id, 'name' => 'Required', 'is_required' => true]);
        AppraisalCriterion::create(['section_id' => $section->id, 'name' => 'Optional', 'is_required' => false]);

        expect(AppraisalCriterion::required()->count())->toBe(1);
    });
});

describe('AppraisalRatingScale Model', function (): void {
    test('can create a rating scale', function (): void {
        $scale = AppraisalRatingScale::create([
            'value' => 5,
            'label' => 'Outstanding',
            'description' => 'Exceptional performance',
        ]);

        expect($scale->id)->not->toBeNull()
            ->and($scale->value)->toBe(5)
            ->and($scale->label)->toBe('Outstanding');
    });

    test('createDefaultScales creates 5 default scales', function (): void {
        $scales = AppraisalRatingScale::createDefaultScales();

        expect($scales)->toHaveCount(5)
            ->and($scales->first()->value)->toBe(1)
            ->and($scales->last()->value)->toBe(5);
    });

    test('getCssClass returns appropriate color', function (): void {
        $scale = AppraisalRatingScale::create(['value' => 5, 'label' => 'Outstanding']);

        expect($scale->getCssClass())->toBe('bg-green-100 text-green-800');
    });
});

describe('Appraisal Model', function (): void {
    beforeEach(function (): void {
        $this->period = AppraisalPeriod::create([
            'name' => 'Test Period',
            'cycle' => AppraisalCycle::Annual,
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
        ]);

        $this->template = AppraisalTemplate::create([
            'name' => 'Test Template',
            'period_id' => $this->period->id,
        ]);

        $this->employee = Employee::factory()->forTenant($this->tenant->id)->create();
        $this->supervisor = Employee::factory()->forTenant($this->tenant->id)->create();
    });

    test('can create an appraisal', function (): void {
        $appraisal = Appraisal::create([
            'employee_id' => $this->employee->id,
            'period_id' => $this->period->id,
            'template_id' => $this->template->id,
            'supervisor_id' => $this->supervisor->id,
        ]);

        expect($appraisal->id)->not->toBeNull()
            ->and($appraisal->uuid)->not->toBeNull()
            ->and($appraisal->status)->toBe(AppraisalStatus::Draft);
    });

    test('appraisal has relationships', function (): void {
        $appraisal = Appraisal::create([
            'employee_id' => $this->employee->id,
            'period_id' => $this->period->id,
            'template_id' => $this->template->id,
            'supervisor_id' => $this->supervisor->id,
        ]);

        expect($appraisal->employee->id)->toBe($this->employee->id)
            ->and($appraisal->period->id)->toBe($this->period->id)
            ->and($appraisal->template->id)->toBe($this->template->id)
            ->and($appraisal->supervisor->id)->toBe($this->supervisor->id);
    });

    test('isDraft returns true for draft appraisals', function (): void {
        $appraisal = Appraisal::create([
            'employee_id' => $this->employee->id,
            'period_id' => $this->period->id,
            'template_id' => $this->template->id,
        ]);

        expect($appraisal->isDraft())->toBeTrue()
            ->and($appraisal->isComplete())->toBeFalse();
    });

    test('can transition through workflow statuses', function (): void {
        $appraisal = Appraisal::create([
            'employee_id' => $this->employee->id,
            'period_id' => $this->period->id,
            'template_id' => $this->template->id,
            'supervisor_id' => $this->supervisor->id,
        ]);

        // Draft → Self Assessment
        expect($appraisal->startSelfAssessment())->toBeTrue()
            ->and($appraisal->isInSelfAssessment())->toBeTrue();

        // Self Assessment → Supervisor Review
        expect($appraisal->submitSelfAssessment())->toBeTrue()
            ->and($appraisal->isAwaitingSupervisorReview())->toBeTrue()
            ->and($appraisal->self_assessment_submitted_at)->not->toBeNull();

        // Supervisor Review → HOD Review
        expect($appraisal->completeSupervisorReview())->toBeTrue()
            ->and($appraisal->isAwaitingHodReview())->toBeTrue();
    });

    test('canReview returns true for correct reviewer', function (): void {
        $appraisal = Appraisal::create([
            'employee_id' => $this->employee->id,
            'period_id' => $this->period->id,
            'template_id' => $this->template->id,
            'supervisor_id' => $this->supervisor->id,
            'status' => AppraisalStatus::SupervisorReview,
        ]);

        expect($appraisal->canReview($this->supervisor))->toBeTrue()
            ->and($appraisal->canReview($this->employee))->toBeFalse();
    });

    test('cannot transition to invalid status', function (): void {
        $appraisal = Appraisal::create([
            'employee_id' => $this->employee->id,
            'period_id' => $this->period->id,
            'template_id' => $this->template->id,
        ]);

        // Cannot skip from Draft to HodReview
        expect($appraisal->transitionTo(AppraisalStatus::HodReview))->toBeFalse()
            ->and($appraisal->isDraft())->toBeTrue();
    });

    test('can cancel appraisal', function (): void {
        $appraisal = Appraisal::create([
            'employee_id' => $this->employee->id,
            'period_id' => $this->period->id,
            'template_id' => $this->template->id,
        ]);

        expect($appraisal->cancel())->toBeTrue()
            ->and($appraisal->isCancelled())->toBeTrue();
    });

    test('forEmployee scope filters by employee', function (): void {
        $employee2 = Employee::factory()->forTenant($this->tenant->id)->create();
        $period2 = AppraisalPeriod::create(['name' => 'Period 2', 'cycle' => 'annual', 'start_date' => '2025-01-01', 'end_date' => '2025-12-31']);

        Appraisal::create(['employee_id' => $this->employee->id, 'period_id' => $this->period->id, 'template_id' => $this->template->id]);
        Appraisal::create(['employee_id' => $this->employee->id, 'period_id' => $period2->id, 'template_id' => $this->template->id]);
        Appraisal::create(['employee_id' => $employee2->id, 'period_id' => $this->period->id, 'template_id' => $this->template->id]);

        expect(Appraisal::forEmployee($this->employee->id)->count())->toBe(2)
            ->and(Appraisal::forEmployee($employee2->id)->count())->toBe(1);
    });

    test('pending scope excludes completed and cancelled', function (): void {
        Appraisal::create(['employee_id' => $this->employee->id, 'period_id' => $this->period->id, 'template_id' => $this->template->id, 'status' => AppraisalStatus::Draft]);

        $period2 = AppraisalPeriod::create(['name' => 'Period 2', 'cycle' => 'annual', 'start_date' => '2025-01-01', 'end_date' => '2025-12-31']);
        Appraisal::create(['employee_id' => $this->employee->id, 'period_id' => $period2->id, 'template_id' => $this->template->id, 'status' => AppraisalStatus::Complete]);

        expect(Appraisal::pending()->count())->toBe(1)
            ->and(Appraisal::count())->toBe(2);
    });
});

describe('AppraisalResponse Model', function (): void {
    test('can create an appraisal response', function (): void {
        $period = AppraisalPeriod::create(['name' => 'Test', 'cycle' => 'annual', 'start_date' => '2024-01-01', 'end_date' => '2024-12-31']);
        $template = AppraisalTemplate::create(['name' => 'Test']);
        $section = AppraisalSection::create(['template_id' => $template->id, 'name' => 'Performance']);
        $criterion = AppraisalCriterion::create(['section_id' => $section->id, 'name' => 'Quality']);
        $employee = Employee::factory()->forTenant($this->tenant->id)->create();
        $appraisal = Appraisal::create(['employee_id' => $employee->id, 'period_id' => $period->id, 'template_id' => $template->id]);

        $response = AppraisalResponse::create([
            'appraisal_id' => $appraisal->id,
            'criterion_id' => $criterion->id,
            'self_rating' => 4,
            'self_comments' => 'I believe I performed well',
        ]);

        expect($response->id)->not->toBeNull()
            ->and($response->self_rating)->toBe(4)
            ->and($response->hasSelfAssessment())->toBeTrue()
            ->and($response->hasSupervisorAssessment())->toBeFalse();
    });

    test('getEffectiveRating returns correct priority', function (): void {
        $period = AppraisalPeriod::create(['name' => 'Test', 'cycle' => 'annual', 'start_date' => '2024-01-01', 'end_date' => '2024-12-31']);
        $template = AppraisalTemplate::create(['name' => 'Test']);
        $section = AppraisalSection::create(['template_id' => $template->id, 'name' => 'Performance']);
        $criterion = AppraisalCriterion::create(['section_id' => $section->id, 'name' => 'Quality']);
        $employee = Employee::factory()->forTenant($this->tenant->id)->create();
        $appraisal = Appraisal::create(['employee_id' => $employee->id, 'period_id' => $period->id, 'template_id' => $template->id]);

        $response = AppraisalResponse::create([
            'appraisal_id' => $appraisal->id,
            'criterion_id' => $criterion->id,
            'self_rating' => 3,
            'supervisor_rating' => 4,
            'final_rating' => 5,
        ]);

        expect($response->getEffectiveRating())->toBe(5);

        $response->final_rating = null;
        expect($response->getEffectiveRating())->toBe(4);
    });

    test('getRatingDifference calculates correctly', function (): void {
        $period = AppraisalPeriod::create(['name' => 'Test', 'cycle' => 'annual', 'start_date' => '2024-01-01', 'end_date' => '2024-12-31']);
        $template = AppraisalTemplate::create(['name' => 'Test']);
        $section = AppraisalSection::create(['template_id' => $template->id, 'name' => 'Performance']);
        $criterion = AppraisalCriterion::create(['section_id' => $section->id, 'name' => 'Quality']);
        $employee = Employee::factory()->forTenant($this->tenant->id)->create();
        $appraisal = Appraisal::create(['employee_id' => $employee->id, 'period_id' => $period->id, 'template_id' => $template->id]);

        $response = AppraisalResponse::create([
            'appraisal_id' => $appraisal->id,
            'criterion_id' => $criterion->id,
            'self_rating' => 4,
            'supervisor_rating' => 3,
        ]);

        expect($response->getRatingDifference())->toBe(-1);
    });
});

describe('AppraisalGoal Model', function (): void {
    test('can create an appraisal goal', function (): void {
        $period = AppraisalPeriod::create(['name' => 'Test', 'cycle' => 'annual', 'start_date' => '2024-01-01', 'end_date' => '2024-12-31']);
        $template = AppraisalTemplate::create(['name' => 'Test']);
        $employee = Employee::factory()->forTenant($this->tenant->id)->create();
        $appraisal = Appraisal::create(['employee_id' => $employee->id, 'period_id' => $period->id, 'template_id' => $template->id]);

        $goal = AppraisalGoal::create([
            'appraisal_id' => $appraisal->id,
            'title' => 'Increase sales by 20%',
            'target' => '120% of Q1 target',
            'weight' => 25,
        ]);

        expect($goal->id)->not->toBeNull()
            ->and($goal->title)->toBe('Increase sales by 20%')
            ->and($goal->status)->toBe(GoalStatus::NotStarted);
    });

    test('isOverdue returns true for past due incomplete goals', function (): void {
        $period = AppraisalPeriod::create(['name' => 'Test', 'cycle' => 'annual', 'start_date' => '2024-01-01', 'end_date' => '2024-12-31']);
        $template = AppraisalTemplate::create(['name' => 'Test']);
        $employee = Employee::factory()->forTenant($this->tenant->id)->create();
        $appraisal = Appraisal::create(['employee_id' => $employee->id, 'period_id' => $period->id, 'template_id' => $template->id]);

        $goal = AppraisalGoal::create([
            'appraisal_id' => $appraisal->id,
            'title' => 'Overdue Goal',
            'due_date' => now()->subMonth(),
            'status' => GoalStatus::InProgress,
        ]);

        expect($goal->isOverdue())->toBeTrue();
    });

    test('isOverdue returns false for completed goals', function (): void {
        $period = AppraisalPeriod::create(['name' => 'Test', 'cycle' => 'annual', 'start_date' => '2024-01-01', 'end_date' => '2024-12-31']);
        $template = AppraisalTemplate::create(['name' => 'Test']);
        $employee = Employee::factory()->forTenant($this->tenant->id)->create();
        $appraisal = Appraisal::create(['employee_id' => $employee->id, 'period_id' => $period->id, 'template_id' => $template->id]);

        $goal = AppraisalGoal::create([
            'appraisal_id' => $appraisal->id,
            'title' => 'Completed Goal',
            'due_date' => now()->subMonth(),
            'status' => GoalStatus::Completed,
        ]);

        expect($goal->isOverdue())->toBeFalse();
    });
});

describe('AppraisalReview Model', function (): void {
    test('can create an appraisal review', function (): void {
        $period = AppraisalPeriod::create(['name' => 'Test', 'cycle' => 'annual', 'start_date' => '2024-01-01', 'end_date' => '2024-12-31']);
        $template = AppraisalTemplate::create(['name' => 'Test']);
        $employee = Employee::factory()->forTenant($this->tenant->id)->create();
        $supervisor = Employee::factory()->forTenant($this->tenant->id)->create();
        $appraisal = Appraisal::create(['employee_id' => $employee->id, 'period_id' => $period->id, 'template_id' => $template->id]);

        $review = AppraisalReview::create([
            'appraisal_id' => $appraisal->id,
            'reviewer_id' => $supervisor->id,
            'reviewer_type' => AppraisalReview::TYPE_SUPERVISOR,
            'overall_rating' => 4.2,
            'general_comments' => 'Good performance overall',
        ]);

        expect($review->id)->not->toBeNull()
            ->and($review->isSupervisorReview())->toBeTrue()
            ->and($review->isComplete())->toBeFalse();
    });

    test('markComplete sets reviewed_at timestamp', function (): void {
        $period = AppraisalPeriod::create(['name' => 'Test', 'cycle' => 'annual', 'start_date' => '2024-01-01', 'end_date' => '2024-12-31']);
        $template = AppraisalTemplate::create(['name' => 'Test']);
        $employee = Employee::factory()->forTenant($this->tenant->id)->create();
        $supervisor = Employee::factory()->forTenant($this->tenant->id)->create();
        $appraisal = Appraisal::create(['employee_id' => $employee->id, 'period_id' => $period->id, 'template_id' => $template->id]);

        $review = AppraisalReview::create([
            'appraisal_id' => $appraisal->id,
            'reviewer_id' => $supervisor->id,
            'reviewer_type' => AppraisalReview::TYPE_SUPERVISOR,
        ]);

        $review->markComplete();

        expect($review->isComplete())->toBeTrue()
            ->and($review->reviewed_at)->not->toBeNull();
    });
});

describe('AppraisalRecommendation Model', function (): void {
    test('can create an appraisal recommendation', function (): void {
        $period = AppraisalPeriod::create(['name' => 'Test', 'cycle' => 'annual', 'start_date' => '2024-01-01', 'end_date' => '2024-12-31']);
        $template = AppraisalTemplate::create(['name' => 'Test']);
        $employee = Employee::factory()->forTenant($this->tenant->id)->create();
        $appraisal = Appraisal::create(['employee_id' => $employee->id, 'period_id' => $period->id, 'template_id' => $template->id]);

        $recommendation = AppraisalRecommendation::create([
            'appraisal_id' => $appraisal->id,
            'type' => RecommendationType::Promotion,
            'description' => 'Recommend for promotion to Senior Developer',
        ]);

        expect($recommendation->id)->not->toBeNull()
            ->and($recommendation->type)->toBe(RecommendationType::Promotion)
            ->and($recommendation->isPending())->toBeTrue()
            ->and($recommendation->isPositive())->toBeTrue();
    });

    test('approve sets approved_by and approved_at', function (): void {
        $period = AppraisalPeriod::create(['name' => 'Test', 'cycle' => 'annual', 'start_date' => '2024-01-01', 'end_date' => '2024-12-31']);
        $template = AppraisalTemplate::create(['name' => 'Test']);
        $employee = Employee::factory()->forTenant($this->tenant->id)->create();
        $approver = Employee::factory()->forTenant($this->tenant->id)->create();
        $appraisal = Appraisal::create(['employee_id' => $employee->id, 'period_id' => $period->id, 'template_id' => $template->id]);

        $recommendation = AppraisalRecommendation::create([
            'appraisal_id' => $appraisal->id,
            'type' => RecommendationType::Training,
        ]);

        $recommendation->approve($approver->id);

        expect($recommendation->isApproved())->toBeTrue()
            ->and($recommendation->approved_by)->toBe($approver->id)
            ->and($recommendation->approved_at)->not->toBeNull();
    });

    test('isCorrective returns true for PIP and Termination', function (): void {
        $period = AppraisalPeriod::create(['name' => 'Test', 'cycle' => 'annual', 'start_date' => '2024-01-01', 'end_date' => '2024-12-31']);
        $template = AppraisalTemplate::create(['name' => 'Test']);
        $employee = Employee::factory()->forTenant($this->tenant->id)->create();
        $appraisal = Appraisal::create(['employee_id' => $employee->id, 'period_id' => $period->id, 'template_id' => $template->id]);

        $recommendation = AppraisalRecommendation::create([
            'appraisal_id' => $appraisal->id,
            'type' => RecommendationType::PerformanceImprovement,
        ]);

        expect($recommendation->isCorrective())->toBeTrue()
            ->and($recommendation->isPositive())->toBeFalse();
    });
});

describe('Employee Appraisal Relationships', function (): void {
    test('employee has appraisals relationship', function (): void {
        $employee = Employee::factory()->forTenant($this->tenant->id)->create();
        $period = AppraisalPeriod::create(['name' => 'Test', 'cycle' => 'annual', 'start_date' => '2024-01-01', 'end_date' => '2024-12-31']);
        $template = AppraisalTemplate::create(['name' => 'Test']);

        Appraisal::create(['employee_id' => $employee->id, 'period_id' => $period->id, 'template_id' => $template->id]);

        expect($employee->appraisals)->toHaveCount(1);
    });

    test('employee has supervisorAppraisals relationship', function (): void {
        $employee = Employee::factory()->forTenant($this->tenant->id)->create();
        $supervisor = Employee::factory()->forTenant($this->tenant->id)->create();
        $period = AppraisalPeriod::create(['name' => 'Test', 'cycle' => 'annual', 'start_date' => '2024-01-01', 'end_date' => '2024-12-31']);
        $template = AppraisalTemplate::create(['name' => 'Test']);

        Appraisal::create([
            'employee_id' => $employee->id,
            'period_id' => $period->id,
            'template_id' => $template->id,
            'supervisor_id' => $supervisor->id,
        ]);

        expect($supervisor->supervisorAppraisals)->toHaveCount(1);
    });
});

describe('Appraisal Enums', function (): void {
    test('AppraisalStatus has correct workflow transitions', function (): void {
        expect(AppraisalStatus::Draft->nextStatus())->toBe(AppraisalStatus::SelfAssessment)
            ->and(AppraisalStatus::SelfAssessment->nextStatus())->toBe(AppraisalStatus::SupervisorReview)
            ->and(AppraisalStatus::Complete->nextStatus())->toBeNull()
            ->and(AppraisalStatus::Draft->canTransitionTo(AppraisalStatus::SelfAssessment))->toBeTrue()
            ->and(AppraisalStatus::Draft->canTransitionTo(AppraisalStatus::Complete))->toBeFalse()
            ->and(AppraisalStatus::Draft->canTransitionTo(AppraisalStatus::Cancelled))->toBeTrue();
    });

    test('AppraisalRating has correct labels and values', function (): void {
        expect(\App\Modules\HrmsCore\Enums\AppraisalRating::Outstanding->value)->toBe(5)
            ->and(\App\Modules\HrmsCore\Enums\AppraisalRating::Outstanding->label())->toBe('Outstanding')
            ->and(\App\Modules\HrmsCore\Enums\AppraisalRating::options())->toHaveCount(5);
    });

    test('AppraisalCycle has correct duration months', function (): void {
        expect(AppraisalCycle::Annual->durationMonths())->toBe(12)
            ->and(AppraisalCycle::Midyear->durationMonths())->toBe(6)
            ->and(AppraisalCycle::Quarterly->durationMonths())->toBe(3);
    });

    test('GoalStatus has correct final states', function (): void {
        expect(GoalStatus::NotStarted->isFinal())->toBeFalse()
            ->and(GoalStatus::InProgress->isFinal())->toBeFalse()
            ->and(GoalStatus::Completed->isFinal())->toBeTrue()
            ->and(GoalStatus::Deferred->isFinal())->toBeTrue();
    });

    test('RecommendationType identifies positive and corrective types', function (): void {
        expect(RecommendationType::Promotion->isPositive())->toBeTrue()
            ->and(RecommendationType::Recognition->isPositive())->toBeTrue()
            ->and(RecommendationType::PerformanceImprovement->isCorrective())->toBeTrue()
            ->and(RecommendationType::Training->isPositive())->toBeFalse()
            ->and(RecommendationType::Training->isCorrective())->toBeFalse();
    });
});
