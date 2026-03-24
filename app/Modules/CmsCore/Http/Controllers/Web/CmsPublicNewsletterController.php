<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\CmsCore\Models\Post;
use App\Modules\CmsCore\Services\CmsThemeService;
use App\Modules\CmsCore\Services\CmsViewResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class CmsPublicNewsletterController extends Controller
{
    public function __construct(
        private readonly CmsThemeService $theme,
        private readonly CmsViewResolver $viewResolver
    ) {}

    public function archive(Request $request): View
    {
        $posts = Post::query()
            ->published()
            ->forType('newsletter')
            ->with(['author:uuid,first_name,last_name', 'featuredMedia', 'media'])
            ->latest('published_at')
            ->paginate(12)
            ->withQueryString();

        return view($this->viewResolver->resolve('newsletters.archive'), [
            'posts' => $posts,
            'title' => 'Newsletters',
            'theme' => $this->theme,
            'cmsLayout' => $this->viewResolver->resolveLayout(),
        ]);
    }

    public function show(string $slug): View
    {
        $post = Post::query()
            ->published()
            ->forType('newsletter')
            ->where('slug', $slug)
            ->with(['author:uuid,first_name,last_name', 'featuredMedia', 'media', 'meta'])
            ->firstOrFail();

        return view($this->viewResolver->resolve('newsletters.single'), [
            'post' => $post,
            'theme' => $this->theme,
            'cmsLayout' => $this->viewResolver->resolveLayout(),
        ]);
    }
}
