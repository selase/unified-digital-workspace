<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// Define custom public routes here.
// These routes are registered at both /site prefix (subdomain) and root (custom domain).
// They take priority over the CMS Core catch-all /{slug} route.
//
// Example:
// Route::get('/contact', [ContactController::class, 'show'])->name('contact');
// Route::post('/contact', [ContactController::class, 'submit'])->name('contact.submit');