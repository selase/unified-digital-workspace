<?php

declare(strict_types=1);

use App\Services\ModuleManager;

describe('Incident Management Module Discovery', function (): void {
    test('module manager discovers incident-management module', function (): void {
        $moduleManager = app(ModuleManager::class);
        $modules = $moduleManager->discoverModules();

        expect($modules->has('incident-management'))->toBeTrue();
    });

    test('incident-management module has correct configuration', function (): void {
        $moduleManager = app(ModuleManager::class);
        $module = $moduleManager->find('incident-management');

        expect($module)->not->toBeNull()
            ->and($module['name'])->toBe('Incident Management')
            ->and($module['slug'])->toBe('incident-management')
            ->and($module['tier'])->toBe('standard')
            ->and($module['is_billable'])->toBeTrue()
            ->and($module['depends_on'])->toContain('core');
    });

    test('incident-management module defines expected permissions', function (): void {
        $moduleManager = app(ModuleManager::class);
        $module = $moduleManager->find('incident-management');

        $permissions = $module['permissions'];

        expect($permissions)->toContain('incidents.view')
            ->and($permissions)->toContain('incidents.create')
            ->and($permissions)->toContain('incidents.update')
            ->and($permissions)->toContain('incidents.delete');
    });

    test('incident-management service provider class exists', function (): void {
        expect(class_exists(App\Modules\IncidentManagement\Providers\IncidentManagementServiceProvider::class))->toBeTrue();
    });
});
