<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\CmsCore\Database\Factories\CategoryFactory;
use App\Modules\CmsCore\Database\Factories\PostTypeFactory;
use App\Modules\CmsCore\Database\Factories\TagFactory;
use App\Modules\CmsCore\Models\Media;
use App\Modules\CmsCore\Models\Post;
use App\Modules\HrmsCore\Database\Factories\CenterFactory;
use App\Modules\HrmsCore\Database\Factories\GradeFactory;
use App\Modules\HrmsCore\Models\Employees\Employee;
use App\Modules\IncidentManagement\Database\Factories\IncidentCategoryFactory;
use App\Modules\IncidentManagement\Database\Factories\IncidentPriorityFactory;
use App\Modules\IncidentManagement\Database\Factories\IncidentStatusFactory;
use App\Modules\IncidentManagement\Models\Incident;
use App\Services\ModuleManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $tenantDb = database_path('tenant_operational_web_testing.sqlite');
    if (file_exists($tenantDb)) {
        unlink($tenantDb);
    }
    touch($tenantDb);

    Config::set('database.connections.tenant', [
        'driver' => 'sqlite',
        'database' => $tenantDb,
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);
    Config::set('database.default_tenant_connection', 'tenant');
    DB::purge('tenant');
    DB::reconnect('tenant');

    $this->user = User::factory()->create();
    $this->tenant = setActiveTenantForTest($this->user, [
        'slug' => 'ops-modules-'.uniqid(),
        'isolation_mode' => 'db_per_tenant',
        'db_driver' => 'sqlite',
        'meta' => [
            'database' => $tenantDb,
        ],
    ]);

    migrateOperationalModules();
});

it('supports hrms employee web crud flow', function (): void {
    app(ModuleManager::class)->enableForTenant('core', $this->tenant);
    app(ModuleManager::class)->enableForTenant('hrms-core', $this->tenant);
    grantWebPermissions($this->user, [
        'hrms.employees.view',
        'hrms.employees.create',
        'hrms.employees.update',
        'hrms.employees.delete',
    ]);

    $grade = GradeFactory::new()->forTenant($this->tenant->id)->create();
    $center = CenterFactory::new()->forTenant($this->tenant->id)->create();

    actingAs($this->user)
        ->get(route('hrms-core.employees.create'))
        ->assertOk();

    actingAs($this->user)
        ->post(route('hrms-core.employees.store'), [
            'employee_staff_id' => 'HRMS-EMP-001',
            'first_name' => 'Ama',
            'last_name' => 'Nkrumah',
            'email' => 'ama.nkrumah@example.com',
            'mobile' => '+233555000001',
            'grade_id' => $grade->id,
            'center_id' => $center->id,
            'is_active' => '1',
        ])
        ->assertRedirect();

    $employee = Employee::query()->where('employee_staff_id', 'HRMS-EMP-001')->firstOrFail();

    expect($employee->first_name)->toBe('Ama');

    actingAs($this->user)
        ->put(route('hrms-core.employees.update', $employee), [
            'employee_staff_id' => 'HRMS-EMP-001',
            'first_name' => 'Ama',
            'last_name' => 'Mensah',
            'email' => 'ama.mensah@example.com',
            'mobile' => '+233555000002',
            'grade_id' => $grade->id,
            'center_id' => $center->id,
        ])
        ->assertRedirect(route('hrms-core.employees.show', $employee));

    expect(Employee::query()->find($employee->id)?->last_name)->toBe('Mensah');

    actingAs($this->user)
        ->delete(route('hrms-core.employees.destroy', $employee))
        ->assertRedirect(route('hrms-core.employees.index'));

    expect(Employee::query()->find($employee->id))->toBeNull();
    expect(Employee::withTrashed()->find($employee->id))->not->toBeNull();
});

it('supports cms post web crud flow', function (): void {
    app(ModuleManager::class)->enableForTenant('core', $this->tenant);
    app(ModuleManager::class)->enableForTenant('cms-core', $this->tenant);
    grantWebPermissions($this->user, [
        'cms.posts.view',
        'cms.posts.create',
        'cms.posts.update',
        'cms.posts.delete',
    ]);

    $postType = PostTypeFactory::new()->forTenant($this->tenant->id)->create();
    $category = CategoryFactory::new()->forTenant($this->tenant->id)->create();
    $tag = TagFactory::new()->forTenant($this->tenant->id)->create();

    actingAs($this->user)
        ->get(route('cms-core.posts.create'))
        ->assertOk();

    actingAs($this->user)
        ->post(route('cms-core.posts.store'), [
            'post_type_id' => $postType->id,
            'title' => 'Operational CMS Post',
            'status' => 'draft',
            'body' => 'Initial content body',
            'author_id' => (string) $this->user->uuid,
            'category_ids' => [$category->id],
            'tag_ids' => [$tag->id],
            'featured_image' => UploadedFile::fake()->image('cover.jpg', 1200, 630),
        ])
        ->assertRedirect();

    $post = Post::query()->where('title', 'Operational CMS Post')->firstOrFail();
    expect($post->featured_media_id)->not->toBeNull();

    $featuredImage = Media::query()->find($post->featured_media_id);
    expect($featuredImage)->not->toBeNull();
    expect($featuredImage?->uploaded_by)->toBe((string) $this->user->uuid);

    $media = Media::query()->create([
        'tenant_id' => $this->tenant->id,
        'disk' => 'public',
        'path' => 'media/ops-post-cover.jpg',
        'original_filename' => 'ops-post-cover.jpg',
        'filename' => 'ops-post-cover.jpg',
        'mime_type' => 'image/jpeg',
        'size_bytes' => 10240,
        'uploaded_by' => (string) $this->user->uuid,
        'is_public' => true,
    ]);

    actingAs($this->user)
        ->get(route('cms-core.posts.index'))
        ->assertOk()
        ->assertSee('Operational CMS Post');

    actingAs($this->user)
        ->put(route('cms-core.posts.update', $post), [
            'title' => 'Operational CMS Post Updated',
            'slug' => 'operational-cms-post-updated',
            'status' => 'published',
            'body' => 'Updated content body',
            'author_id' => (string) $this->user->uuid,
            'post_type_id' => $postType->id,
            'featured_media_id' => $media->id,
            'category_ids' => [$category->id],
            'tag_ids' => [$tag->id],
            'media_ids' => [$media->id],
        ])
        ->assertRedirect(route('cms-core.posts.show', $post));

    expect(Post::query()->find($post->id)?->status)->toBe('published');

    actingAs($this->user)
        ->get(route('cms-core.posts.show', $post))
        ->assertOk();

    actingAs($this->user)
        ->delete(route('cms-core.posts.destroy', $post))
        ->assertRedirect(route('cms-core.posts.index'));

    expect(Post::query()->find($post->id))->toBeNull();
    expect(Post::withTrashed()->find($post->id))->not->toBeNull();
});

it('supports incident web crud and workflow actions', function (): void {
    Mail::fake();

    app(ModuleManager::class)->enableForTenant('core', $this->tenant);
    app(ModuleManager::class)->enableForTenant('incident-management', $this->tenant);
    grantWebPermissions($this->user, [
        'incidents.view',
        'incidents.create',
        'incidents.update',
        'incidents.delete',
        'incidents.assign',
        'incidents.escalate',
    ]);

    $status = IncidentStatusFactory::new()->forTenant($this->tenant->id)->create(['is_default' => true]);
    $category = IncidentCategoryFactory::new()->forTenant($this->tenant->id)->create();
    $priority = IncidentPriorityFactory::new()->forTenant($this->tenant->id)->create();
    $escalatedPriority = IncidentPriorityFactory::new()->forTenant($this->tenant->id)->create();
    $assignee = User::factory()->create();

    $this->tenant->users()->syncWithoutDetaching($assignee->id);

    actingAs($this->user)
        ->post(route('incident-management.incidents.store'), [
            'title' => 'Service outage',
            'description' => 'Portal is unreachable',
            'status_id' => $status->id,
            'category_id' => $category->id,
            'priority_id' => $priority->id,
        ])
        ->assertRedirect();

    $incident = Incident::query()->where('title', 'Service outage')->firstOrFail();

    actingAs($this->user)
        ->post(route('incident-management.incidents.assign', $incident), [
            'assigned_to_id' => (string) $assignee->uuid,
            'note' => 'Take first response',
        ])
        ->assertRedirect(route('incident-management.incidents.show', $incident));

    actingAs($this->user)
        ->post(route('incident-management.incidents.escalate', $incident), [
            'to_priority_id' => $escalatedPriority->id,
            'reason' => 'Extended business impact',
        ])
        ->assertRedirect(route('incident-management.incidents.show', $incident));

    actingAs($this->user)
        ->post(route('incident-management.incidents.resolve', $incident), [
            'status_id' => $status->id,
        ])
        ->assertRedirect(route('incident-management.incidents.show', $incident));

    actingAs($this->user)
        ->post(route('incident-management.incidents.close', $incident), [
            'status_id' => $status->id,
        ])
        ->assertRedirect(route('incident-management.incidents.show', $incident));

    actingAs($this->user)
        ->delete(route('incident-management.incidents.destroy', $incident))
        ->assertRedirect(route('incident-management.incidents.index'));

    expect(Incident::query()->find($incident->id))->toBeNull();
    expect(Incident::withTrashed()->find($incident->id))->not->toBeNull();
});

function migrateOperationalModules(): void
{
    $moduleMigrationPaths = [
        app_path('Modules/HrmsCore/Database/Migrations'),
        app_path('Modules/CmsCore/Database/Migrations'),
        app_path('Modules/IncidentManagement/Database/Migrations'),
    ];

    foreach ($moduleMigrationPaths as $path) {
        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => $path,
            '--realpath' => true,
            '--force' => true,
        ]);
    }
}

/**
 * @param  array<int, string>  $permissions
 */
function grantWebPermissions(User $user, array $permissions): void
{
    foreach ($permissions as $permission) {
        $record = Permission::query()
            ->where('name', $permission)
            ->where('guard_name', 'web')
            ->first();

        if (! $record) {
            Permission::create([
                'uuid' => (string) Str::uuid(),
                'name' => $permission,
                'guard_name' => 'web',
                'category' => 'Modules',
            ]);
        }
    }

    $user->givePermissionTo($permissions);
}
