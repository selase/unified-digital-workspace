<?php

declare(strict_types=1);

namespace App\Modules\ProjectManagement\Database\Factories;

use App\Modules\ProjectManagement\Models\Project;
use App\Modules\ProjectManagement\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
final class TaskFactory extends Factory
{
    protected $model = Task::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'status' => 'todo',
            'priority' => 'medium',
            'start_date' => now()->toDateString(),
            'due_date' => now()->addWeek()->toDateString(),
        ];
    }

    public function forTenant(string $tenantId): static
    {
        return $this->state(fn () => [
            'project_id' => Project::factory()->forTenant($tenantId),
        ]);
    }
}
