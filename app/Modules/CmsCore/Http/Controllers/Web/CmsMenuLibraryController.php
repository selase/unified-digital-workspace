<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\CmsCore\Models\Menu;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class CmsMenuLibraryController extends Controller
{
    public function index(Request $request): View
    {
        abort_if(! $request->user()?->can('cms.menus.view'), 403);

        $menus = Menu::query()
            ->withCount('items')
            ->latest('updated_at')
            ->paginate(20);

        return view('cms-core::menus', [
            'menus' => $menus,
        ]);
    }
}
