<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\CmsCore\Models\Post;
use App\Modules\CmsCore\Models\PostMeta;
use App\Modules\CmsCore\Services\CmsThemeService;
use App\Modules\CmsCore\Services\CmsViewResolver;
use Illuminate\Contracts\View\View;

final class CmsPublicPageController extends Controller
{
    public function __construct(
        private readonly CmsThemeService $theme,
        private readonly CmsViewResolver $viewResolver
    ) {}

    public function show(string $slug): View
    {
        $page = Post::query()
            ->published()
            ->forType('page')
            ->where('slug', $slug)
            ->with(['featuredMedia', 'author:uuid,first_name,last_name', 'children' => function ($query): void {
                $query->published()->orderBy('sort_order');
            }])
            ->firstOrFail();

        // Check for a page template override via PostMeta
        $pageTemplate = PostMeta::query()
            ->where('post_id', $page->id)
            ->where('key', 'page_template')
            ->value('value');

        $templateSlug = is_array($pageTemplate) ? ($pageTemplate[0] ?? null) : $pageTemplate;

        $viewName = $templateSlug
            ? $this->viewResolver->resolve("templates.{$templateSlug}")
            : $this->viewResolver->resolve('page');

        return view($viewName, [
            'page' => $page,
            'theme' => $this->theme,
            'cmsLayout' => $this->viewResolver->resolveLayout(),
        ]);
    }
}
