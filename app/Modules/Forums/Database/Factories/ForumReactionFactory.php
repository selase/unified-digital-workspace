<?php

declare(strict_types=1);

namespace App\Modules\Forums\Database\Factories;

use App\Modules\Forums\Models\ForumPost;
use App\Modules\Forums\Models\ForumReaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ForumReaction>
 */
final class ForumReactionFactory extends Factory
{
    protected $model = ForumReaction::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fake()->uuid(),
            'post_id' => ForumPost::factory(),
            'user_id' => fake()->uuid(),
            'type' => fake()->randomElement(['like', 'celebrate', 'insightful']),
        ];
    }

    public function forTenant(string $tenantId): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $tenantId,
        ]);
    }
}
