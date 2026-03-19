<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\CmsCore\Models\Category;
use App\Modules\CmsCore\Models\Post;
use App\Modules\CmsCore\Models\Tag;
use App\Modules\CmsCore\Services\CmsThemeService;
use App\Modules\CmsCore\Services\CmsViewResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class CmsPublicPostController extends Controller
{
    public function __construct(
        private readonly CmsThemeService $theme,
        private readonly CmsViewResolver $viewResolver
    ) {}

    public function archive(Request $request): View
    {
        $posts = Post::query()
            ->published()
            ->forType('post')
            ->with(['author:uuid,first_name,last_name', 'featuredMedia', 'categories:id,name,slug'])
            ->latest('published_at')
            ->paginate(12)
            ->withQueryString();

        return view($this->viewResolver->resolve('archive'), [
            'posts' => $posts,
            'title' => 'News',
            'theme' => $this->theme,
            'cmsLayout' => $this->viewResolver->resolveLayout(),
        ]);
    }

    public function show(string $slug): View
    {
        $post = Post::query()
            ->published()
            ->forType('post')
            ->where('slug', $slug)
            ->with([
                'author:uuid,first_name,last_name',
                'featuredMedia',
                'categories:id,name,slug',
                'tags:id,name,slug',
                'meta',
            ])
            ->firstOrFail();

        $relatedPosts = Post::query()
            ->published()
            ->forType('post')
            ->where('id', '!=', $post->id)
            ->whereHas('categories', function ($query) use ($post): void {
                $query->whereIn('categories.id', $post->categories->pluck('id'));
            })
            ->with(['featuredMedia', 'author:uuid,first_name,last_name'])
            ->latest('published_at')
            ->limit(3)
            ->get();

        return view($this->viewResolver->resolve('single'), [
            'post' => $post,
            'relatedPosts' => $relatedPosts,
            'theme' => $this->theme,
            'cmsLayout' => $this->viewResolver->resolveLayout(),
        ]);
    }

    public function byCategory(string $categorySlug): View
    {
        $category = Category::query()
            ->where('slug', $categorySlug)
            ->where('is_active', true)
            ->firstOrFail();

        $posts = Post::query()
            ->published()
            ->forType('post')
            ->whereHas('categories', function ($query) use ($category): void {
                $query->where('categories.id', $category->id);
            })
            ->with(['author:uuid,first_name,last_name', 'featuredMedia', 'categories:id,name,slug'])
            ->latest('published_at')
            ->paginate(12)
            ->withQueryString();

        return view($this->viewResolver->resolve('archive'), [
            'posts' => $posts,
            'title' => $category->name,
            'category' => $category,
            'theme' => $this->theme,
            'cmsLayout' => $this->viewResolver->resolveLayout(),
        ]);
    }

    public function byTag(string $tagSlug): View
    {
        $tag = Tag::query()
            ->where('slug', $tagSlug)
            ->firstOrFail();

        $posts = Post::query()
            ->published()
            ->forType('post')
            ->whereHas('tags', function ($query) use ($tag): void {
                $query->where('tags.id', $tag->id);
            })
            ->with(['author:uuid,first_name,last_name', 'featuredMedia', 'categories:id,name,slug'])
            ->latest('published_at')
            ->paginate(12)
            ->withQueryString();

        return view($this->viewResolver->resolve('archive'), [
            'posts' => $posts,
            'title' => $tag->name,
            'tag' => $tag,
            'theme' => $this->theme,
            'cmsLayout' => $this->viewResolver->resolveLayout(),
        ]);
    }
}
