<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->tenant = setActiveTenantForTest($this->user);

    Permission::firstOrCreate([
        'name' => 'core.dashboard.access',
        'guard_name' => 'web',
    ], [
        'uuid' => (string) Str::uuid(),
        'category' => 'dashboard',
    ]);

    setPermissionsTeamId($this->tenant->id);
    $this->user->givePermissionTo('core.dashboard.access');
    grantOrganisationAdminAccess($this->user);
});

it('renders the executive command center with operational telemetry', function (): void {
    $response = $this->actingAs($this->user)
        ->withSession(['active_tenant_id' => $this->tenant->id])
        ->get(route('tenant.dashboard', ['subdomain' => $this->tenant->slug]));

    $response->assertSuccessful();
    $response->assertViewIs('tenant.dashboard');
    $response->assertSee("Welcome to {$this->tenant->name}!");
    $response->assertSee('Multi-Module Operations Radar');
    $response->assertSee('Priority Leadership Attention Queue');
    $response->assertSee('Workforce');
    $response->assertSee('Controlled Assets');
    $response->assertSee('Active Ops');
    $response->assertSee('assets/metronic/css/styles.css');
});

it('displays action items and module counters when data exists', function (): void {
    $response = $this->actingAs($this->user)
        ->withSession(['active_tenant_id' => $this->tenant->id])
        ->get(route('tenant.dashboard', ['subdomain' => $this->tenant->slug]));

    $response->assertSuccessful();
    $response->assertViewHas('executiveKpis');
    $response->assertViewHas('moduleCards');
    $response->assertViewHas('actionItems');
    $response->assertViewHas('checklist');
    $response->assertViewHas('usages');
});
