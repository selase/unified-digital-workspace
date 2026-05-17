<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected $connectionsToTransact = ['landlord'];

    /**
     * Has the per-process test DB been migrated yet? Migrations are
     * idempotent but Artisan::call is slow, so we only run them once.
     */
    private static bool $databaseMigrated = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSafeTestDatabase();

        if (str_contains((string) config('app.url'), '.test')) {
            config(['session.domain' => '.unified-digital-workspace.test']);
            config(['app.url' => 'http://unified-digital-workspace.test']);
        }

        if (! self::$databaseMigrated) {
            $this->migrateAllSchemas();
            self::$databaseMigrated = true;
        }

        $this->aliasTenantConnectionToLandlord();
    }

    /**
     * In production, shared-isolation tenants conceptually use the same DB as
     * landlord; in tests, RefreshDatabase only transacts `landlord` (we tried
     * adding `tenant` to $connectionsToTransact — but the two are separate PDO
     * instances, each with its own transaction, so a row written via landlord's
     * connection isn't visible to tenant's, causing FK violations on inserts
     * that reference the tenants table. Pointing the tenant connection's PDO
     * at the landlord PDO instance unifies the transaction view, so models that
     * use BelongsToTenant see freshly-created tenants without committing.
     */
    private function aliasTenantConnectionToLandlord(): void
    {
        // Mirror landlord config onto tenant so the connection can resolve
        // a driver. Code under test (e.g. TenantDatabaseManager::configureShared)
        // also does this at runtime; doing it again here is a safe override.
        Config::set(
            'database.connections.tenant',
            Config::get('database.connections.landlord'),
        );

        // Listen for any (re)establishment of the tenant connection and
        // immediately swap its PDO for landlord's. Without this, every call
        // to DB::purge('tenant') (e.g. via TenantDatabaseManager::configureShared
        // when a test calls TenantContext::setTenant) would discard our PDO
        // alias and build a fresh PDO whose transaction is invisible to the
        // landlord PDO — causing FK violations on inserts into module tables
        // that reference the tenants table created via landlord.
        Event::listen(ConnectionEstablished::class, function (ConnectionEstablished $event): void {
            if ($event->connectionName !== 'tenant') {
                return;
            }

            // Only alias when the tenant connection is genuinely shared with
            // landlord — same driver, same database. Tests that exercise the
            // dedicated-isolation path (e.g. setupIncidentTenantConnection
            // which configures sqlite) need their separate PDO; aliasing
            // postgres over sqlite would corrupt the query dialect.
            $landlordConfig = Config::get('database.connections.landlord');
            $tenantConfig = Config::get('database.connections.tenant');

            if (($tenantConfig['driver'] ?? null) !== ($landlordConfig['driver'] ?? null)) {
                return;
            }
            if (($tenantConfig['database'] ?? null) !== ($landlordConfig['database'] ?? null)) {
                return;
            }

            $landlord = DB::connection('landlord');
            $event->connection->setPdo($landlord->getPdo());
            $event->connection->setReadPdo($landlord->getReadPdo());
        });

        // Force the tenant connection to (re)establish now so the listener
        // wires it up before the first query runs.
        DB::purge('tenant');
        DB::connection('tenant')->getPdo();
    }

    /**
     * Run landlord, tenant and every module's tenant-scoped migrations on
     * the test database. The `tenant` connection mirrors `landlord` in the
     * shared-isolation tests (default for almost every fixture), so module
     * tables end up alongside landlord tables in the same physical DB.
     */
    private function migrateAllSchemas(): void
    {
        Config::set(
            'database.connections.tenant',
            Config::get('database.connections.landlord'),
        );

        $paths = [
            database_path('migrations/landlord') => 'landlord',
            database_path('migrations/tenant') => 'tenant',
        ];

        foreach (glob(app_path('Modules/*/Database/Migrations'), GLOB_ONLYDIR) ?: [] as $modulePath) {
            $paths[$modulePath] = 'tenant';
        }

        foreach ($paths as $path => $connection) {
            Artisan::call('migrate', [
                '--database' => $connection,
                '--path' => $path,
                '--realpath' => true,
                '--force' => true,
            ]);
        }
    }

    /**
     * Refuse to run if the configured DB doesn't look like a test database.
     * Prevents a misconfigured env from pointing the suite at the dev or prod
     * database and wiping real data via RefreshDatabase / migrate:fresh.
     */
    private function assertSafeTestDatabase(): void
    {
        $database = (string) config('database.connections.landlord.database');

        // Allow in-memory sqlite for legacy / lightweight unit tests.
        if ($database === ':memory:') {
            return;
        }

        // Accept any of:
        //   - the bare `testing` name (used by GitHub Actions services)
        //   - a name ending in `_testing` (local convention, e.g. `unified_digital_workspace_testing`)
        //   - a name containing `_testing_` (parallel test suites)
        $isTestDb = $database === 'testing'
            || str_ends_with($database, '_testing')
            || str_contains($database, '_testing_');

        if (! $isTestDb) {
            throw new RuntimeException(
                "Refusing to run tests against landlord database [{$database}]: "
                .'name must be "testing", end in "_testing", or contain "_testing_". '
                .'Check phpunit.xml / .env.testing / CI env vars.'
            );
        }
    }
}
