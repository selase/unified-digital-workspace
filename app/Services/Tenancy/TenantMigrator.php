<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use Illuminate\Support\Facades\Artisan;

/**
 * Intentionally not declared final. TenantsMigrateCommandTest mocks this
 * via Mockery::mock(TenantMigrator::class); Pint's final_class rule
 * skips this file via pint.json.
 */
class TenantMigrator
{
    /**
     * @return array{exitCode: int, output: string}
     */
    public function migrate(string $database, string $path, bool $force = false): array
    {
        $exitCode = Artisan::call('migrate', [
            '--database' => $database,
            '--path' => $path,
            '--force' => $force,
        ]);

        return [
            'exitCode' => $exitCode,
            'output' => Artisan::output(),
        ];
    }
}
