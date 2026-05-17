<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected $connectionsToTransact = ['landlord'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSafeTestDatabase();

        if (str_contains((string) config('app.url'), '.test')) {
            config(['session.domain' => '.unified-digital-workspace.test']);
            config(['app.url' => 'http://unified-digital-workspace.test']);
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
