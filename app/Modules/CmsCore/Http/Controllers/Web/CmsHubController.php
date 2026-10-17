<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\CmsCore\Models\Category;
use App\Modules\CmsCore\Models\Media;
use App\Modules\CmsCore\Models\Post;
use App\Modules\CmsCore\Models\PostType;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

final class CmsHubController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        abort_if(! $user?->can('cms.posts.view'), 403);

        $contentTypes = PostType::query()
            ->where('is_active', true)
            ->withCount([
                'posts',
                'posts as published_posts_count' => fn ($query) => $query->where('status', 'published'),
            ])
            ->orderBy('name')
            ->get(['id', 'name', 'description']);

        $shortcuts = collect([
            ['label' => 'All content', 'hint' => 'Browse and edit every post, page and slide', 'route' => 'cms-core.posts.index', 'permission' => 'cms.posts.view'],
            ['label' => 'Write something new', 'hint' => 'Start a post, page or slide', 'route' => 'cms-core.posts.create', 'permission' => 'cms.posts.create'],
            ['label' => 'Media library', 'hint' => 'Upload and manage images and files', 'route' => 'cms-core.media.index', 'permission' => 'cms.media.view'],
            ['label' => 'Categories', 'hint' => 'Group related content', 'route' => 'cms-core.categories.index', 'permission' => 'cms.categories.view'],
            ['label' => 'Tags', 'hint' => 'Label content by topic', 'route' => 'cms-core.tags.index', 'permission' => 'cms.tags.view'],
            ['label' => 'Menus', 'hint' => 'Website navigation', 'route' => 'cms-core.menus.index', 'permission' => 'cms.menus.view'],
            ['label' => 'Website settings', 'hint' => 'Theme, contact details and SEO', 'route' => 'cms-core.settings.index', 'permission' => 'cms.settings.manage'],
        ])
            ->filter(fn (array $shortcut): bool => Route::has($shortcut['route']) && $user->can($shortcut['permission']))
            ->map(fn (array $shortcut): array => $shortcut + ['url' => route($shortcut['route'])])
            ->values();

        $metrics = [
            'total_posts' => Post::query()->count(),
            'published_posts' => Post::query()->where('status', 'published')->count(),
            'draft_posts' => Post::query()->where('status', 'draft')->count(),
            'scheduled_posts' => Post::query()->where('status', 'scheduled')->count(),
            'categories_count' => Category::query()->count(),
            'media_count' => $user->can('cms.media.view') ? Media::query()->count() : 0,
        ];

        $recentPosts = Post::query()
            ->with([
                'postType:id,name,slug',
                'author:uuid,first_name,last_name,email',
            ])
            ->latest('updated_at')
            ->limit(6)
            ->get();

        return view('cms-core::index', [
            'contentTypes' => $contentTypes,
            'shortcuts' => $shortcuts,
            'mediaCount' => $user->can('cms.media.view') ? Media::query()->count() : null,
            'canCreate' => $user->can('cms.posts.create'),
            'apiLinks' => $user->can('cms.settings.manage') ? $this->apiLinks() : collect(),
            'metrics' => $metrics,
            'recentPosts' => $recentPosts,
        ]);
    }

    /**
     * Developer-facing API entry points, shown only to people who manage the website.
     *
     * @return Collection<int, array{label: string, route: string, url: string}>
     */
    private function apiLinks(): Collection
    {
        return collect([
            ['label' => 'API root', 'route' => 'api.cms-core.index'],
            ['label' => 'Posts', 'route' => 'api.cms-core.v1.posts.index'],
            ['label' => 'Post types', 'route' => 'api.cms-core.v1.post-types.index'],
            ['label' => 'Categories', 'route' => 'api.cms-core.v1.categories.index'],
            ['label' => 'Tags', 'route' => 'api.cms-core.v1.tags.index'],
            ['label' => 'Media library', 'route' => 'api.cms-core.v1.media.index'],
            ['label' => 'Menus', 'route' => 'api.cms-core.v1.menus.index'],
        ])
            ->filter(fn (array $apiLink): bool => Route::has($apiLink['route']))
            ->map(fn (array $apiLink): array => $apiLink + ['url' => route($apiLink['route'])])
            ->values();
    }
}
