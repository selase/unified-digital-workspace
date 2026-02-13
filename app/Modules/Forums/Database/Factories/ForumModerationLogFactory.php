<?php

declare(strict_types=1);

namespace App\Modules\Forums\Database\Factories;

use App\Modules\Forums\Models\ForumModerationLog;
use App\Modules\Forums\Models\ForumThread;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ForumModerationLog>
 */
final class ForumModerationLogFactory extends Factory
{
    protected $model = ForumModerationLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fake()->uuid(),
            'thread_id' => ForumThread::factory(),
            'post_id' => null,
            'moderator_id' => fake()->uuid(),
            'action' => fake()->randomElement(['pin', 'lock', 'unlock', 'flag']),
            'reason' => fake()->optional()->sentence(),
            'metadata' => ['source' => 'factory'],
            'created_at' => now(),
        ];
    }

    public function forTenant(string $tenantId): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $tenantId,
        ]);
    }
}
