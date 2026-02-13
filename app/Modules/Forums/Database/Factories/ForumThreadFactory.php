<?php

declare(strict_types=1);

namespace App\Modules\Forums\Database\Factories;

use App\Modules\Forums\Models\ForumChannel;
use App\Modules\Forums\Models\ForumThread;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ForumThread>
 */
final class ForumThreadFactory extends Factory
{
    protected $model = ForumThread::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->sentence(6);

        return [
            'tenant_id' => fake()->uuid(),
            'channel_id' => ForumChannel::factory(),
            'title' => $title,
            'slug' => Str::slug($title),
            'user_id' => fake()->uuid(),
            'status' => ForumThread::STATUS_OPEN,
            'tags' => [fake()->word(), fake()->word()],
            'metadata' => ['source' => 'factory'],
        ];
    }

    public function forTenant(string $tenantId): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $tenantId,
        ]);
    }
}
