<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;

final class CmsPublicSitemapController extends Controller
{
    public function index(): Response
    {
        $path = storage_path('app/public/sitemap.xml');

        if (! file_exists($path)) {
            Artisan::call('cms:generate-sitemap');
        }

        $content = file_exists($path)
            ? (string) file_get_contents($path)
            : '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>';

        return response($content, 200, [
            'Content-Type' => 'application/xml',
        ]);
    }
}
