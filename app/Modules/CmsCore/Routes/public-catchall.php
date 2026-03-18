<?php

declare(strict_types=1);

use App\Modules\CmsCore\Http\Controllers\Web\CmsPublicPageController;
use Illuminate\Support\Facades\Route;

// Catch-all for static pages — registered via booted() callback
// to ensure site module routes take priority over this wildcard.
Route::get('/{slug}', [CmsPublicPageController::class, 'show'])->name('pages.show');
