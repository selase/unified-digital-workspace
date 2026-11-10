<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\TenantFeature;
use App\Models\User;
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

/**
 * A platform operator belongs to a tenant through membership rather than a tenant_id of their own. Module pages must
 * use the tenant they are working in, not the operator's own (empty) tenant column.
 */
test('reports and workflows open for a member who has no tenant_id of their own', function (): void {
    $member = User::factory()->create(['tenant_id' => null]);
    $tenant = setActiveTenantForTest($member);
    $tenant->users()->syncWithoutDetaching([$member->id]);

    Session::put('active_tenant_id', $tenant->id);
    setPermissionsTeamId($tenant->id);
    app(TenantContext::class)->setTenant($tenant);

    foreach (['reports.view', 'workflows.view'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web'], ['category' => 'modules', 'uuid' => (string) Str::uuid()]);
    }
    $member->givePermissionTo(['reports.view', 'workflows.view']);

    foreach (['reports', 'workflow-automation'] as $slug) {
        TenantFeature::query()->create(['tenant_id' => $tenant->id, 'feature_key' => 'module-'.$slug, 'enabled' => true, 'meta' => ['source' => 'complimentary']]);
        app(ModuleManager::class)->enableForTenantWithMigrations($slug, $tenant);
    }

    expect($member->tenant_id)->toBeNull();
    $this->actingAs($member)->withSession(['active_tenant_id' => $tenant->id]);
    $this->get(route('reports.index'))->assertOk();
    $this->get(route('workflow-automation.index'))->assertOk();
});
