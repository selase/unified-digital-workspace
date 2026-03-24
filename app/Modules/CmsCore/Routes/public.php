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

// Catch-all for static pages — must be last in this file.
// Site module routes registered via CmsSiteServiceProvider take priority
// because they use explicit paths that match before this wildcard.
Route::get('/{slug}', [App\Modules\CmsCore\Http\Controllers\Web\CmsPublicPageController::class, 'show'])->name('pages.show');
