<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Database\Factories;

use App\Modules\HrmsCore\Models\Organization\Grade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Grade>
 */
final class GradeFactory extends Factory
{
    protected $model = Grade::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $sortOrder = $this->faker->unique()->numberBetween(1, 20);

        return [
            'tenant_id' => $this->faker->uuid(),
            'name' => 'Grade '.$sortOrder,
            'slug' => 'grade-'.$sortOrder,
            'description' => $this->faker->optional(0.5)->sentence(),
            'can_recommend_leave' => $this->faker->boolean(30),
            'can_approve_leave' => $this->faker->boolean(20),
            'can_appraise' => $this->faker->boolean(30),
            'sort_order' => $sortOrder,
        ];
    }

    /**
     * Set a specific sort order for the grade.
     */
    public function sortOrder(int $order): static
    {
        return $this->state(fn (array $attributes): array => [
            'name' => 'Grade '.$order,
            'slug' => 'grade-'.$order,
            'sort_order' => $order,
        ]);
    }

    /**
     * Set the grade as able to recommend leave.
     */
    public function canRecommendLeave(): static
    {
        return $this->state(fn (array $attributes): array => [
            'can_recommend_leave' => true,
        ]);
    }

    /**
     * Set the grade as able to approve leave.
     */
    public function canApproveLeave(): static
    {
        return $this->state(fn (array $attributes): array => [
            'can_approve_leave' => true,
        ]);
    }

    /**
     * Set the grade as able to appraise.
     */
    public function canAppraise(): static
    {
        return $this->state(fn (array $attributes): array => [
            'can_appraise' => true,
        ]);
    }

    /**
     * Set the tenant for the grade.
     */
    public function forTenant(string $tenantId): static
    {
        return $this->state(fn (array $attributes): array => [
            'tenant_id' => $tenantId,
        ]);
    }
}
