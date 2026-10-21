<?php

declare(strict_types=1);

use App\Services\ModuleManager;

it('serves the platform homepage on its own host despite global CMS routes', function (): void {
    $this->get(config('app.url').'/')->assertOk()->assertSee('Every tool for your work.')
        ->assertSee('Sample content, not a live account');
});

it('preserves the generic CMS homepage on a verified customer domain', function (): void {
    $tenant = setActiveTenantForTest(null, ['slug' => 'client-home', 'custom_domain' => 'client-home.example', 'custom_domain_status' => 'active']);
    app(ModuleManager::class)->enableForTenant('cms-core', $tenant);
    $this->get('https://client-home.example/')->assertOk()->assertDontSee('Every tool for your work.');
});
