<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\CmsCore\Models\Media;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class CmsMediaLibraryController extends Controller
{
    public function index(Request $request): View
    {
        abort_if(! $request->user()?->can('cms.media.view'), 403);

        $mediaItems = Media::query()
            ->with('uploadedBy:id,first_name,last_name,email')
            ->latest('updated_at')
            ->paginate(20);

        return view('cms-core::media', [
            'mediaItems' => $mediaItems,
        ]);
    }
}
