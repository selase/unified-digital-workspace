<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Database\Factories;

use App\Modules\HrmsCore\Models\Leave\LeaveCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LeaveCategory>
 */
final class LeaveCategoryFactory extends Factory
{
    protected $model = LeaveCategory::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->randomElement([
            'Annual Leave',
            'Sick Leave',
            'Maternity Leave',
            'Paternity Leave',
            'Study Leave',
            'Compassionate Leave',
            'Casual Leave',
            'Emergency Leave',
            'Unpaid Leave',
            'Sabbatical Leave',
        ]);

        return [
            'tenant_id' => fake()->uuid(),
            'name' => $name,
            'slug' => Str::slug($name),
            'default_days' => fake()->numberBetween(5, 30),
            'description' => fake()->optional(0.7)->sentence(),
            'is_paid' => fake()->boolean(80),
            'requires_documentation' => fake()->boolean(30),
            'is_active' => true,
            'sort_order' => fake()->numberBetween(1, 10),
        ];
    }

    /**
     * Indicate that the category is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the category is unpaid.
     */
    public function unpaid(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_paid' => false,
        ]);
    }

    /**
     * Indicate that the category requires documentation.
     */
    public function requiresDocumentation(): static
    {
        return $this->state(fn (array $attributes): array => [
            'requires_documentation' => true,
        ]);
    }

    /**
     * Set specific default days.
     */
    public function withDays(int $days): static
    {
        return $this->state(fn (array $attributes): array => [
            'default_days' => $days,
        ]);
    }

    /**
     * Set the tenant for the category.
     */
    public function forTenant(string $tenantId): static
    {
        return $this->state(fn (array $attributes): array => [
            'tenant_id' => $tenantId,
        ]);
    }
}
