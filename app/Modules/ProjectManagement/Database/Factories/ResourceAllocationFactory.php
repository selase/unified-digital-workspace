<?php

declare(strict_types=1);

namespace App\Modules\ProjectManagement\Database\Factories;

use App\Modules\ProjectManagement\Models\Project;
use App\Modules\ProjectManagement\Models\ResourceAllocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResourceAllocation>
 */
final class ResourceAllocationFactory extends Factory
{
    protected $model = ResourceAllocation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->toDateString();

        return [
            'project_id' => Project::factory(),
            'user_id' => fake()->uuid(),
            'start_date' => $start,
            'end_date' => now()->addDays(5)->toDateString(),
            'allocation_percent' => 50,
            'role' => fake()->jobTitle(),
        ];
    }

    public function forTenant(string $tenantId): static
    {
        return $this->state(fn () => [
            'project_id' => Project::factory()->forTenant($tenantId),
        ]);
    }
}
