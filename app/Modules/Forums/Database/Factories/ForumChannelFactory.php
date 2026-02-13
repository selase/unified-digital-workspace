<?php

declare(strict_types=1);

namespace App\Modules\Forums\Database\Factories;

use App\Modules\Forums\Models\ForumChannel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ForumChannel>
 */
final class ForumChannelFactory extends Factory
{
    protected $model = ForumChannel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->sentence(3);

        return [
            'tenant_id' => fake()->uuid(),
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->optional()->sentence(),
            'visibility' => [
                'tenant_wide' => true,
            ],
            'is_locked' => false,
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }

    public function forTenant(string $tenantId): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $tenantId,
        ]);
    }
}
