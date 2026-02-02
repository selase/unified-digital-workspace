<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Database\Factories;

use App\Modules\HrmsCore\Models\Organization\Center;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Center>
 */
final class CenterFactory extends Factory
{
    protected $model = Center::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->city().' Office';

        return [
            'tenant_id' => $this->faker->uuid(),
            'name' => $name,
            'slug' => Str::slug($name),
            'location' => $this->faker->address(),
            'description' => $this->faker->optional(0.5)->sentence(),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the center is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    /**
     * Set the tenant for the center.
     */
    public function forTenant(string $tenantId): static
    {
        return $this->state(fn (array $attributes): array => [
            'tenant_id' => $tenantId,
        ]);
    }
}
