<?php

declare(strict_types=1);

namespace App\Modules\IncidentManagement\Database\Factories;

use App\Modules\IncidentManagement\Models\IncidentPriority;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<IncidentPriority>
 */
final class IncidentPriorityFactory extends Factory
{
    protected $model = IncidentPriority::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->word();

        return [
            'uuid' => (string) Str::uuid(),
            'tenant_id' => fake()->uuid(),
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'level' => fake()->numberBetween(1, 5),
            'response_time_minutes' => fake()->numberBetween(60, 240),
            'resolution_time_minutes' => fake()->numberBetween(240, 1440),
            'is_active' => true,
        ];
    }

    public function forTenant(string $tenantId): static
    {
        return $this->state(fn () => [
            'tenant_id' => $tenantId,
        ]);
    }
}
