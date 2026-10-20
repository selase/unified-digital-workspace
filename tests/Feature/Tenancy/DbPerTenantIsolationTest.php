<?php

declare(strict_types=1);

use App\Jobs\Middleware\TenantAwareJob;
use App\Models\Tenant;
use App\Models\TenantModule;
use App\Modules\Crm\Models\CrmAccount;
use App\Modules\WorkflowAutomation\Models\WorkflowRule;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantDatabaseManager;
use App\Services\Tenancy\TenantProvisioner;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * These tests use REAL dedicated PostgreSQL databases (tenant_<uuid>) and inspect
 * them directly with a separate PDO connection. They exist to prove the
 * db_per_tenant claim without mocking the database manager.
 */
final class IsolationProbeJob
{
    public function __construct(public string $tenantId) {}
}

function dedicatedTenantPdo(string $database): PDO
{
    $landlord = Config::get('database.connections.landlord');

    $pdo = new PDO(
        sprintf('pgsql:host=%s;port=%s;dbname=%s', $landlord['host'] ?? '127.0.0.1', $landlord['port'] ?? 5432, $database),
        $landlord['username'] ?? null,
        $landlord['password'] ?? null
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    return $pdo;
}

function rowCount(string $database, string $table, ?string $where = null): int
{
    $sql = "select count(*) from \"{$table}\"".($where ? " where {$where}" : '');

    return (int) dedicatedTenantPdo($database)->query($sql)->fetchColumn();
}

function tableExists(string $database, string $table): bool
{
    $stmt = dedicatedTenantPdo($database)->prepare("select to_regclass('public.\"{$table}\"') is not null");
    $stmt->execute();

    return (bool) $stmt->fetchColumn();
}

function makeDedicatedTenant(string $slug): Tenant
{
    $tenant = Tenant::factory()->create([
        'name' => "Isolation {$slug}",
        'slug' => $slug.'-'.uniqid(),
        'isolation_mode' => 'db_per_tenant',
        'db_driver' => 'pgsql',
    ]);

    app(TenantProvisioner::class)->provision($tenant);

    return $tenant->refresh();
}

function enableModuleRow(Tenant $tenant, string $slug): void
{
    TenantModule::updateOrCreate(
        ['tenant_id' => $tenant->id, 'module_slug' => $slug],
        ['is_enabled' => true, 'enabled_at' => now(), 'version' => '1.0.0']
    );
}

function switchToTenant(Tenant $tenant): void
{
    app(TenantContext::class)->setTenant($tenant);
    setPermissionsTeamId($tenant->id);

    if ($tenant->requiresDedicatedDb()) {
        app(TenantDatabaseManager::class)->configure($tenant);
    } else {
        app(TenantDatabaseManager::class)->configureShared();
    }
}

beforeEach(function (): void {
    refreshTenantDatabases();
    $this->dbsToDrop = [];
});

afterEach(function (): void {
    foreach ($this->dbsToDrop ?? [] as $name) {
        dropTenantDatabase($name);
    }
});

test('provisioning creates a separate database whose name is recorded on the tenant', function (): void {
    $tenant = makeDedicatedTenant('provision');
    $this->dbsToDrop[] = $tenant->meta['database'];

    expect($tenant->meta['database'])->toBe('tenant_'.str_replace('-', '_', (string) $tenant->id));
    expect(tableExists($tenant->meta['database'], 'migrations'))->toBeTrue();

    $landlordDb = (string) config('database.connections.landlord.database');
    expect($tenant->meta['database'])->not->toBe($landlordDb);
});

test('each dedicated tenant keeps module data only in its own database', function (): void {
    $d1 = makeDedicatedTenant('d1');
    $d2 = makeDedicatedTenant('d2');
    $this->dbsToDrop = [$d1->meta['database'], $d2->meta['database']];

    foreach ([$d1, $d2] as $tenant) {
        enableModuleRow($tenant, 'crm');
        Artisan::call('module:migrate', ['slug' => 'crm', '--tenant' => (string) $tenant->id]);
        expect(tableExists($tenant->meta['database'], 'crm_accounts'))->toBeTrue();
    }

    switchToTenant($d1);
    CrmAccount::create(['name' => 'D1 only account', 'code' => 'ACC-D1', 'account_type' => 'corporate', 'status' => 'active']);

    switchToTenant($d2);
    CrmAccount::create(['name' => 'D2 account one', 'code' => 'ACC-D2A', 'account_type' => 'corporate', 'status' => 'active']);
    CrmAccount::create(['name' => 'D2 account two', 'code' => 'ACC-D2B', 'account_type' => 'corporate', 'status' => 'active']);

    // Direct inspection of every database, bypassing the application.
    expect(rowCount($d1->meta['database'], 'crm_accounts'))->toBe(1);
    expect(rowCount($d2->meta['database'], 'crm_accounts'))->toBe(2);
    expect(rowCount($d1->meta['database'], 'crm_accounts', "name like 'D2%'"))->toBe(0);
    expect(rowCount($d2->meta['database'], 'crm_accounts', "name like 'D1%'"))->toBe(0);

    // The main (landlord) database must not have received either tenant's rows.
    $landlordDb = (string) config('database.connections.landlord.database');
    if (tableExists($landlordDb, 'crm_accounts')) {
        expect(rowCount($landlordDb, 'crm_accounts', "name like 'D1%' or name like 'D2%'"))->toBe(0);
    }

    // And through the application, each tenant sees only its own rows.
    switchToTenant($d1);
    expect(CrmAccount::query()->count())->toBe(1);
    switchToTenant($d2);
    expect(CrmAccount::query()->count())->toBe(2);
});

test('module:migrate for all tenants reaches shared and dedicated databases and is idempotent', function (): void {
    $shared = Tenant::factory()->create(['slug' => 'shared-'.uniqid(), 'isolation_mode' => 'shared']);
    $d1 = makeDedicatedTenant('fan1');
    $d2 = makeDedicatedTenant('fan2');
    $this->dbsToDrop = [$d1->meta['database'], $d2->meta['database']];

    foreach ([$shared, $d1, $d2] as $tenant) {
        enableModuleRow($tenant, 'crm');
    }

    expect(tableExists($d1->meta['database'], 'crm_accounts'))->toBeFalse();
    expect(tableExists($d2->meta['database'], 'crm_accounts'))->toBeFalse();

    expect(Artisan::call('module:migrate', ['slug' => 'crm', '--all-tenants' => true]))->toBe(0);

    foreach ([$d1, $d2] as $tenant) {
        $database = $tenant->meta['database'];
        expect(tableExists($database, 'crm_accounts'))->toBeTrue();
        expect(rowCount($database, 'migrations', "migration like '%create_crm_tables%'"))->toBe(1);
    }

    $before = rowCount($d1->meta['database'], 'migrations');
    expect(Artisan::call('module:migrate', ['slug' => 'crm', '--all-tenants' => true]))->toBe(0);
    expect(rowCount($d1->meta['database'], 'migrations'))->toBe($before);
});

test('tenant aware jobs switch database per tenant and do not leak between jobs in one worker', function (): void {
    $d1 = makeDedicatedTenant('job1');
    $d2 = makeDedicatedTenant('job2');
    $this->dbsToDrop = [$d1->meta['database'], $d2->meta['database']];

    $seen = [];
    $middleware = new TenantAwareJob();

    foreach ([$d1, $d2, $d1] as $tenant) {
        $job = new IsolationProbeJob($tenant->id);

        $middleware->handle($job, function () use (&$seen): void {
            $seen[] = DB::connection('tenant')->getDatabaseName();
        });
    }

    expect($seen)->toBe([
        $d1->meta['database'],
        $d2->meta['database'],
        $d1->meta['database'],
    ]);
});

test('payment fulfilment connects to the dedicated tenant database when no tenant is active', function (): void {
    $d1 = makeDedicatedTenant('pay1');
    $this->dbsToDrop = [$d1->meta['database']];

    // Simulate a webhook: no tenant context, tenant connection unconfigured.
    app()->forgetInstance(TenantContext::class);
    Config::set('database.connections.tenant', ['driver' => null]);
    DB::purge('tenant');

    $service = app(App\Services\Billing\PaymentFulfillmentService::class);

    // The order does not exist, but the handler must reach the tenant's own database
    // (and fail with a normal "not found" error, not "Unsupported driver []").
    try {
        $service->fulfillStoreOrder(
            ['order_id' => 999999, 'tenant_id' => (string) $d1->id],
            1000,
            'txn_isolation_probe',
            'paystack',
            'GHS'
        );
    } catch (Throwable $e) {
        expect($e->getMessage())->not->toContain('Unsupported driver');
        expect(DB::connection('tenant')->getDatabaseName())->toBe($d1->meta['database']);

        return;
    }

    test()->fail('Expected the handler to fail for a missing order.');
});

test('workflow rules are stored in the dedicated tenant database and fire from a queued event', function (): void {
    $d1 = makeDedicatedTenant('wf1');
    $d2 = makeDedicatedTenant('wf2');
    $this->dbsToDrop = [$d1->meta['database'], $d2->meta['database']];

    foreach ([$d1, $d2] as $tenant) {
        enableModuleRow($tenant, 'workflow-automation');
        Artisan::call('module:migrate', ['slug' => 'workflow-automation', '--tenant' => (string) $tenant->id]);
    }

    switchToTenant($d1);
    WorkflowRule::create([
        'name' => 'D1 rule',
        'trigger_event' => 'incident.logged',
        'conditions' => [],
        'actions' => [['type' => 'log_audit_alert', 'message' => 'seen {event}']],
        'is_active' => true,
    ]);

    expect(rowCount($d1->meta['database'], 'workflow_rules'))->toBe(1);
    expect(rowCount($d2->meta['database'], 'workflow_rules'))->toBe(0);

    $landlordDb = (string) config('database.connections.landlord.database');
    if (tableExists($landlordDb, 'workflow_rules')) {
        expect(rowCount($landlordDb, 'workflow_rules', "name = 'D1 rule'"))->toBe(0);
    }

    // The queued listener runs with no tenant context: it must find D1's rule on its own.
    app()->forgetInstance(TenantContext::class);
    Config::set('database.connections.tenant', ['driver' => null]);
    DB::purge('tenant');

    $logs = app(App\Modules\WorkflowAutomation\Services\WorkflowEngineService::class)
        ->trigger((string) $d1->id, 'incident.logged', ['id' => 1]);

    expect($logs)->toHaveCount(1);
    expect(DB::connection('tenant')->getDatabaseName())->toBe($d1->meta['database']);
    expect(rowCount($d1->meta['database'], 'workflow_execution_logs'))->toBe(1);
    expect(rowCount($d2->meta['database'], 'workflow_execution_logs'))->toBe(0);
});

test('scheduled commands skip dedicated tenants that do not have the module instead of crashing', function (): void {
    $d1 = makeDedicatedTenant('cmd1');
    $this->dbsToDrop = [$d1->meta['database']];

    expect(tableExists($d1->meta['database'], 'documents'))->toBeFalse();

    foreach (['documents:check-reviews', 'facilities:generate-preventive-work-orders', 'hrms:leave-rollover'] as $command) {
        expect(Artisan::call($command))->toBe(0);
    }
});

test('enabling a module from the web creates its tables in the dedicated tenant database', function (): void {
    Artisan::call('db:seed', ['--class' => 'RoleSeeder']);
    Artisan::call('db:seed', ['--class' => 'PermissionsSeeder']);

    $tenant = makeDedicatedTenant('web-enable');
    $this->dbsToDrop = [$tenant->meta['database']];

    $admin = App\Models\User::factory()->create();
    $tenant->users()->attach($admin->id);
    setPermissionsTeamId($tenant->id);
    $admin->givePermissionTo(App\Models\Permission::findOrCreate('core.settings.manage', 'web'));

    expect(tableExists($tenant->meta['database'], 'form_definitions'))->toBeFalse();

    $baseDomain = mb_ltrim((string) config('session.domain'), '.');
    $host = "{$tenant->slug}.{$baseDomain}";

    $this->actingAs($admin)
        ->post("http://{$host}/modules/forms/enable", [], ['HTTP_HOST' => $host])
        ->assertSessionHas('status', 'success');

    expect(TenantModule::query()
        ->where('tenant_id', $tenant->id)->where('module_slug', 'forms')->where('is_enabled', true)->exists())->toBeTrue();
    expect(tableExists($tenant->meta['database'], 'form_definitions'))->toBeTrue();
    expect(tableExists($tenant->meta['database'], 'poll_votes'))->toBeTrue();
});

test('a module whose migrations fail is not enabled', function (): void {
    $tenant = makeDedicatedTenant('enable-fail');
    $this->dbsToDrop = [$tenant->meta['database']];

    $failing = new class extends App\Services\Tenancy\TenantMigrator
    {
        public function migrate(string $database, string $path, bool $force = false): array
        {
            return ['exitCode' => 1, 'output' => 'simulated failure'];
        }
    };
    app()->instance(App\Services\Tenancy\TenantMigrator::class, $failing);

    expect(fn () => app(App\Services\ModuleManager::class)->enableForTenantWithMigrations('forms', $tenant))
        ->toThrow(RuntimeException::class, 'migration failed');

    expect(TenantModule::query()
        ->where('tenant_id', $tenant->id)->where('module_slug', 'forms')->exists())->toBeFalse();
});

test('a controlled document opens on a dedicated-database tenant', function (): void {
    Artisan::call('db:seed', ['--class' => 'RoleSeeder']);
    Artisan::call('db:seed', ['--class' => 'PermissionsSeeder']);

    $tenant = makeDedicatedTenant('doc-open');
    $this->dbsToDrop = [$tenant->meta['database']];

    enableModuleRow($tenant, 'document-management');
    Artisan::call('module:migrate', ['slug' => 'document-management', '--tenant' => (string) $tenant->id]);

    $user = App\Models\User::factory()->create();
    $tenant->users()->attach($user->id);
    setPermissionsTeamId($tenant->id);
    Spatie\Permission\Models\Permission::firstOrCreate(
        ['name' => 'documents.view', 'guard_name' => 'web'],
        ['category' => 'documents', 'uuid' => (string) Illuminate\Support\Str::uuid()]
    );
    $user->givePermissionTo(['documents.view']);

    switchToTenant($tenant);
    $document = App\Modules\DocumentManagement\Database\Factories\DocumentFactory::new()
        ->forTenant((string) $tenant->id)
        ->create(['title' => 'Infection Control Policy', 'status' => 'published', 'owner_id' => (string) $user->uuid]);

    $host = $tenant->slug.'.'.mb_ltrim((string) config('session.domain'), '.');

    // A real HTTP request starts without an already activated tenant connection.
    app(TenantContext::class)->clear();
    app(TenantDatabaseManager::class)->configureShared();

    $this->actingAs($user)
        ->get("http://{$host}/document-management/documents/{$document->id}", ['HTTP_HOST' => $host])
        ->assertOk()
        ->assertSee('Infection Control Policy');

    $this->get("http://{$host}/document-management/documents", ['HTTP_HOST' => $host])
        ->assertOk()
        ->assertSee("http://{$host}/document-management/documents/{$document->id}", false);

    $this->get("http://{$host}/document-management/documents/999999999", ['HTTP_HOST' => $host])
        ->assertNotFound();
});
