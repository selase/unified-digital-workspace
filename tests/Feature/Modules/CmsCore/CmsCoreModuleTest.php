<?php

declare(strict_types=1);

use App\Services\ModuleManager;

describe('CMS Core Module Discovery', function (): void {
    test('module manager discovers cms-core module', function (): void {
        $moduleManager = app(ModuleManager::class);
        $modules = $moduleManager->discoverModules();

        expect($modules->has('cms-core'))->toBeTrue();
    });

    test('cms-core module has correct configuration', function (): void {
        $moduleManager = app(ModuleManager::class);
        $module = $moduleManager->find('cms-core');

        expect($module)->not->toBeNull()
            ->and($module['name'])->toBe('CMS Core')
            ->and($module['slug'])->toBe('cms-core')
            ->and($module['tier'])->toBe('standard')
            ->and($module['is_billable'])->toBeTrue()
            ->and($module['depends_on'])->toContain('core');
    });

    test('cms-core module defines expected features and permissions', function (): void {
        $moduleManager = app(ModuleManager::class);
        $module = $moduleManager->find('cms-core');

        $features = $module['features'];
        $permissions = $module['permissions'];

        expect($features)->toHaveKey('cms.posts.manage')
            ->and($features)->toHaveKey('cms.media.manage')
            ->and($permissions)->toContain('cms.posts.view')
            ->and($permissions)->toContain('cms.tags.manage')
            ->and($permissions)->toContain('cms.menus.manage');
    });

    test('cms-core service provider class exists', function (): void {
        expect(class_exists(App\Modules\CmsCore\Providers\CmsCoreServiceProvider::class))->toBeTrue();
    });
});
