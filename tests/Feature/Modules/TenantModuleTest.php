<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\TenantModule;
use App\Services\Tenancy\TenantContext;

beforeEach(function () {
    $this->tenant = Tenant::create([
        'name' => 'Test Tenant',
        'slug' => 'test-tenant',
        'isolation_mode' => 'shared',
    ]);

    app(TenantContext::class)->setTenant($this->tenant);
});

test('tenant module can be created', function () {
    $tenantModule = TenantModule::create([
        'tenant_id' => $this->tenant->id,
        'module_slug' => 'test-module',
        'is_enabled' => true,
        'version' => '1.0.0',
    ]);

    expect($tenantModule)->toBeInstanceOf(TenantModule::class);
    expect($tenantModule->uuid)->not->toBeNull();
    expect($tenantModule->is_enabled)->toBeTrue();
});

test('tenant module belongs to tenant', function () {
    $tenantModule = TenantModule::create([
        'tenant_id' => $this->tenant->id,
        'module_slug' => 'test-module',
        'is_enabled' => true,
    ]);

    expect($tenantModule->tenant)->toBeInstanceOf(Tenant::class);
    expect($tenantModule->tenant->id)->toBe($this->tenant->id);
});

test('tenant has modules relationship', function () {
    TenantModule::create([
        'tenant_id' => $this->tenant->id,
        'module_slug' => 'module-1',
        'is_enabled' => true,
    ]);

    TenantModule::create([
        'tenant_id' => $this->tenant->id,
        'module_slug' => 'module-2',
        'is_enabled' => false,
    ]);

    $this->tenant->refresh();

    expect($this->tenant->modules)->toHaveCount(2);
});

test('tenant can get enabled modules', function () {
    TenantModule::create([
        'tenant_id' => $this->tenant->id,
        'module_slug' => 'enabled-module',
        'is_enabled' => true,
    ]);

    TenantModule::create([
        'tenant_id' => $this->tenant->id,
        'module_slug' => 'disabled-module',
        'is_enabled' => false,
    ]);

    expect($this->tenant->enabledModules)->toHaveCount(1);
    expect($this->tenant->enabledModules->first()->module_slug)->toBe('enabled-module');
});

test('tenant module can be enabled', function () {
    $tenantModule = TenantModule::create([
        'tenant_id' => $this->tenant->id,
        'module_slug' => 'test-module',
        'is_enabled' => false,
    ]);

    $tenantModule->enable();

    expect($tenantModule->is_enabled)->toBeTrue();
    expect($tenantModule->enabled_at)->not->toBeNull();
    expect($tenantModule->disabled_at)->toBeNull();
});

test('tenant module can be disabled', function () {
    $tenantModule = TenantModule::create([
        'tenant_id' => $this->tenant->id,
        'module_slug' => 'test-module',
        'is_enabled' => true,
        'enabled_at' => now(),
    ]);

    $tenantModule->disable();

    expect($tenantModule->is_enabled)->toBeFalse();
    expect($tenantModule->disabled_at)->not->toBeNull();
});

test('tenant module settings are cast to array', function () {
    $tenantModule = TenantModule::create([
        'tenant_id' => $this->tenant->id,
        'module_slug' => 'test-module',
        'is_enabled' => true,
        'settings' => ['key' => 'value', 'nested' => ['foo' => 'bar']],
    ]);

    expect($tenantModule->settings)->toBeArray();
    expect($tenantModule->settings['key'])->toBe('value');
    expect($tenantModule->settings['nested']['foo'])->toBe('bar');
});

test('tenant module has unique constraint on tenant and slug', function () {
    TenantModule::create([
        'tenant_id' => $this->tenant->id,
        'module_slug' => 'unique-module',
        'is_enabled' => true,
    ]);

    // This should throw a unique constraint exception
    expect(fn () => TenantModule::create([
        'tenant_id' => $this->tenant->id,
        'module_slug' => 'unique-module',
        'is_enabled' => false,
    ]))->toThrow(Illuminate\Database\QueryException::class);
});
