<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\ModuleManager;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->tenant = setActiveTenantForTest($this->user, [
        'slug' => 'tier2-pages-'.uniqid(),
    ]);
});

// --- Document Management ---

it('renders documents page with kt-card pattern and search form', function (): void {
    enableTier2Module('document-management', ['documents.view']);

    actingAs($this->user)
        ->get(route('document-management.documents.index'))
        ->assertOk()
        ->assertSee('Document Library')
        ->assertSee('kt-card', false)
        ->assertSee('kt-card-header', false)
        ->assertSee('kt-card-content', false)
        ->assertSee('kt-scrollable-x-auto', false)
        ->assertSee('name="search"', false)
        ->assertSee('name="status"', false);
});

it('renders quizzes page with kt-card pattern and search form', function (): void {
    enableTier2Module('document-management', ['documents.view']);

    actingAs($this->user)
        ->get(route('document-management.quizzes.index'))
        ->assertOk()
        ->assertSee('Quiz Library')
        ->assertSee('kt-card', false)
        ->assertSee('kt-card-header', false)
        ->assertSee('kt-card-content', false)
        ->assertSee('kt-scrollable-x-auto', false)
        ->assertSee('name="search"', false);
});

it('renders audits page with kt-card pattern and search form', function (): void {
    enableTier2Module('document-management', ['documents.view', 'documents.audit.view']);

    actingAs($this->user)
        ->get(route('document-management.audits.index'))
        ->assertOk()
        ->assertSee('Audit Timeline')
        ->assertSee('kt-card', false)
        ->assertSee('kt-card-header', false)
        ->assertSee('kt-card-content', false)
        ->assertSee('kt-scrollable-x-auto', false)
        ->assertSee('name="search"', false);
});

// --- Forums ---

it('renders channels page with kt-card pattern and search form', function (): void {
    enableTier2Module('forums', ['forums.view']);

    actingAs($this->user)
        ->get(route('forums.channels.index'))
        ->assertOk()
        ->assertSee('Channel Directory')
        ->assertSee('kt-card', false)
        ->assertSee('kt-card-header', false)
        ->assertSee('kt-card-content', false)
        ->assertSee('kt-scrollable-x-auto', false)
        ->assertSee('name="search"', false);
});

it('renders threads page with kt-card pattern and search form', function (): void {
    enableTier2Module('forums', ['forums.view']);

    actingAs($this->user)
        ->get(route('forums.threads.index'))
        ->assertOk()
        ->assertSee('Thread Queue')
        ->assertSee('kt-card', false)
        ->assertSee('kt-card-header', false)
        ->assertSee('kt-card-content', false)
        ->assertSee('kt-scrollable-x-auto', false)
        ->assertSee('name="search"', false)
        ->assertSee('name="status"', false);
});

it('renders messages page with kt-card pattern and search form', function (): void {
    enableTier2Module('forums', ['forums.view']);

    actingAs($this->user)
        ->get(route('forums.messages.index'))
        ->assertOk()
        ->assertSee('Message Center')
        ->assertSee('kt-card', false)
        ->assertSee('kt-card-header', false)
        ->assertSee('kt-card-content', false)
        ->assertSee('kt-scrollable-x-auto', false)
        ->assertSee('name="search"', false);
});

// --- Memos ---

it('renders memo list page with kt-card pattern and search form', function (): void {
    enableTier2Module('memos', ['memos.view']);

    actingAs($this->user)
        ->get(route('memos.list'))
        ->assertOk()
        ->assertSee('Memo List')
        ->assertSee('kt-card', false)
        ->assertSee('kt-card-header', false)
        ->assertSee('kt-card-content', false)
        ->assertSee('kt-scrollable-x-auto', false)
        ->assertSee('name="search"', false)
        ->assertSee('name="status"', false);
});

// --- Project Management ---

it('renders projects page with kt-card pattern and search form', function (): void {
    enableTier2Module('project-management', ['projects.view']);

    actingAs($this->user)
        ->get(route('project-management.projects.index'))
        ->assertOk()
        ->assertSee('Projects')
        ->assertSee('kt-card', false)
        ->assertSee('kt-card-header', false)
        ->assertSee('kt-card-content', false)
        ->assertSee('kt-scrollable-x-auto', false)
        ->assertSee('name="search"', false)
        ->assertSee('name="status"', false);
});

it('renders tasks page with kt-card pattern and search form', function (): void {
    enableTier2Module('project-management', ['projects.view']);

    actingAs($this->user)
        ->get(route('project-management.tasks.index'))
        ->assertOk()
        ->assertSee('Task Board')
        ->assertSee('kt-card', false)
        ->assertSee('kt-card-header', false)
        ->assertSee('kt-card-content', false)
        ->assertSee('kt-scrollable-x-auto', false)
        ->assertSee('name="search"', false)
        ->assertSee('name="status"', false);
});

// --- Quality Monitoring ---

it('renders workplans page with kt-card pattern and search form', function (): void {
    enableTier2Module('quality-monitoring', ['qm.workplans.view']);

    actingAs($this->user)
        ->get(route('quality-monitoring.workplans.index'))
        ->assertOk()
        ->assertSee('Workplans')
        ->assertSee('kt-card', false)
        ->assertSee('kt-card-header', false)
        ->assertSee('kt-card-content', false)
        ->assertSee('kt-scrollable-x-auto', false)
        ->assertSee('name="search"', false)
        ->assertSee('name="status"', false);
});

it('renders alerts page with kt-card pattern and search form', function (): void {
    enableTier2Module('quality-monitoring', ['qm.workplans.view', 'qm.alerts.manage']);

    actingAs($this->user)
        ->get(route('quality-monitoring.alerts.index'))
        ->assertOk()
        ->assertSee('Performance Alerts')
        ->assertSee('kt-card', false)
        ->assertSee('kt-card-header', false)
        ->assertSee('kt-card-content', false)
        ->assertSee('kt-scrollable-x-auto', false)
        ->assertSee('name="search"', false)
        ->assertSee('name="status"', false);
});

// --- Helpers ---

/**
 * @param  array<int, string>  $permissions
 */
function enableTier2Module(string $slug, array $permissions): void
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
