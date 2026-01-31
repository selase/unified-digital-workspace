<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\TenantContext;

beforeEach(function () {
    refreshTenantDatabases();
});

test('subdomain routing resolves the correct tenant', function () {
    // Create a tenant
    $tenant = Tenant::factory()->create([
        'slug' => 'acme',
        'isolation_mode' => 'shared',
        'db_driver' => 'mysql',
    ]);

    // Create a user and associate them with the tenant
    $user = User::factory()->create();
    $tenant->users()->attach($user->id);

    // Get the base domain from the APP_URL
    $baseDomain = mb_ltrim((string) config('session.domain'), '.');
    if (! $baseDomain) {
        $baseDomain = 'localhost';
    }

    // Construct the subdomain URL
    $subdomainUrl = "http://acme.{$baseDomain}/dashboard";

    // Make a request to the subdomain
    $response = $this->actingAs($user)
        ->get($subdomainUrl, ['HTTP_HOST' => "acme.{$baseDomain}"]);

    // Assert the response is successful
    $response->assertStatus(200);

    // Assert the correct tenant is set in the context
    $this->assertEquals($tenant->id, app(TenantContext::class)->getTenant()->id);

    // Assert the correct view is returned
    $response->assertViewIs('tenant.dashboard');
});
