<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\TenantModule;
use App\Models\User;
use App\Modules\SiteThyroidGhanaFoundation\Database\Seeders\ThyroidGhanaSeeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

it('keeps seeded call-to-action links on the custom domain', function (): void {
    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user, [
        'slug' => 'thyroid-ghana-foundation',
        'custom_domain' => 'thyroidghanafoundation.org',
        'custom_domain_status' => 'active',
    ]);

    foreach (['cms-core', 'site-thyroid-ghana-foundation'] as $module) {
        TenantModule::query()->create([
            'tenant_id' => $tenant->id,
            'module_slug' => $module,
            'is_enabled' => true,
            'version' => '1.0.0',
        ]);
    }

    app(ThyroidGhanaSeeder::class)->run();

    $html = $this->get('https://thyroidghanafoundation.org/')->assertOk()->getContent();

    expect($html)
        ->toContain('href="/about"')
        ->toContain('href="/donate"')
        ->not->toContain('href="/site/')
        ->not->toContain('udworkspace.com');
});

it('seeds a tenant-scoped CMS Editor role without settings or user management access', function (): void {
    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user, ['slug' => 'thyroid-ghana-foundation']);

    foreach (['cms.posts.create', 'cms.media.manage', 'cms.settings.manage', 'core.users.create'] as $name) {
        Permission::query()->firstOrCreate(['name' => $name, 'guard_name' => 'web'], ['uuid' => (string) Str::uuid(), 'category' => 'test']);
    }

    app(ThyroidGhanaSeeder::class)->run();

    $role = Role::query()->where('name', 'CMS Editor')->where('tenant_id', $tenant->id)->firstOrFail();

    expect($role->hasPermissionTo('cms.posts.create'))->toBeTrue()
        ->and($role->hasPermissionTo('cms.media.manage'))->toBeTrue()
        ->and($role->permissions->pluck('name'))->not->toContain('cms.settings.manage', 'core.users.create');
});
