<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\ModuleManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $tenantDb = database_path('tenant_secondary_page_testing.sqlite');
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
        'slug' => 'secondary-pages-'.uniqid(),
        'isolation_mode' => 'db_per_tenant',
        'db_driver' => 'sqlite',
        'meta' => [
            'database' => $tenantDb,
        ],
    ]);

    migrateSecondaryPageModules();
});

// --- HRMS Departments ---

it('renders departments page with kt-card pattern and search form', function (): void {
    enableModule('hrms-core', ['hrms.departments.view']);

    actingAs($this->user)
        ->get(route('hrms-core.departments.index'))
        ->assertOk()
        ->assertSee('Department Directory')
        ->assertSee('kt-card', false)
        ->assertSee('kt-card-header', false)
        ->assertSee('kt-card-content', false)
        ->assertSee('kt-scrollable-x-auto', false)
        ->assertSee('name="search"', false)
        ->assertSee('name="status"', false);
});

// --- HRMS Leave Requests ---

it('renders leave requests page with kt-card pattern and search form', function (): void {
    enableModule('hrms-core', ['hrms.leave.view']);

    actingAs($this->user)
        ->get(route('hrms-core.leave-requests.index'))
        ->assertOk()
        ->assertSee('Leave Requests')
        ->assertSee('kt-card', false)
        ->assertSee('kt-card-header', false)
        ->assertSee('kt-card-content', false)
        ->assertSee('kt-scrollable-x-auto', false)
        ->assertSee('name="search"', false)
        ->assertSee('name="status"', false);
});

// --- HRMS Recruitment ---

it('renders recruitment page with kt-card pattern and search form', function (): void {
    enableModule('hrms-core', ['hrms.jobs.view']);

    actingAs($this->user)
        ->get(route('hrms-core.recruitment.index'))
        ->assertOk()
        ->assertSee('Recruitment Pipeline')
        ->assertSee('kt-card', false)
        ->assertSee('kt-card-header', false)
        ->assertSee('kt-card-content', false)
        ->assertSee('kt-scrollable-x-auto', false)
        ->assertSee('name="search"', false)
        ->assertSee('name="status"', false);
});

// --- CMS Media ---

it('renders media library page with kt-card pattern and search form', function (): void {
    enableModule('cms-core', ['cms.media.view']);

    actingAs($this->user)
        ->get(route('cms-core.media.index'))
        ->assertOk()
        ->assertSee('Media Library')
        ->assertSee('kt-card', false)
        ->assertSee('kt-card-header', false)
        ->assertSee('kt-card-content', false)
        ->assertSee('kt-scrollable-x-auto', false)
        ->assertSee('name="search"', false)
        ->assertSee('name="type"', false);
});

// --- CMS Menus ---

it('renders menu registry page with kt-card pattern and search form', function (): void {
    enableModule('cms-core', ['cms.menus.view']);

    actingAs($this->user)
        ->get(route('cms-core.menus.index'))
        ->assertOk()
        ->assertSee('Menu Registry')
        ->assertSee('kt-card', false)
        ->assertSee('kt-card-header', false)
        ->assertSee('kt-card-content', false)
        ->assertSee('kt-scrollable-x-auto', false)
        ->assertSee('name="search"', false);
});

// --- Helpers ---

function migrateSecondaryPageModules(): void
{
    $paths = [
        app_path('Modules/HrmsCore/Database/Migrations'),
        app_path('Modules/CmsCore/Database/Migrations'),
    ];

    foreach ($paths as $path) {
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
function enableModule(string $slug, array $permissions): void
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
