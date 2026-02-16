<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\CmsCore\Models\Post;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class CmsPostLibraryController extends Controller
{
    public function index(Request $request): View
    {
        abort_if(! $request->user()?->can('cms.posts.view'), 403);

        $posts = Post::query()
            ->with(['postType:id,name', 'author:id,first_name,last_name,email'])
            ->latest('updated_at')
            ->paginate(20);

        return view('cms-core::posts', [
            'posts' => $posts,
        ]);
    }
}
