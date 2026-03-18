<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\CmsCore\Models\Post;
use App\Modules\CmsCore\Services\CmsThemeService;
use App\Modules\CmsCore\Services\CmsViewResolver;
use Illuminate\Contracts\View\View;

final class CmsPublicHomeController extends Controller
{
    public function __construct(
        private readonly CmsThemeService $theme,
        private readonly CmsViewResolver $viewResolver
    ) {}

    public function index(): View
    {
        $homepage = Post::query()
            ->published()
            ->forType('page')
            ->where('slug', 'home')
            ->with(['featuredMedia'])
            ->first();

        $recentPosts = Post::query()
            ->published()
            ->forType('post')
            ->with(['author:uuid,first_name,last_name', 'featuredMedia', 'categories:id,name,slug'])
            ->latest('published_at')
            ->limit(6)
            ->get();

        return view($this->viewResolver->resolve('home'), [
            'homepage' => $homepage,
            'recentPosts' => $recentPosts,
            'theme' => $this->theme,
            'cmsLayout' => $this->viewResolver->resolveLayout(),
        ]);
    }
}
