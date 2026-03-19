<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\TenantModule;
use App\Models\User;
use Database\Seeders\CompletedModulesDemoSeeder;

test('completed modules demo seeder enables modules and grants permissions', function (): void {
    $tenant = Tenant::factory()->create([
        'slug' => 'purpledot',
        'isolation_mode' => 'shared',
    ]);

    $user = User::factory()->create([
        'email' => 'dev@wearepurpledot.com',
        'tenant_id' => $tenant->id,
    ]);
    $tenant->users()->syncWithoutDetaching([$user->id]);

    $this->seed(CompletedModulesDemoSeeder::class);

    expect(TenantModule::query()
        ->where('tenant_id', $tenant->id)
        ->where('module_slug', 'document-management')
        ->where('is_enabled', true)
        ->exists())->toBeTrue();

    expect(TenantModule::query()
        ->where('tenant_id', $tenant->id)
        ->where('module_slug', 'incident-management')
        ->where('is_enabled', true)
        ->exists())->toBeTrue();

    setPermissionsTeamId($tenant->id);
    expect($user->fresh()->hasPermissionTo('documents.view'))->toBeTrue();
    expect($user->fresh()->hasPermissionTo('incidents.view'))->toBeTrue();
    setPermissionsTeamId(null);
});
