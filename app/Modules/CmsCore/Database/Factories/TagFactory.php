<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Database\Factories;

use App\Modules\CmsCore\Models\Tag;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tag>
 */
final class TagFactory extends Factory
{
    protected $model = Tag::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->word();

        return [
            'uuid' => (string) Str::uuid(),
            'tenant_id' => fake()->uuid(),
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
        ];
    }

    public function forTenant(string $tenantId): static
    {
        return $this->state(fn () => [
            'tenant_id' => $tenantId,
        ]);
    }
}
