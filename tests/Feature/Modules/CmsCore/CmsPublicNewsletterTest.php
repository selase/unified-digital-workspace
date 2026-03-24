<?php

declare(strict_types=1);

use App\Modules\CmsCore\Http\Controllers\Web\CmsPublicNewsletterController;
use App\Modules\CmsCore\Http\Controllers\Web\CmsPublicPostController;

test('newsletter controller class exists and has expected methods', function () {
    expect(class_exists(CmsPublicNewsletterController::class))->toBeTrue();

    $reflection = new ReflectionClass(CmsPublicNewsletterController::class);

    expect($reflection->hasMethod('archive'))->toBeTrue()
        ->and($reflection->hasMethod('show'))->toBeTrue();
});

test('post controller archive method accepts search parameter', function () {
    $reflection = new ReflectionMethod(CmsPublicPostController::class, 'archive');
    $params = $reflection->getParameters();

    expect($params[0]->getName())->toBe('request')
        ->and($params[0]->getType()->getName())->toBe('Illuminate\Http\Request');
});

test('newsletter routes are registered', function () {
    $routes = collect(app('router')->getRoutes()->getRoutesByMethod()['GET'] ?? []);

    $newsletterRoutes = $routes->filter(fn ($route) => str_contains($route->uri(), 'newsletters'));

    expect($newsletterRoutes)->not->toBeEmpty();
});
