<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Modules\QualityMonitoring\Models\Workplan;
use App\Services\ModuleManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

/**
 * @return array{0: User, 1: Tenant}
 */
function createQualityApiContext(): array
{
    $tenantDb = database_path('tenant_quality_testing.sqlite');
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

    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user, [
        'isolation_mode' => 'db_per_tenant',
        'db_driver' => 'sqlite',
        'meta' => [
            'database' => $tenantDb,
        ],
    ]);

    foreach (['qm.workplans.view', 'qm.workplans.manage', 'qm.approvals.manage'] as $permission) {
        Permission::firstOrCreate([
            'name' => $permission,
            'category' => 'quality',
        ], [
            'uuid' => (string) Str::uuid(),
        ]);
    }

    $user->givePermissionTo(['qm.workplans.view', 'qm.workplans.manage', 'qm.approvals.manage']);

    app(ModuleManager::class)->enableForTenant('quality-monitoring', $tenant);

    Artisan::call('migrate', [
        '--database' => 'tenant',
        '--path' => app_path('Modules/QualityMonitoring/Database/Migrations'),
        '--realpath' => true,
    ]);

    return [$user, $tenant];
}

it('creates and updates workplans', function (): void {
    [$user] = createQualityApiContext();

    $create = actingAs($user, 'sanctum')->postJson('/api/quality-monitoring/v1/workplans', [
        'title' => 'Q1 Workplan',
        'period_start' => now()->toDateString(),
        'period_end' => now()->addMonths(3)->toDateString(),
        'status' => 'draft',
    ]);

    $create->assertCreated();

    $workplanId = $create->json('id');

    $update = actingAs($user, 'sanctum')->putJson("/api/quality-monitoring/v1/workplans/{$workplanId}", [
        'status' => 'submitted',
    ]);

    $update->assertSuccessful()->assertJsonFragment([
        'status' => 'submitted',
    ]);
});

it('submits and approves workplans', function (): void {
    [$user] = createQualityApiContext();

    $create = actingAs($user, 'sanctum')->postJson('/api/quality-monitoring/v1/workplans', [
        'title' => 'Q2 Plan',
        'period_start' => now()->toDateString(),
        'period_end' => now()->addMonths(3)->toDateString(),
        'status' => 'draft',
    ]);

    $create->assertCreated();

    $workplanId = $create->json('id');

    actingAs($user, 'sanctum')
        ->postJson("/api/quality-monitoring/v1/workplans/{$workplanId}/submit", [
            'notes' => 'Ready for review',
        ])
        ->assertSuccessful()
        ->assertJsonFragment([
            'status' => 'submitted',
        ]);

    actingAs($user, 'sanctum')
        ->postJson("/api/quality-monitoring/v1/workplans/{$workplanId}/approve", [
            'comments' => 'Approved',
        ])
        ->assertSuccessful()
        ->assertJsonFragment([
            'status' => 'approved',
        ]);
});

it('lists workplans with filters', function (): void {
    [$user] = createQualityApiContext();

    Workplan::create([
        'title' => 'Annual Plan',
        'period_start' => now()->toDateString(),
        'period_end' => now()->addYear()->toDateString(),
        'status' => 'draft',
    ]);

    $response = actingAs($user, 'sanctum')->getJson('/api/quality-monitoring/v1/workplans?status=draft');

    $response->assertSuccessful()->assertJsonFragment([
        'title' => 'Annual Plan',
    ]);
});
