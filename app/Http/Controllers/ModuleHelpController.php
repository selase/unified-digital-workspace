<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\CmsCore\Support\ContentSanitizer;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

final class ModuleHelpController extends Controller
{
    public function __invoke(): View
    {
        $path = base_path('docs/MODULE_QUICKSTARTS.md');
        $guideHtml = File::exists($path)
            ? ContentSanitizer::markdown(File::get($path))
            : null;

        return view('help.modules', compact('guideHtml'));
    }
}
