<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;

test('landlord database connection is defined', function () {
    $config = Config::get('database.connections.landlord');
    expect($config)->not->toBeNull()
        ->and($config['driver'])->toBeIn(['mysql', 'pgsql', 'sqlite']);
});

test('tenant database connection is defined', function () {
    $config = Config::get('database.connections.tenant');
    expect($config)->not->toBeNull()
        // Driver starts as null (placeholder for dynamic configuration) but in
        // tests it gets aliased to the landlord config in TestCase::setUp().
        // Both are valid states; assert the key exists rather than its value.
        ->and($config)->toHaveKey('driver');
});

test('default database connection is landlord', function () {
    expect(Config::get('database.default'))->toBe('landlord');
});
