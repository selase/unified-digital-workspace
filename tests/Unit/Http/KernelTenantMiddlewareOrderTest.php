<?php

declare(strict_types=1);

use App\Http\Kernel;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Routing\Middleware\SubstituteBindings;

it('runs tenant resolution before substitute bindings for web and api middleware groups', function (): void {
    $kernel = app(Kernel::class);
    $reflection = new ReflectionClass($kernel);
    $property = $reflection->getProperty('middlewareGroups');
    $property->setAccessible(true);

    /** @var array<string, array<int, string>> $groups */
    $groups = $property->getValue($kernel);

    $web = $groups['web'];
    $api = $groups['api'];

    expect(array_search(ResolveTenant::class, $web, true))->toBeLessThan(array_search(SubstituteBindings::class, $web, true));
    expect(array_search(ResolveTenant::class, $api, true))->toBeLessThan(array_search(SubstituteBindings::class, $api, true));
});
