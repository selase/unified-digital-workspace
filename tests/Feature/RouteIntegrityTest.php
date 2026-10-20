<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

it('registers only callable public controller actions', function (): void {
    $invalid = [];

    foreach (Route::getRoutes() as $route) {
        $action = $route->getAction('controller');
        if (! is_string($action)) {
            continue;
        }

        [$controller, $method] = array_pad(explode('@', $action, 2), 2, '__invoke');
        if (! class_exists($controller) || ! method_exists($controller, $method)) {
            $invalid[] = $route->uri().' -> '.$action;

            continue;
        }

        $reflection = new ReflectionMethod($controller, $method);
        if (! $reflection->isPublic() || $reflection->isAbstract()) {
            $invalid[] = $route->uri().' -> '.$action;
        }
    }

    expect($invalid)->toBeEmpty(implode("\n", $invalid));
});

it('does not advertise unsupported package and lead resource actions', function (): void {
    foreach (['packages.show', 'admin.leads.create', 'admin.leads.store', 'admin.leads.edit', 'admin.leads.update'] as $name) {
        expect(Route::has($name))->toBeFalse($name);
    }

    foreach (['packages.edit', 'admin.leads.index', 'admin.leads.show', 'admin.leads.destroy', 'billing.invoices.index'] as $name) {
        expect(Route::has($name))->toBeTrue($name);
    }
});
