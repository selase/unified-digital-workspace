<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use App\Models\TenantFeature;
use App\Models\User;
use App\Modules\ProjectManagement\Database\Factories\ProjectFactory;
use App\Modules\ProjectManagement\Models\Project;
use App\Services\ModuleManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    refreshTenantDatabases();
    Mail::fake();
    $this->user = User::factory()->create();
    $this->tenant = setActiveTenantForTest($this->user);
    foreach (['projects.create', 'core.users.create'] as $name) {
        Permission::query()->firstOrCreate(['name' => $name, 'guard_name' => 'web'], ['category' => 'core']);
    }
    $this->user->givePermissionTo(['projects.create', 'core.users.create']);
    app(ModuleManager::class)->enableForTenant('project-management', $this->tenant);
    Artisan::call('module:migrate', ['slug' => 'project-management', '--tenant' => (string) $this->tenant->id]);
});

it('blocks project creation at capacity on both web and api', function (string $path): void {
    TenantFeature::query()->create([
        'tenant_id' => $this->tenant->id, 'feature_key' => 'projects-limit',
        'enabled' => true, 'meta' => ['type' => 'limit', 'value' => 1],
    ]);
    ProjectFactory::new()->forTenant((string) $this->tenant->id)->create();
    $this->actingAs($this->user)->postJson($path, ['name' => 'Over capacity'])->assertForbidden();
    expect(Project::query()->count())->toBe(1);
})->with(['/project-management/projects', '/api/project-management/v1/projects']);

it('counts tenant membership when enforcing user capacity and sends no email on rejection', function (): void {
    TenantFeature::query()->create([
        'tenant_id' => $this->tenant->id, 'feature_key' => 'users-limit',
        'enabled' => true, 'meta' => ['type' => 'limit', 'value' => 1],
    ]);
    $role = Role::query()->create(['name' => 'Member', 'tenant_id' => $this->tenant->id, 'guard_name' => 'web']);
    $this->actingAs($this->user)->postJson(route('tenant.users.store', ['subdomain' => $this->tenant->slug]), [
        'first_name' => 'New', 'last_name' => 'Member', 'email' => 'capacity@example.test',
        'phone_no' => '+233244123456', 'status' => 'active', 'roles' => [$role->id],
    ])->assertForbidden();
    expect(User::query()->where('email', 'capacity@example.test')->exists())->toBeFalse();
    Mail::assertNothingQueued();
});

it('releases project capacity after soft deletion and honours unlimited plans', function (): void {
    $feature = TenantFeature::query()->create([
        'tenant_id' => $this->tenant->id, 'feature_key' => 'projects-limit',
        'enabled' => true, 'meta' => ['type' => 'limit', 'value' => 1],
    ]);
    $old = ProjectFactory::new()->forTenant((string) $this->tenant->id)->create();
    $old->delete();
    $this->actingAs($this->user)->postJson('/api/project-management/v1/projects', ['name' => 'Replacement'])->assertCreated();
    $feature->update(['meta' => ['type' => 'limit', 'value' => -1]]);
    $this->postJson('/api/project-management/v1/projects', ['name' => 'Unlimited'])->assertCreated();
});

it('ignores retired package limits after upgrading while preserving explicit tenant restrictions', function (): void {
    $this->seed(Database\Seeders\PackageSeeder::class);
    $team = App\Models\Package::query()->where('slug', 'team')->firstOrFail();
    $this->tenant->update(['package_id' => $team->id]);
    $feature = TenantFeature::query()->create([
        'tenant_id' => $this->tenant->id, 'feature_key' => 'projects-limit',
        'enabled' => false, 'meta' => ['source' => 'package', 'type' => 'limit', 'value' => 0],
    ]);
    $this->actingAs($this->user)->postJson('/api/project-management/v1/projects', ['name' => 'After upgrade'])->assertCreated();
    $feature->update(['meta' => ['source' => 'manual', 'type' => 'limit', 'value' => 0]]);
    $this->postJson('/api/project-management/v1/projects', ['name' => 'Explicitly restricted'])->assertForbidden();
});
