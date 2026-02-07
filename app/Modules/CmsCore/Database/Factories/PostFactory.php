<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Database\Factories;

use App\Models\User;
use App\Modules\CmsCore\Models\Post;
use App\Modules\CmsCore\Models\PostType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Post>
 */
final class PostFactory extends Factory
{
    protected $model = Post::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->sentence(4);

        return [
            'uuid' => (string) Str::uuid(),
            'tenant_id' => fake()->uuid(),
            'post_type_id' => PostType::factory(),
            'title' => $title,
            'slug' => Str::slug($title),
            'status' => 'draft',
            'excerpt' => fake()->sentence(),
            'body' => fake()->paragraphs(3, true),
            'published_at' => null,
            'scheduled_for' => null,
            'author_id' => User::factory(),
            'editor_id' => null,
            'featured_media_id' => null,
            'parent_id' => null,
            'sort_order' => 0,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    public function forTenant(string $tenantId): static
    {
        return $this->state(fn () => [
            'tenant_id' => $tenantId,
            'post_type_id' => PostType::factory()->state(['tenant_id' => $tenantId]),
            'author_id' => User::factory()->state(['tenant_id' => $tenantId]),
        ]);
    }
}
