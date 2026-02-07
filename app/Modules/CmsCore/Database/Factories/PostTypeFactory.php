<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Database\Factories;

use App\Modules\CmsCore\Models\PostType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PostType>
 */
final class PostTypeFactory extends Factory
{
    protected $model = PostType::class;

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
            'icon' => null,
            'supports' => [
                'excerpt' => true,
                'featured_media' => true,
            ],
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
