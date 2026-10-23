<?php

declare(strict_types=1);

use Illuminate\Cache\DatabaseStore;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Facade;

it('provides landlord cache and locks and preserves existing tables through rollback', function (bool $cacheExists, bool $locksExist): void {
    $original = Facade::getFacadeApplication();
    $db = new Manager;
    $db->addConnection(['driver' => 'sqlite', 'database' => ':memory:'], 'landlord');
    $db->addConnection(['driver' => 'sqlite', 'database' => ':memory:'], 'default');
    $db->getContainer()->instance('db.schema', $db->getConnection()->getSchemaBuilder());
    $db->getContainer()->instance('db', $db->getDatabaseManager());
    Facade::clearResolvedInstances();
    Facade::setFacadeApplication($db->getContainer());
    $connection = $db->getConnection('landlord');
    $schema = $connection->getSchemaBuilder();

    try {
        foreach (['cache' => $cacheExists, 'cache_locks' => $locksExist] as $table => $exists) {
            if ($exists) {
                $schema->create($table, function (Blueprint $blueprint) use ($table): void {
                    $blueprint->string('key')->primary();
                    $blueprint->text($table === 'cache' ? 'value' : 'owner');
                    $blueprint->integer('expiration');
                });
                $connection->table($table)->insert(['key' => 'existing', $table === 'cache' ? 'value' : 'owner' => 'preserve', 'expiration' => time() + 600]);
            }
        }
        $path = dirname(__DIR__, 2).'/database/migrations/landlord/2026_10_23_160947_create_shared_cache_tables.php';
        $migration = require $path;
        $migration->up();
        $migration->up();
        $store = new DatabaseStore($connection, 'cache', 'test:');
        expect($store->put('shared', 'available', 60))->toBeTrue();
        expect($store->get('shared'))->toBe('available');
        $lock = $store->lock('shared', 60);
        expect($lock->get())->toBeTrue();
        expect($store->lock('shared', 60)->get())->toBeFalse();
        expect($lock->release())->toBeTrue();
        expect($db->getConnection()->getSchemaBuilder()->hasTable('cache'))->toBeFalse();

        // Reload to model rollback in a different process.
        $rollback = require $path;
        $rollback->down();
        expect($schema->hasTable('cache'))->toBeTrue();
        expect($schema->hasTable('cache_locks'))->toBeTrue();
        foreach (['cache' => $cacheExists, 'cache_locks' => $locksExist] as $table => $exists) {
            if ($exists) {
                expect($connection->table($table)->where('key', 'existing')->exists())->toBeTrue();
            }
        }
    } finally {
        $db->getDatabaseManager()->disconnect('landlord');
        $db->getDatabaseManager()->disconnect('default');
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($original);
    }
})->with([[false, false], [true, false], [false, true], [true, true]]);

it('keeps queue reservations longer than job timeouts and accepts an override', function (): void {
    $key = 'QUEUE_RETRY_AFTER';
    $original = $_ENV[$key] ?? null;
    try {
        unset($_ENV[$key]);
        $config = require dirname(__DIR__, 2).'/config/queue.php';
        $timeouts = [60];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/app')) as $file) {
            if ($file->getExtension() === 'php') {
                preg_match_all('/\$timeout\s*=\s*(\d+)/', file_get_contents($file->getPathname()), $matches);
                array_push($timeouts, ...array_map('intval', $matches[1]));
            }
        }
        foreach (['database', 'redis', 'beanstalkd'] as $driver) {
            expect($config['connections'][$driver]['retry_after'])->toBeGreaterThan(max($timeouts));
        }
        $_ENV[$key] = '240';
        $config = require dirname(__DIR__, 2).'/config/queue.php';
        foreach (['database', 'redis', 'beanstalkd'] as $driver) {
            expect($config['connections'][$driver]['retry_after'])->toBe(240);
        }
    } finally {
        if ($original === null) {
            unset($_ENV[$key]);
        } else {
            $_ENV[$key] = $original;
        }
    }
});
