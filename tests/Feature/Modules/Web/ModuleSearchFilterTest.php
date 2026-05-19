<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\CmsCore\Database\Factories\PostTypeFactory;
use App\Modules\CmsCore\Models\Post;
use App\Modules\HrmsCore\Database\Factories\CenterFactory;
use App\Modules\HrmsCore\Database\Factories\GradeFactory;
use App\Modules\HrmsCore\Models\Employees\Employee;
use App\Modules\IncidentManagement\Database\Factories\IncidentCategoryFactory;
use App\Modules\IncidentManagement\Database\Factories\IncidentPriorityFactory;
use App\Modules\IncidentManagement\Database\Factories\IncidentStatusFactory;
use App\Modules\IncidentManagement\Models\Incident;
use App\Services\ModuleManager;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

/*
|--------------------------------------------------------------------------
| Search, Filter & Show Page Tests
|--------------------------------------------------------------------------
|
| Tests that index page search/filter works, show pages render,
| and secondary index pages (tasks, reports) load correctly.
|
*/

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->tenant = setActiveTenantForTest($this->user, [
        'slug' => 'search-test-'.uniqid(),
    ]);
});

// ==========================================================================
// CMS Core — Search & Filter
// ==========================================================================

it('filters cms posts by search term', function (): void {
    enableSearchModule('cms-core', ['cms.posts.view', 'cms.posts.create']);
    $postType = PostTypeFactory::new()->forTenant($this->tenant->id)->create();

    Post::create([
        'tenant_id' => $this->tenant->id,
        'post_type_id' => $postType->id,
        'title' => 'Alpha article',
        'slug' => 'alpha-article',
        'status' => 'draft',
        'body' => 'Alpha body',
        'author_id' => (string) $this->user->uuid,
    ]);
    Post::create([
        'tenant_id' => $this->tenant->id,
        'post_type_id' => $postType->id,
        'title' => 'Beta bulletin',
        'slug' => 'beta-bulletin',
        'status' => 'published',
        'body' => 'Beta body',
        'author_id' => (string) $this->user->uuid,
    ]);

    actingAs($this->user)
        ->get(route('cms-core.posts.index', ['search' => 'Alpha']))
        ->assertOk()
        ->assertSee('Alpha article')
        ->assertDontSee('Beta bulletin');
});

it('filters cms posts by status', function (): void {
    enableSearchModule('cms-core', ['cms.posts.view', 'cms.posts.create']);
    $postType = PostTypeFactory::new()->forTenant($this->tenant->id)->create();

    Post::create([
        'tenant_id' => $this->tenant->id,
        'post_type_id' => $postType->id,
        'title' => 'Draft post',
        'slug' => 'draft-post',
        'status' => 'draft',
        'body' => 'Draft body',
        'author_id' => (string) $this->user->uuid,
    ]);
    Post::create([
        'tenant_id' => $this->tenant->id,
        'post_type_id' => $postType->id,
        'title' => 'Published post',
        'slug' => 'published-post',
        'status' => 'published',
        'body' => 'Published body',
        'author_id' => (string) $this->user->uuid,
        'published_at' => now(),
    ]);

    actingAs($this->user)
        ->get(route('cms-core.posts.index', ['status' => 'published']))
        ->assertOk()
        ->assertSee('Published post')
        ->assertDontSee('Draft post');
});

it('renders cms post show page with related data', function (): void {
    enableSearchModule('cms-core', ['cms.posts.view', 'cms.posts.create']);
    $postType = PostTypeFactory::new()->forTenant($this->tenant->id)->create();

    $post = Post::create([
        'tenant_id' => $this->tenant->id,
        'post_type_id' => $postType->id,
        'title' => 'Show test post',
        'slug' => 'show-test-post',
        'status' => 'published',
        'body' => 'Full content for the show page',
        'author_id' => (string) $this->user->uuid,
        'published_at' => now(),
    ]);

    actingAs($this->user)
        ->get(route('cms-core.posts.show', $post))
        ->assertOk()
        ->assertSee('Show test post')
        ->assertSee('Full content for the show page');
});

// ==========================================================================
// HRMS Core — Search & Filter
// ==========================================================================

it('filters hrms employees by search term', function (): void {
    enableSearchModule('hrms-core', ['hrms.employees.view', 'hrms.employees.create']);
    $grade = GradeFactory::new()->forTenant($this->tenant->id)->create();
    $center = CenterFactory::new()->forTenant($this->tenant->id)->create();

    actingAs($this->user)->post(route('hrms-core.employees.store'), [
        'first_name' => 'Kwame',
        'last_name' => 'Asante',
        'email' => 'kwame@example.com',
        'grade_id' => $grade->id,
        'center_id' => $center->id,
        'is_active' => '1',
    ]);

    actingAs($this->user)->post(route('hrms-core.employees.store'), [
        'first_name' => 'Esi',
        'last_name' => 'Mensah',
        'email' => 'esi@example.com',
        'grade_id' => $grade->id,
        'center_id' => $center->id,
        'is_active' => '1',
    ]);

    actingAs($this->user)
        ->get(route('hrms-core.employees.index', ['search' => 'Kwame']))
        ->assertOk()
        ->assertSee('Kwame')
        ->assertDontSee('Esi');
});

it('filters hrms employees by status', function (): void {
    enableSearchModule('hrms-core', ['hrms.employees.view', 'hrms.employees.create']);
    $grade = GradeFactory::new()->forTenant($this->tenant->id)->create();
    $center = CenterFactory::new()->forTenant($this->tenant->id)->create();

    actingAs($this->user)->post(route('hrms-core.employees.store'), [
        'first_name' => 'Active',
        'last_name' => 'Worker',
        'email' => 'active@example.com',
        'grade_id' => $grade->id,
        'center_id' => $center->id,
        'is_active' => '1',
    ]);

    actingAs($this->user)->post(route('hrms-core.employees.store'), [
        'first_name' => 'Inactive',
        'last_name' => 'Worker',
        'email' => 'inactive@example.com',
        'grade_id' => $grade->id,
        'center_id' => $center->id,
        'is_active' => '0',
    ]);

    actingAs($this->user)
        ->get(route('hrms-core.employees.index', ['status' => 'active']))
        ->assertOk()
        ->assertSee('active@example.com')
        ->assertDontSee('inactive@example.com');
});

it('renders hrms employee show page', function (): void {
    enableSearchModule('hrms-core', ['hrms.employees.view', 'hrms.employees.create']);
    $grade = GradeFactory::new()->forTenant($this->tenant->id)->create();
    $center = CenterFactory::new()->forTenant($this->tenant->id)->create();

    actingAs($this->user)->post(route('hrms-core.employees.store'), [
        'employee_staff_id' => 'SHOW-001',
        'first_name' => 'Yaw',
        'last_name' => 'Boateng',
        'email' => 'yaw.boateng@example.com',
        'grade_id' => $grade->id,
        'center_id' => $center->id,
        'is_active' => '1',
    ]);

    $employee = Employee::query()->where('employee_staff_id', 'SHOW-001')->firstOrFail();

    actingAs($this->user)
        ->get(route('hrms-core.employees.show', $employee))
        ->assertOk()
        ->assertSee('Yaw')
        ->assertSee('Boateng');
});

// ==========================================================================
// Incident Management — Search & Filter
// ==========================================================================

it('filters incidents by search term', function (): void {
    enableSearchModule('incident-management', ['incidents.view', 'incidents.create']);
    $status = IncidentStatusFactory::new()->forTenant($this->tenant->id)->create(['is_default' => true]);
    $category = IncidentCategoryFactory::new()->forTenant($this->tenant->id)->create();
    $priority = IncidentPriorityFactory::new()->forTenant($this->tenant->id)->create();

    Incident::create([
        'tenant_id' => $this->tenant->id,
        'title' => 'Server outage alpha',
        'description' => 'Production down',
        'status_id' => $status->id,
        'category_id' => $category->id,
        'priority_id' => $priority->id,
        'reported_by_id' => (string) $this->user->uuid,
        'reported_via' => 'internal',
        'reference_code' => 'INC-SEARCH-001',
    ]);
    Incident::create([
        'tenant_id' => $this->tenant->id,
        'title' => 'Network latency beta',
        'description' => 'Slow responses',
        'status_id' => $status->id,
        'category_id' => $category->id,
        'priority_id' => $priority->id,
        'reported_by_id' => (string) $this->user->uuid,
        'reported_via' => 'internal',
        'reference_code' => 'INC-SEARCH-002',
    ]);

    actingAs($this->user)
        ->get(route('incident-management.incidents.index', ['search' => 'Server outage']))
        ->assertOk()
        ->assertSee('Server outage alpha')
        ->assertDontSee('Network latency beta');
});

it('renders incident show page with related data', function (): void {
    enableSearchModule('incident-management', ['incidents.view', 'incidents.create']);
    $status = IncidentStatusFactory::new()->forTenant($this->tenant->id)->create(['is_default' => true]);

    $incident = Incident::create([
        'tenant_id' => $this->tenant->id,
        'title' => 'Show page incident',
        'description' => 'Detailed description for show page',
        'status_id' => $status->id,
        'reported_by_id' => (string) $this->user->uuid,
        'reported_via' => 'internal',
        'reference_code' => 'INC-SHOW-001',
    ]);

    actingAs($this->user)
        ->get(route('incident-management.incidents.show', $incident))
        ->assertOk()
        ->assertSee('Show page incident')
        ->assertSee('Detailed description for show page');
});

// ==========================================================================
// Incident Management — Secondary Index Pages
// ==========================================================================

it('renders incident tasks page', function (): void {
    enableSearchModule('incident-management', ['incidents.view']);

    actingAs($this->user)
        ->get(route('incident-management.tasks.index'))
        ->assertOk()
        ->assertSee('Incident Tasks');
});

it('renders incident reports page', function (): void {
    enableSearchModule('incident-management', ['incidents.view']);

    actingAs($this->user)
        ->get(route('incident-management.reports.index'))
        ->assertOk()
        ->assertSee('Progress Reports');
});

// ==========================================================================
// Helpers
// ==========================================================================

/**
 * @param  array<int, string>  $permissions
 */
function enableSearchModule(string $slug, array $permissions): void
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
