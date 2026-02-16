<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

final class CmsHubController extends Controller
{
    public function index(Request $request): View
    {
        abort_if(! $request->user()?->can('cms.posts.view'), 403);

        $apiLinks = collect([
            ['label' => 'Posts', 'route' => 'api.cms-core.v1.posts.index'],
            ['label' => 'Post Types', 'route' => 'api.cms-core.v1.post-types.index'],
            ['label' => 'Categories', 'route' => 'api.cms-core.v1.categories.index'],
            ['label' => 'Tags', 'route' => 'api.cms-core.v1.tags.index'],
            ['label' => 'Media Library', 'route' => 'api.cms-core.v1.media.index'],
            ['label' => 'Menus', 'route' => 'api.cms-core.v1.menus.index'],
        ])->map(function (array $apiLink): array {
            $apiLink['url'] = Route::has($apiLink['route']) ? route($apiLink['route']) : null;

            return $apiLink;
        })->filter(fn (array $apiLink): bool => filled($apiLink['url']))->values();

        return view('cms-core::index', [
            'rootApiUrl' => route('api.cms-core.index'),
            'apiLinks' => $apiLinks,
            'featureCount' => count((array) config('modules.cms-core.features', [])),
            'permissionCount' => count((array) config('modules.cms-core.permissions', [])),
            'moduleVersion' => (string) config('modules.cms-core.version', '1.0.0'),
        ]);
    }
}
