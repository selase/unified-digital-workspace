<?php

declare(strict_types=1);

namespace App\Modules\Forums\Database\Factories;

use App\Modules\Forums\Models\ForumMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ForumMessage>
 */
final class ForumMessageFactory extends Factory
{
    protected $model = ForumMessage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fake()->uuid(),
            'sender_id' => fake()->uuid(),
            'subject' => fake()->sentence(5),
            'body' => fake()->paragraphs(2, true),
            'visibility' => [
                'tenant_wide' => false,
            ],
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
