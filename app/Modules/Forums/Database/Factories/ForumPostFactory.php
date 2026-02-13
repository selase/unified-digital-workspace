<?php

declare(strict_types=1);

namespace App\Modules\Forums\Database\Factories;

use App\Modules\Forums\Models\ForumPost;
use App\Modules\Forums\Models\ForumThread;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ForumPost>
 */
final class ForumPostFactory extends Factory
{
    protected $model = ForumPost::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fake()->uuid(),
            'thread_id' => ForumThread::factory(),
            'user_id' => fake()->uuid(),
            'parent_id' => null,
            'body' => fake()->paragraph(),
            'is_best_answer' => false,
        ];
    }

    public function forTenant(string $tenantId): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $tenantId,
        ]);
    }
}
