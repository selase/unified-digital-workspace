<?php

declare(strict_types=1);

use App\Modules\CmsCore\Http\Controllers\Web\CmsPublicHomeController;
use App\Modules\CmsCore\Http\Controllers\Web\CmsPublicNewsletterController;
use App\Modules\CmsCore\Http\Controllers\Web\CmsPublicPostController;
use App\Modules\CmsCore\Http\Controllers\Web\CmsPublicSitemapController;
use Illuminate\Support\Facades\Route;

Route::get('/', [CmsPublicHomeController::class, 'index'])->name('home');

Route::get('/news', [CmsPublicPostController::class, 'archive'])->name('posts.archive');
Route::get('/news/{slug}', [CmsPublicPostController::class, 'show'])->name('posts.show');
Route::get('/category/{categorySlug}', [CmsPublicPostController::class, 'byCategory'])->name('posts.category');
Route::get('/tag/{tagSlug}', [CmsPublicPostController::class, 'byTag'])->name('posts.tag');

Route::get('/newsletters', [CmsPublicNewsletterController::class, 'archive'])->name('newsletters.archive');
Route::get('/newsletters/{slug}', [CmsPublicNewsletterController::class, 'show'])->name('newsletters.show');

Route::get('/sitemap.xml', [CmsPublicSitemapController::class, 'index'])->name('sitemap');

// Catch-all for static pages. Registered as Route::fallback() so it only
// matches when no other route does — this prevents the /{slug} pattern
// from shadowing tenant subdomain routes, app routes, or routes that
// tests register via Route::get(...) inside beforeEach hooks.
//
// Wraps the controller in a closure because Route::fallback() doesn't bind
// URL params (no pattern); we pull the slug from the request's first
// segment. A request with more than one segment falls through to a true
// 404, matching the original behaviour of the /{slug} pattern.
Route::fallback(function (\Illuminate\Http\Request $request) {
    $segments = $request->segments();
    if (count($segments) !== 1) {
        abort(404);
    }

    return app(App\Modules\CmsCore\Http\Controllers\Web\CmsPublicPageController::class)
        ->show($segments[0]);
})->name('pages.show');
