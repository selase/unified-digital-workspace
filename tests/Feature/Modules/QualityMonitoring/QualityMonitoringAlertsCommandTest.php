<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Modules\QualityMonitoring\Models\Activity;
use App\Modules\QualityMonitoring\Models\Alert;
use App\Modules\QualityMonitoring\Models\Objective;
use App\Modules\QualityMonitoring\Models\Workplan;
use App\Services\ModuleManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;

/**
 * @return array{0: User, 1: Tenant}
 */
function createQualityAlertContext(): array
{
    $tenantDb = database_path('tenant_quality_alerts.sqlite');
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

    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user, [
        'isolation_mode' => 'db_per_tenant',
        'db_driver' => 'sqlite',
        'meta' => [
            'database' => $tenantDb,
        ],
    ]);

    app(ModuleManager::class)->enableForTenant('quality-monitoring', $tenant);

    Artisan::call('migrate', [
        '--database' => 'tenant',
        '--path' => app_path('Modules/QualityMonitoring/Database/Migrations'),
        '--realpath' => true,
    ]);

    return [$user, $tenant];
}

it('generates overdue activity alerts', function (): void {
    [$user, $tenant] = createQualityAlertContext();

    $workplan = Workplan::create([
        'title' => 'Alert Plan',
        'period_start' => now()->toDateString(),
        'period_end' => now()->addMonths(3)->toDateString(),
        'status' => 'approved',
    ]);

    $objective = Objective::create([
        'workplan_id' => $workplan->id,
        'title' => 'Objective',
    ]);

    $activity = Activity::create([
        'objective_id' => $objective->id,
        'title' => 'Overdue Activity',
        'due_date' => now()->subDays(3)->toDateString(),
        'status' => 'in-progress',
        'responsible_id' => $user->id,
    ]);

    Artisan::call('quality:generate-alerts', ['--tenant' => $tenant->id]);

    expect(Alert::query()->where('type', 'activity_overdue')->exists())->toBeTrue();
});

it('escalates open alerts to workplan owners', function (): void {
    [$user, $tenant] = createQualityAlertContext();

    $workplan = Workplan::create([
        'title' => 'Escalation Plan',
        'period_start' => now()->toDateString(),
        'period_end' => now()->addMonths(3)->toDateString(),
        'status' => 'approved',
        'owner_id' => $user->id,
    ]);

    $alert = Alert::create([
        'workplan_id' => $workplan->id,
        'type' => 'activity_overdue',
        'status' => 'open',
        'metadata' => [
            'activity_title' => 'Overdue Activity',
        ],
    ]);

    $alert->created_at = now()->subDays(4);
    $alert->save();

    Artisan::call('quality:escalate-alerts', ['--tenant' => $tenant->id]);

    $alert->refresh();

    expect($alert->escalation_level)->toBe(1);
    expect($alert->escalated_at)->not->toBeNull();
    expect($user->notifications()->count())->toBe(1);
});
