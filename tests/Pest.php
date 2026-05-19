<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

uses(
    TestCase::class,
    Illuminate\Foundation\Testing\RefreshDatabase::class,
)->in('Feature', 'Unit');

uses(
    TestCase::class,
)->in('Infrastructure');

afterEach(function () {
    Mockery::close();
});

// Note: global per-test setup that needs to run for every Feature/Unit test
// belongs in tests/TestCase::setUp(), NOT here. Pest's beforeEach() in this
// file is keyed on tests/Pest.php as its filename — Pest's TestSuite looks
// up beforeEach hooks by the test's own filename, so a beforeEach here only
// applies to tests defined inside this file (none). The `->in('Feature', 'Unit')`
// qualifier works for uses() but silently no-ops on beforeEach (it falls
// through __call and lands on TestCall::__call() which doesn't change scope).

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', fn () => $this->toBe(1));

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something(): void
{
    // ..
}

function setActiveTenantForTest(?User $user = null, array $overrides = []): Tenant
{
    $tenant = Tenant::factory()->create($overrides + [
        'name' => 'Test Tenant',
        'slug' => 'test-tenant-'.uniqid(),
        'isolation_mode' => 'shared',
    ]);

    if ($user) {
        $tenant->users()->attach($user->id);
    }

    Session::put('active_tenant_id', $tenant->id);
    setPermissionsTeamId($tenant->id);
    app(App\Services\Tenancy\TenantContext::class)->setTenant($tenant);

    // Configure the `tenant` DB connection — without this, models that use
    // the BelongsToTenant trait blow up with "Unsupported driver []" because
    // config('database.connections.tenant.driver') is null until
    // TenantDatabaseManager::configure() is called.
    app(App\Services\Tenancy\TenantDatabaseManager::class)->configure($tenant);

    return $tenant;
}

function refreshTenantDatabases(): void
{
    Config::set('database.connections.tenant', Config::get('database.connections.landlord'));

    $landlordPath = database_path('migrations/landlord');
    $tenantPath = database_path('migrations/tenant');

    Artisan::call('migrate', [
        '--database' => 'landlord',
        '--path' => $landlordPath,
        '--realpath' => true,
    ]);

    Artisan::call('migrate', [
        '--database' => 'tenant',
        '--path' => $tenantPath,
        '--realpath' => true,
    ]);
}

/**
 * Drop a dynamically-provisioned tenant postgres database. Uses a raw PDO
 * because DROP DATABASE forbids running inside a transaction block, and
 * RefreshDatabase keeps the landlord connection in one for the test lifetime.
 */
function dropTenantDatabase(string $name): void
{
    $landlord = config('database.connections.landlord');
    // Preserve sslmode so this still works when the landlord requires TLS.
    $dsn = sprintf(
        'pgsql:host=%s;port=%s;dbname=%s;sslmode=%s',
        $landlord['host'] ?? '127.0.0.1',
        $landlord['port'] ?? 5432,
        $landlord['database'],
        $landlord['sslmode'] ?? 'prefer'
    );
    $pdo = new PDO($dsn, $landlord['username'] ?? null, $landlord['password'] ?? null);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Purge Laravel's tracked tenant connection so its PDO releases the DB
    // before we DROP, then use FORCE (pg13+) to evict any other holders.
    DB::purge('tenant');
    $pdo->exec(sprintf('DROP DATABASE IF EXISTS "%s" WITH (FORCE)', $name));
}

/**
 * Set up a shared-isolation tenant for module integration tests. Module
 * tables are already migrated onto the tenant connection (aliased to
 * landlord postgres) by TestCase::setUp(), so no per-tenant DB or
 * module-specific migrate call is needed.
 *
 * The second slot is kept as `null` for backwards compatibility with
 * callers that destructure `[$tenant, $tenantDb]`.
 *
 * @return array{0: Tenant, 1: null}
 */
function setupIncidentTenantConnection(?User $user = null): array
{
    return [setActiveTenantForTest($user), null];
}

/**
 * @return array{0: Tenant, 1: null}
 */
function setupMemoTenantConnection(?User $user = null): array
{
    return [setActiveTenantForTest($user), null];
}
