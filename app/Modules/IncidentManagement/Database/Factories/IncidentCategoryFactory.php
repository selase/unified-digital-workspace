<?php

declare(strict_types=1);

namespace App\Modules\IncidentManagement\Database\Factories;

use App\Modules\IncidentManagement\Models\IncidentCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<IncidentCategory>
 */
final class IncidentCategoryFactory extends Factory
{
    protected $model = IncidentCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->words(2, true);

        return [
            'uuid' => (string) Str::uuid(),
            'tenant_id' => fake()->uuid(),
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
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
