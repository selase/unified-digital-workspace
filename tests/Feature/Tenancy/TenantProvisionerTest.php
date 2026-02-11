<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantDatabaseManager;
use App\Services\Tenancy\TenantProvisioner;
use Illuminate\Support\Facades\Schema;

it('migrates enabled module tables during provisioning', function (): void {
    if (! file_exists(storage_path('tenants'))) {
        mkdir(storage_path('tenants'), 0755, true);
    }

    $tenant = Tenant::factory()->create([
        'name' => 'Provisioned Tenant',
        'slug' => 'provisioned-tenant',
        'isolation_mode' => 'db_per_tenant',
        'db_driver' => 'sqlite',
    ]);

    app(ModuleManager::class)->enableForTenant('memos', $tenant);

    app(TenantProvisioner::class)->provision($tenant);

    app(TenantDatabaseManager::class)->configure($tenant);

    expect(Schema::connection('tenant')->hasTable('memos'))->toBeTrue();
});
