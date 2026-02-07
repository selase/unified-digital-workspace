<?php

declare(strict_types=1);

namespace App\Modules\DocumentManagement\Database\Factories;

use App\Modules\DocumentManagement\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Document>
 */
final class DocumentFactory extends Factory
{
    protected $model = Document::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->sentence(3);

        return [
            'tenant_id' => fake()->uuid(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1, 9999),
            'status' => 'draft',
            'visibility' => [
                'is_private' => false,
                'tenant_wide' => false,
            ],
            'owner_id' => fake()->uuid(),
            'category' => 'policy',
            'tags' => ['tag1'],
        ];
    }

    public function forTenant(string $tenantId): static
    {
        return $this->state(fn () => ['tenant_id' => $tenantId]);
    }
}
