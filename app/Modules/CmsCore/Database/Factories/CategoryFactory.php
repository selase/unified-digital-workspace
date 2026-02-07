<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Database\Factories;

use App\Modules\CmsCore\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
final class CategoryFactory extends Factory
{
    protected $model = Category::class;

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
            'parent_id' => null,
            'sort_order' => 0,
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
