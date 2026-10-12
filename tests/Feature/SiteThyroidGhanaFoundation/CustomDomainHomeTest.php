<?php

declare(strict_types=1);

use App\Models\TenantModule;
use App\Models\User;
use App\Modules\SiteThyroidGhanaFoundation\Database\Seeders\ThyroidGhanaSeeder;

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
