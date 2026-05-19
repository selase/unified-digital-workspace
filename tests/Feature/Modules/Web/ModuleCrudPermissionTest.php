<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\CmsCore\Database\Factories\PostTypeFactory;
use App\Modules\CmsCore\Models\Post;
use App\Modules\HrmsCore\Database\Factories\CenterFactory;
use App\Modules\HrmsCore\Database\Factories\GradeFactory;
use App\Modules\HrmsCore\Models\Employees\Employee;
use App\Modules\IncidentManagement\Database\Factories\IncidentPriorityFactory;
use App\Modules\IncidentManagement\Database\Factories\IncidentStatusFactory;
use App\Modules\IncidentManagement\Models\Incident;
use App\Services\ModuleManager;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

/*
|--------------------------------------------------------------------------
| Permission Gate & Validation Tests
|--------------------------------------------------------------------------
|
| Tests that CRUD routes return 403 without proper permissions,
| and that validation rejects invalid payloads.
|
*/

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->tenant = setActiveTenantForTest($this->user, [
        'slug' => 'perm-test-'.uniqid(),
    ]);
});

// ==========================================================================
// CMS Core — Permission Gates
// ==========================================================================

it('returns 403 for cms post create without permission', function (): void {
    enableCrudModule('cms-core', ['cms.posts.view']);

    actingAs($this->user)
        ->get(route('cms-core.posts.create'))
        ->assertForbidden();
});

it('returns 403 for cms post store without permission', function (): void {
    enableCrudModule('cms-core', ['cms.posts.view']);

    actingAs($this->user)
        ->post(route('cms-core.posts.store'), ['title' => 'Blocked'])
        ->assertForbidden();
});

it('returns 403 for cms post edit without permission', function (): void {
    enableCrudModule('cms-core', ['cms.posts.view', 'cms.posts.create']);
    $postType = PostTypeFactory::new()->forTenant($this->tenant->id)->create();
    $post = Post::create([
        'tenant_id' => $this->tenant->id,
        'post_type_id' => $postType->id,
        'title' => 'Gate test',
        'slug' => 'gate-test',
        'status' => 'draft',
        'body' => 'body',
        'author_id' => (string) $this->user->uuid,
    ]);

    actingAs($this->user)
        ->get(route('cms-core.posts.edit', $post))
        ->assertForbidden();
});

it('returns 403 for cms post delete without permission', function (): void {
    enableCrudModule('cms-core', ['cms.posts.view', 'cms.posts.create']);
    $postType = PostTypeFactory::new()->forTenant($this->tenant->id)->create();
    $post = Post::create([
        'tenant_id' => $this->tenant->id,
        'post_type_id' => $postType->id,
        'title' => 'Delete gate test',
        'slug' => 'delete-gate-test',
        'status' => 'draft',
        'body' => 'body',
        'author_id' => (string) $this->user->uuid,
    ]);

    actingAs($this->user)
        ->delete(route('cms-core.posts.destroy', $post))
        ->assertForbidden();
});

// ==========================================================================
// CMS Core — Validation
// ==========================================================================

it('rejects cms post store with missing required fields', function (): void {
    enableCrudModule('cms-core', ['cms.posts.view', 'cms.posts.create']);

    actingAs($this->user)
        ->post(route('cms-core.posts.store'), [])
        ->assertSessionHasErrors(['title', 'status', 'body', 'author_id']);
});

it('rejects cms post store with invalid status', function (): void {
    enableCrudModule('cms-core', ['cms.posts.view', 'cms.posts.create']);
    $postType = PostTypeFactory::new()->forTenant($this->tenant->id)->create();

    actingAs($this->user)
        ->post(route('cms-core.posts.store'), [
            'post_type_id' => $postType->id,
            'title' => 'Bad status',
            'status' => 'banana',
            'body' => 'body content',
            'author_id' => (string) $this->user->uuid,
        ])
        ->assertSessionHasErrors(['status']);
});

// ==========================================================================
// HRMS Core — Permission Gates
// ==========================================================================

it('returns 403 for hrms employee create without permission', function (): void {
    enableCrudModule('hrms-core', ['hrms.employees.view']);

    actingAs($this->user)
        ->get(route('hrms-core.employees.create'))
        ->assertForbidden();
});

it('returns 403 for hrms employee store without permission', function (): void {
    enableCrudModule('hrms-core', ['hrms.employees.view']);

    actingAs($this->user)
        ->post(route('hrms-core.employees.store'), ['first_name' => 'Blocked'])
        ->assertForbidden();
});

it('returns 403 for hrms employee delete without permission', function (): void {
    enableCrudModule('hrms-core', ['hrms.employees.view', 'hrms.employees.create']);
    $grade = GradeFactory::new()->forTenant($this->tenant->id)->create();
    $center = CenterFactory::new()->forTenant($this->tenant->id)->create();

    actingAs($this->user)
        ->post(route('hrms-core.employees.store'), [
            'first_name' => 'DelGate',
            'last_name' => 'Test',
            'email' => 'del-gate@example.com',
            'grade_id' => $grade->id,
            'center_id' => $center->id,
            'is_active' => '1',
        ])
        ->assertRedirect();

    $employee = Employee::query()->where('email', 'del-gate@example.com')->firstOrFail();

    actingAs($this->user)
        ->delete(route('hrms-core.employees.destroy', $employee))
        ->assertForbidden();
});

// ==========================================================================
// HRMS Core — Validation
// ==========================================================================

it('rejects hrms employee store with missing required fields', function (): void {
    enableCrudModule('hrms-core', ['hrms.employees.view', 'hrms.employees.create']);

    actingAs($this->user)
        ->post(route('hrms-core.employees.store'), [])
        ->assertSessionHasErrors(['first_name', 'last_name']);
});

// ==========================================================================
// Incident Management — Permission Gates
// ==========================================================================

it('returns 403 for incident create without permission', function (): void {
    enableCrudModule('incident-management', ['incidents.view']);

    actingAs($this->user)
        ->get(route('incident-management.incidents.create'))
        ->assertForbidden();
});

it('returns 403 for incident store without permission', function (): void {
    enableCrudModule('incident-management', ['incidents.view']);

    actingAs($this->user)
        ->post(route('incident-management.incidents.store'), ['title' => 'Blocked'])
        ->assertForbidden();
});

it('returns 403 for incident delete without permission', function (): void {
    Mail::fake();
    enableCrudModule('incident-management', ['incidents.view', 'incidents.create']);
    $status = IncidentStatusFactory::new()->forTenant($this->tenant->id)->create(['is_default' => true]);

    $incident = Incident::create([
        'tenant_id' => $this->tenant->id,
        'title' => 'Del gate test',
        'description' => 'Testing delete gate',
        'status_id' => $status->id,
        'reported_by_id' => (string) $this->user->uuid,
        'reported_via' => 'internal',
        'reference_code' => 'INC-GATE-001',
    ]);

    actingAs($this->user)
        ->delete(route('incident-management.incidents.destroy', $incident))
        ->assertForbidden();
});

// ==========================================================================
// Incident Management — Validation
// ==========================================================================

it('rejects incident store with missing required fields', function (): void {
    enableCrudModule('incident-management', ['incidents.view', 'incidents.create']);

    actingAs($this->user)
        ->post(route('incident-management.incidents.store'), [])
        ->assertSessionHasErrors(['title', 'description']);
});

// ==========================================================================
// Incident Management — Workflow Permission Gates
// ==========================================================================

it('returns 403 for incident assign without permission', function (): void {
    Mail::fake();
    enableCrudModule('incident-management', ['incidents.view', 'incidents.create']);
    $status = IncidentStatusFactory::new()->forTenant($this->tenant->id)->create(['is_default' => true]);

    $incident = Incident::create([
        'tenant_id' => $this->tenant->id,
        'title' => 'Assign gate test',
        'description' => 'Testing assign gate',
        'status_id' => $status->id,
        'reported_by_id' => (string) $this->user->uuid,
        'reported_via' => 'internal',
        'reference_code' => 'INC-GATE-002',
    ]);

    actingAs($this->user)
        ->post(route('incident-management.incidents.assign', $incident), [
            'assigned_to_id' => (string) $this->user->uuid,
        ])
        ->assertForbidden();
});

it('returns 403 for incident escalate without permission', function (): void {
    Mail::fake();
    enableCrudModule('incident-management', ['incidents.view', 'incidents.create']);
    $status = IncidentStatusFactory::new()->forTenant($this->tenant->id)->create(['is_default' => true]);
    $priority = IncidentPriorityFactory::new()->forTenant($this->tenant->id)->create();

    $incident = Incident::create([
        'tenant_id' => $this->tenant->id,
        'title' => 'Escalate gate test',
        'description' => 'Testing escalate gate',
        'status_id' => $status->id,
        'priority_id' => $priority->id,
        'reported_by_id' => (string) $this->user->uuid,
        'reported_via' => 'internal',
        'reference_code' => 'INC-GATE-003',
    ]);

    actingAs($this->user)
        ->post(route('incident-management.incidents.escalate', $incident), [
            'to_priority_id' => $priority->id,
        ])
        ->assertForbidden();
});

// ==========================================================================
// Helpers
// ==========================================================================

/**
 * @param  array<int, string>  $permissions
 */
function enableCrudModule(string $slug, array $permissions): void
{
    $tenant = test()->tenant;
    $user = test()->user;

    app(ModuleManager::class)->enableForTenant('core', $tenant);
    app(ModuleManager::class)->enableForTenant($slug, $tenant);

    foreach ($permissions as $permission) {
        Permission::firstOrCreate([
            'name' => $permission,
            'guard_name' => 'web',
        ], [
            'uuid' => (string) Str::uuid(),
            'category' => 'Modules',
        ]);
    }

    setPermissionsTeamId($tenant->id);
    $user->givePermissionTo($permissions);
}
