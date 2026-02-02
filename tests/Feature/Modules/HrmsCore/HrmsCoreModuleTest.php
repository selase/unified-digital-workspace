<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\TenantModule;
use App\Services\ModuleManager;

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create([
        'name' => 'HRMS Test Company',
        'slug' => 'hrms-test-company',
    ]);
});

describe('HRMS Core Module Discovery', function (): void {
    test('module manager discovers hrms-core module', function (): void {
        $moduleManager = app(ModuleManager::class);
        $modules = $moduleManager->discoverModules();

        expect($modules->has('hrms-core'))->toBeTrue();
    });

    test('hrms-core module has correct configuration', function (): void {
        $moduleManager = app(ModuleManager::class);
        $module = $moduleManager->find('hrms-core');

        expect($module)->not->toBeNull()
            ->and($module['name'])->toBe('HRMS Core')
            ->and($module['slug'])->toBe('hrms-core')
            ->and($module['tier'])->toBe('professional')
            ->and($module['is_billable'])->toBeTrue()
            ->and($module['depends_on'])->toContain('core');
    });

    test('hrms-core module defines expected features', function (): void {
        $moduleManager = app(ModuleManager::class);
        $module = $moduleManager->find('hrms-core');

        $features = $module['features'];

        expect($features)->toHaveKey('hrms.employees.manage')
            ->and($features)->toHaveKey('hrms.employees.limit')
            ->and($features)->toHaveKey('hrms.leave.annual')
            ->and($features)->toHaveKey('hrms.appraisal.enabled')
            ->and($features)->toHaveKey('hrms.promotion.enabled')
            ->and($features)->toHaveKey('hrms.recruitment.enabled');
    });

    test('hrms-core module defines expected permissions', function (): void {
        $moduleManager = app(ModuleManager::class);
        $module = $moduleManager->find('hrms-core');

        $permissions = $module['permissions'];

        expect($permissions)->toContain('hrms.employees.view')
            ->and($permissions)->toContain('hrms.employees.create')
            ->and($permissions)->toContain('hrms.employees.update')
            ->and($permissions)->toContain('hrms.employees.delete')
            ->and($permissions)->toContain('hrms.leave.view')
            ->and($permissions)->toContain('hrms.leave.approve');
    });

    test('hrms-core module has correct namespace and provider', function (): void {
        $moduleManager = app(ModuleManager::class);
        $module = $moduleManager->find('hrms-core');

        expect($module['namespace'])->toBe('App\\Modules\\HrmsCore')
            ->and($module['provider'])->toBe('App\\Modules\\HrmsCore\\Providers\\HrmsCoreServiceProvider');
    });

    test('hrms-core service provider class exists', function (): void {
        expect(class_exists(App\Modules\HrmsCore\Providers\HrmsCoreServiceProvider::class))->toBeTrue();
    });
});

describe('HRMS Core Module Enable/Disable', function (): void {
    test('can enable hrms-core module for tenant', function (): void {
        $moduleManager = app(ModuleManager::class);

        // First enable core module (dependency)
        $moduleManager->enableForTenant('core', $this->tenant);

        // Then enable hrms-core
        $moduleManager->enableForTenant('hrms-core', $this->tenant);

        expect($moduleManager->isEnabledForTenant('hrms-core', $this->tenant))->toBeTrue();

        $tenantModule = TenantModule::query()
            ->where('tenant_id', $this->tenant->id)
            ->where('module_slug', 'hrms-core')
            ->first();

        expect($tenantModule)->not->toBeNull()
            ->and($tenantModule->is_enabled)->toBeTrue()
            ->and($tenantModule->enabled_at)->not->toBeNull();
    });

    test('hrms-core depends on core module', function (): void {
        $moduleManager = app(ModuleManager::class);
        $module = $moduleManager->find('hrms-core');

        // Verify hrms-core declares core as a dependency
        expect($module['depends_on'])->toContain('core');

        // Core is always considered enabled, so hrms-core can be enabled
        // (This is by design - core is the foundation module)
        $moduleManager->enableForTenant('hrms-core', $this->tenant);
        expect($moduleManager->isEnabledForTenant('hrms-core', $this->tenant))->toBeTrue();
    });

    test('can disable hrms-core module for tenant', function (): void {
        $moduleManager = app(ModuleManager::class);

        // Enable both modules
        $moduleManager->enableForTenant('core', $this->tenant);
        $moduleManager->enableForTenant('hrms-core', $this->tenant);

        // Disable hrms-core
        $moduleManager->disableForTenant('hrms-core', $this->tenant);

        expect($moduleManager->isEnabledForTenant('hrms-core', $this->tenant))->toBeFalse();

        $tenantModule = TenantModule::query()
            ->where('tenant_id', $this->tenant->id)
            ->where('module_slug', 'hrms-core')
            ->first();

        expect($tenantModule)->not->toBeNull()
            ->and($tenantModule->is_enabled)->toBeFalse()
            ->and($tenantModule->disabled_at)->not->toBeNull();
    });

    test('hrms-core appears in enabled modules list when enabled', function (): void {
        $moduleManager = app(ModuleManager::class);

        $moduleManager->enableForTenant('core', $this->tenant);
        $moduleManager->enableForTenant('hrms-core', $this->tenant);

        $enabledModules = $moduleManager->getEnabledForTenant($this->tenant);

        expect($enabledModules->contains(fn ($m) => $m['slug'] === 'hrms-core'))->toBeTrue();
    });

    test('hrms-core is not enabled by default', function (): void {
        $moduleManager = app(ModuleManager::class);

        expect($moduleManager->isEnabledForTenant('hrms-core', $this->tenant))->toBeFalse();
    });

    test('enabling hrms-core creates tenant module record', function (): void {
        $moduleManager = app(ModuleManager::class);

        // Enable dependencies first
        $moduleManager->enableForTenant('core', $this->tenant);
        $moduleManager->enableForTenant('hrms-core', $this->tenant);

        $record = TenantModule::where('tenant_id', $this->tenant->id)
            ->where('module_slug', 'hrms-core')
            ->first();

        expect($record)->not->toBeNull()
            ->and($record->uuid)->not->toBeNull()
            ->and($record->version)->toBe('1.0.0');
    });
});

describe('HRMS Core Module Features', function (): void {
    test('hrms-core has employee management feature', function (): void {
        $moduleManager = app(ModuleManager::class);
        $module = $moduleManager->find('hrms-core');

        $feature = $module['features']['hrms.employees.manage'];

        expect($feature['type'])->toBe('boolean')
            ->and($feature['name'])->toBe('Employee Management');
    });

    test('hrms-core has employee limit feature with default', function (): void {
        $moduleManager = app(ModuleManager::class);
        $module = $moduleManager->find('hrms-core');

        $feature = $module['features']['hrms.employees.limit'];

        expect($feature['type'])->toBe('numeric')
            ->and($feature['default'])->toBe(50);
    });

    test('hrms-core defines all leave features', function (): void {
        $moduleManager = app(ModuleManager::class);
        $module = $moduleManager->find('hrms-core');

        expect($module['features'])->toHaveKey('hrms.leave.annual')
            ->and($module['features'])->toHaveKey('hrms.leave.other');
    });

    test('hrms-core defines recruitment features', function (): void {
        $moduleManager = app(ModuleManager::class);
        $module = $moduleManager->find('hrms-core');

        expect($module['features'])->toHaveKey('hrms.recruitment.enabled')
            ->and($module['features'])->toHaveKey('hrms.recruitment.public_portal');
    });
});
