<?php

declare(strict_types=1);

namespace App\Modules\IncidentManagement\Database\Factories;

use App\Models\User;
use App\Modules\IncidentManagement\Models\Incident;
use App\Modules\IncidentManagement\Models\IncidentCategory;
use App\Modules\IncidentManagement\Models\IncidentPriority;
use App\Modules\IncidentManagement\Models\IncidentStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Incident>
 */
final class IncidentFactory extends Factory
{
    protected $model = Incident::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fake()->uuid(),
            'title' => fake()->sentence(6),
            'description' => fake()->paragraph(),
            'category_id' => IncidentCategory::factory(),
            'priority_id' => IncidentPriority::factory(),
            'status_id' => IncidentStatus::factory(),
            'reported_by_id' => fn () => (string) User::factory()->create()->uuid,
            'reporter_id' => null,
            'reported_via' => 'internal',
            'assigned_to_id' => null,
            'due_at' => null,
            'resolved_at' => null,
            'closed_at' => null,
            'source' => 'web',
            'reference_code' => null,
            'metadata' => null,
            'impact' => null,
        ];
    }

    public function forTenant(string $tenantId): static
    {
        return $this->state(fn () => [
            'tenant_id' => $tenantId,
            'category_id' => IncidentCategory::factory()->state(['tenant_id' => $tenantId]),
            'priority_id' => IncidentPriority::factory()->state(['tenant_id' => $tenantId]),
            'status_id' => IncidentStatus::factory()->state(['tenant_id' => $tenantId]),
            'reported_by_id' => fn () => (string) User::factory()->state(['tenant_id' => $tenantId])->create()->uuid,
        ]);
    }
}
