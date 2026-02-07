<?php

declare(strict_types=1);

namespace App\Modules\IncidentManagement\Database\Factories;

use App\Modules\IncidentManagement\Models\IncidentStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<IncidentStatus>
 */
final class IncidentStatusFactory extends Factory
{
    protected $model = IncidentStatus::class;

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
            'sort_order' => fake()->numberBetween(0, 10),
            'is_terminal' => false,
            'is_default' => false,
        ];
    }

    public function forTenant(string $tenantId): static
    {
        return $this->state(fn () => [
            'tenant_id' => $tenantId,
        ]);
    }
}
