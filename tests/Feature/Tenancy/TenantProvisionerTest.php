<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantDatabaseManager;
use App\Services\Tenancy\TenantProvisioner;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->dedicatedTenantDbsToDrop = [];
});

afterEach(function () {
    foreach ($this->dedicatedTenantDbsToDrop ?? [] as $dbName) {
        dropTenantDatabase($dbName);
    }
});

it('migrates enabled module tables during provisioning', function (): void {
    $tenant = Tenant::factory()->create([
        'name' => 'Provisioned Tenant',
        'slug' => 'provisioned-tenant',
        'isolation_mode' => 'db_per_tenant',
        'db_driver' => 'pgsql',
    ]);

    app(ModuleManager::class)->enableForTenant('memos', $tenant);

    app(TenantProvisioner::class)->provision($tenant);

    $this->dedicatedTenantDbsToDrop[] = $tenant->fresh()->meta['database'];

    app(TenantDatabaseManager::class)->configure($tenant->fresh());

    expect(Schema::connection('tenant')->hasTable('memos'))->toBeTrue();
});

it('configures shared tenant connections to match landlord settings', function (): void {
    $landlordConfig = Config::get('database.connections.landlord');

    app(TenantDatabaseManager::class)->configureShared();

    $tenantConfig = Config::get('database.connections.tenant');

    expect($tenantConfig['driver'])->toBe($landlordConfig['driver']);
    expect($tenantConfig['database'])->toBe($landlordConfig['database']);
});

it('configures dedicated tenant connections using tenant metadata', function (): void {
    $tenant = Tenant::factory()->create([
        'isolation_mode' => 'db_per_tenant',
        'db_driver' => 'pgsql',
        'meta' => [
            'database' => 'tenant_config_test_db',
        ],
    ]);

    app(TenantDatabaseManager::class)->configure($tenant);

    $tenantConfig = Config::get('database.connections.tenant');

    expect($tenantConfig['driver'])->toBe('pgsql');
    expect($tenantConfig['database'])->toBe('tenant_config_test_db');

    Config::set('database.connections.tenant', Config::get('database.connections.landlord'));
});
