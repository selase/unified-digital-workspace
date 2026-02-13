<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\TenantModule;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantDatabaseManager;
use App\Services\Tenancy\TenantMigrator;

beforeEach(function () {
    $this->tenant = Tenant::create([
        'name' => 'Test Tenant',
        'slug' => 'test-tenant',
        'isolation_mode' => 'shared',
    ]);

    app(TenantContext::class)->setTenant($this->tenant);
});

test('module:list command shows all modules', function () {
    $this->artisan('module:list')
        ->assertSuccessful()
        ->expectsOutputToContain('core');
});

test('module:list command shows tenant status when tenant option provided', function () {
    $this->artisan('module:list', ['--tenant' => $this->tenant->id])
        ->assertSuccessful()
        ->expectsOutputToContain('core');
});

test('module:list command fails for non-existent tenant', function () {
    $this->artisan('module:list', ['--tenant' => 'non-existent-uuid'])
        ->assertFailed();
});

test('module:enable command requires tenant or all-tenants option', function () {
    $this->artisan('module:enable', ['slug' => 'core'])
        ->assertFailed()
        ->expectsOutputToContain('must specify');
});

test('module:enable command enables module for tenant', function () {
    $this->artisan('module:enable', [
        'slug' => 'core',
        '--tenant' => $this->tenant->id,
    ])->assertSuccessful();

    expect(TenantModule::where('tenant_id', $this->tenant->id)
        ->where('module_slug', 'core')
        ->where('is_enabled', true)
        ->exists()
    )->toBeTrue();
});

test('module:enable command fails for non-existent module', function () {
    $this->artisan('module:enable', [
        'slug' => 'non-existent-module',
        '--tenant' => $this->tenant->id,
    ])->assertFailed();
});

test('module:enable command can enable for all tenants', function () {
    $tenant2 = Tenant::create([
        'name' => 'Second Tenant',
        'slug' => 'second-tenant',
        'isolation_mode' => 'shared',
    ]);

    $this->artisan('module:enable', [
        'slug' => 'core',
        '--all-tenants' => true,
    ])->assertSuccessful();

    expect(TenantModule::where('module_slug', 'core')->where('is_enabled', true)->count())->toBe(2);
});

test('module:enable command runs migrations for tenant modules', function () {
    $modulePath = str_replace(base_path().'/', '', app_path('Modules/QualityMonitoring/Database/Migrations'));

    $this->mock(TenantMigrator::class, function ($mock) use ($modulePath) {
        $mock->shouldReceive('migrate')
            ->once()
            ->with('tenant', $modulePath, true)
            ->andReturn(['exitCode' => 0, 'output' => 'Module']);
    });

    $this->artisan('module:enable', [
        'slug' => 'quality-monitoring',
        '--tenant' => $this->tenant->id,
    ])->assertSuccessful();
});

test('module:disable command requires tenant or all-tenants option', function () {
    $this->artisan('module:disable', ['slug' => 'core'])
        ->assertFailed()
        ->expectsOutputToContain('must specify');
});

test('module:disable command cannot disable core module', function () {
    $this->artisan('module:disable', [
        'slug' => 'core',
        '--tenant' => $this->tenant->id,
    ])->assertFailed()
        ->expectsOutputToContain('cannot be disabled');
});

test('module:disable command fails for non-existent module', function () {
    $this->artisan('module:disable', [
        'slug' => 'non-existent-module',
        '--tenant' => $this->tenant->id,
    ])->assertFailed();
});

test('module:migrate command requires tenant scope', function () {
    $this->artisan('module:migrate', ['slug' => 'core'])
        ->assertFailed()
        ->expectsOutputToContain('must specify either --tenant');
});

test('module:migrate command uses shared database configuration for shared tenants', function () {
    $this->mock(TenantDatabaseManager::class, function ($mock): void {
        $mock->shouldReceive('configureShared')->once();
        $mock->shouldReceive('configure')->never();
    });

    $this->artisan('module:migrate', [
        'slug' => 'core',
        '--tenant' => $this->tenant->id,
    ])->assertSuccessful();
});

test('module:migrate command uses dedicated database configuration for dedicated tenants', function () {
    $dedicatedTenant = Tenant::create([
        'name' => 'Dedicated Tenant',
        'slug' => 'dedicated-tenant',
        'isolation_mode' => 'db_per_tenant',
        'db_driver' => 'sqlite',
    ]);

    $this->mock(TenantDatabaseManager::class, function ($mock) use ($dedicatedTenant): void {
        $mock->shouldReceive('configure')->once()->withArgs(
            fn (Tenant $tenant): bool => $tenant->id === $dedicatedTenant->id
        );
        $mock->shouldReceive('configureShared')->never();
    });

    $this->artisan('module:migrate', [
        'slug' => 'core',
        '--tenant' => $dedicatedTenant->id,
    ])->assertSuccessful();
});

test('module:migrate command runs for a tenant when tenant option provided', function () {
    $this->artisan('module:migrate', [
        'slug' => 'core',
        '--tenant' => $this->tenant->id,
    ])->assertSuccessful();
});

test('module:migrate command fails for non-existent module', function () {
    $this->artisan('module:migrate', [
        'slug' => 'non-existent-module',
        '--tenant' => $this->tenant->id,
    ])
        ->assertFailed();
});
