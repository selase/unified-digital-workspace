<?php

declare(strict_types=1);

namespace App\Modules\ProjectManagement\Database\Factories;

use App\Modules\ProjectManagement\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Project>
 */
final class ProjectFactory extends Factory
{
    protected $model = Project::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->sentence(3);

        return [
            'tenant_id' => fake()->uuid(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 9999),
            'description' => fake()->paragraph(),
            'status' => 'planned',
            'priority' => 'medium',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addWeeks(4)->toDateString(),
        ];
    }

    public function forTenant(string $tenantId): static
    {
        return $this->state(fn () => ['tenant_id' => $tenantId]);
    }
}
