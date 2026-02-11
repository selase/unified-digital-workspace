<?php

declare(strict_types=1);

namespace App\Modules\Memos\Database\Factories;

use App\Models\User;
use App\Modules\Memos\Models\Memo;
use App\Modules\Memos\Models\MemoMinute;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemoMinute>
 */
final class MemoMinuteFactory extends Factory
{
    protected $model = MemoMinute::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'memo_id' => Memo::factory(),
            'tenant_id' => fake()->uuid(),
            'author_id' => User::factory(),
            'body' => fake()->sentence(12),
        ];
    }

    public function forTenant(string $tenantId): static
    {
        return $this->state(fn () => [
            'tenant_id' => $tenantId,
        ]);
    }
}
