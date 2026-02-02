<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Database\Factories;

use App\Modules\HrmsCore\Enums\LeaveStatus;
use App\Modules\HrmsCore\Models\Employees\Employee;
use App\Modules\HrmsCore\Models\Leave\LeaveCategory;
use App\Modules\HrmsCore\Models\Leave\LeaveRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveRequest>
 */
final class LeaveRequestFactory extends Factory
{
    protected $model = LeaveRequest::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('+1 week', '+2 months');
        $requestedDays = fake()->numberBetween(1, 14);
        $endDate = (clone $startDate)->modify("+{$requestedDays} days");

        return [
            'tenant_id' => fake()->uuid(),
            'employee_id' => Employee::factory(),
            'leave_category_id' => LeaveCategory::factory(),
            'proposed_start_date' => $startDate,
            'proposed_end_date' => $endDate,
            'no_requested_days' => $requestedDays,
            'leave_reasons' => fake()->optional(0.8)->sentence(),
            'contact_when_away' => fake()->optional(0.6)->e164PhoneNumber(),
            'status' => LeaveStatus::Pending,
            'no_of_holidays_in_period' => 0,
            'no_of_weekends_in_period' => fake()->numberBetween(0, 4),
            'is_recalled' => false,
        ];
    }

    /**
     * Indicate that the request is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => LeaveStatus::Pending,
        ]);
    }

    /**
     * Indicate that the request is verified by supervisor.
     */
    public function verified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => LeaveStatus::Verified,
            'supervisor_id' => Employee::factory(),
            'supervisor_verified_at' => fake()->dateTimeBetween('-1 week', 'now'),
            'no_recommended_days' => $attributes['no_requested_days'] ?? fake()->numberBetween(1, 14),
            'recommended_start_date' => $attributes['proposed_start_date'] ?? now()->addWeek(),
            'recommended_end_date' => $attributes['proposed_end_date'] ?? now()->addWeeks(2),
            'supervisor_comments' => fake()->optional(0.5)->sentence(),
        ]);
    }

    /**
     * Indicate that the request is approved.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => LeaveStatus::Approved,
            'supervisor_id' => Employee::factory(),
            'supervisor_verified_at' => fake()->dateTimeBetween('-2 weeks', '-1 week'),
            'hod_id' => Employee::factory(),
            'hod_decision_at' => fake()->dateTimeBetween('-1 week', 'now'),
            'no_recommended_days' => $attributes['no_requested_days'] ?? fake()->numberBetween(1, 14),
            'no_of_days_approved' => $attributes['no_requested_days'] ?? fake()->numberBetween(1, 14),
            'approved_start_date' => $attributes['proposed_start_date'] ?? now()->addWeek(),
            'approved_end_date' => $attributes['proposed_end_date'] ?? now()->addWeeks(2),
            'hod_comments' => fake()->optional(0.5)->sentence(),
        ]);
    }

    /**
     * Indicate that the request is rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => LeaveStatus::Rejected,
            'hod_id' => Employee::factory(),
            'hod_decision_at' => fake()->dateTimeBetween('-1 week', 'now'),
            'hod_comments' => fake()->sentence(),
        ]);
    }

    /**
     * Indicate that the request is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => LeaveStatus::Cancelled,
        ]);
    }

    /**
     * Indicate that the request is recalled.
     */
    public function recalled(): static
    {
        return $this->state(function (array $attributes): array {
            $approvedDays = $attributes['no_of_days_approved'] ?? fake()->numberBetween(5, 14);
            $recalledDays = fake()->numberBetween(1, $approvedDays - 1);

            return [
                'status' => LeaveStatus::Recalled,
                'is_recalled' => true,
                'recall_date' => fake()->dateTimeBetween('-1 week', 'now'),
                'no_of_days_recalled' => $recalledDays,
                'recall_reason' => fake()->sentence(),
                'recalled_at' => fake()->dateTimeBetween('-1 week', 'now'),
            ];
        });
    }

    /**
     * Set the employee for the request.
     */
    public function forEmployee(Employee $employee): static
    {
        return $this->state(fn (array $attributes): array => [
            'employee_id' => $employee->id,
            'tenant_id' => $employee->tenant_id,
        ]);
    }

    /**
     * Set the leave category for the request.
     */
    public function forCategory(LeaveCategory $category): static
    {
        return $this->state(fn (array $attributes): array => [
            'leave_category_id' => $category->id,
        ]);
    }

    /**
     * Set specific dates for the request.
     */
    public function withDates(\DateTimeInterface $startDate, \DateTimeInterface $endDate): static
    {
        return $this->state(fn (array $attributes): array => [
            'proposed_start_date' => $startDate,
            'proposed_end_date' => $endDate,
        ]);
    }

    /**
     * Set the tenant for the request.
     */
    public function forTenant(string $tenantId): static
    {
        return $this->state(fn (array $attributes): array => [
            'tenant_id' => $tenantId,
        ]);
    }
}
