<?php

declare(strict_types=1);

use App\Models\Feature;
use App\Models\Package;
use App\Models\Permission;
use App\Models\Tenant;
use App\Models\TenantFeature;
use App\Models\TenantModule;
use App\Services\ModuleManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

test('renaming result tables preserves indicator and reading rows and can run twice', function (): void {
    $schema = Schema::connection('landlord');
    $db = DB::connection('landlord');

    if ($schema->hasTable('rf_indicators')) {
        $schema->rename('rf_indicators', 'pr_indicators');
        $schema->rename('rf_readings', 'pr_readings');
    }

    $tenant = Tenant::factory()->create();
    $indicatorIds = [];
    foreach (['Water access', 'Water quality'] as $name) {
        $indicatorIds[] = $db->table('pr_indicators')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'name' => $name,
            'target' => 10,
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
        ]);
    }

    $readingIds = [];
    foreach ([[$indicatorIds[0], 1], [$indicatorIds[0], 2], [$indicatorIds[1], 3]] as [$indicatorId, $value]) {
        $readingIds[] = $db->table('pr_readings')->insertGetId([
            'tenant_id' => $tenant->id,
            'indicator_id' => $indicatorId,
            'value' => $value,
            'recorded_on' => '2026-10-01',
        ]);
    }

    $rows = fn (string $table, array $ids): array => $db->table($table)->whereIn('id', $ids)->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all();
    $indicators = $rows('pr_indicators', $indicatorIds);
    $readings = $rows('pr_readings', $readingIds);

    $path = app_path('Modules/ResultsFramework/Database/Migrations/2026_11_12_100000_rename_result_tables_to_rf.php');
    expect(is_file($path))->toBeTrue();
    $migration = require $path;

    for ($run = 0; $run < 2; $run++) {
        $migration->up();

        expect($schema->hasTable('pr_indicators'))->toBeFalse()
            ->and($schema->hasTable('pr_readings'))->toBeFalse()
            ->and($rows('rf_indicators', $indicatorIds))->toBe($indicators)
            ->and($rows('rf_readings', $readingIds))->toBe($readings);
    }

    $migration->down();
    expect($rows('pr_indicators', $indicatorIds))->toBe($indicators)
        ->and($rows('pr_readings', $readingIds))->toBe($readings);
    $migration->up();
});

test('landlord rename preserves entitlements, module metadata, feature links and unrelated tenants', function (): void {
    $tenant = Tenant::factory()->create();
    $unrelated = Tenant::factory()->create();
    $module = TenantModule::query()->create(['tenant_id' => $tenant->id, 'module_slug' => 'programme-results', 'is_enabled' => true]);
    $complimentary = TenantFeature::query()->create(['tenant_id' => $tenant->id, 'feature_key' => 'module-programme-results', 'enabled' => true, 'meta' => ['source' => 'complimentary']]);
    $managed = TenantFeature::query()->create(['tenant_id' => $tenant->id, 'feature_key' => 'programme-results.manage', 'enabled' => true, 'meta' => ['source' => 'module', 'module_slug' => 'programme-results', 'value' => true]]);
    $otherFeature = TenantFeature::query()->create(['tenant_id' => $unrelated->id, 'feature_key' => 'module-core', 'enabled' => true, 'meta' => ['source' => 'complimentary']]);
    $otherFeatureBefore = $otherFeature->fresh()->getAttributes();
    $feature = Feature::factory()->create(['slug' => 'module-programme-results']);
    $package = Package::factory()->create();
    $feature->packages()->attach($package, ['value' => 'true']);

    $view = Permission::query()->create(['name' => 'results.view', 'guard_name' => 'web', 'category' => 'programme-results', 'uuid' => (string) Str::uuid()]);
    $manage = Permission::query()->create(['name' => 'results.manage', 'guard_name' => 'web', 'category' => 'custom-results', 'uuid' => (string) Str::uuid()]);

    $manager = app(ModuleManager::class);
    Cache::forget("tenant.{$tenant->id}.module.results-framework");
    expect($manager->isEnabledForTenant('results-framework', $tenant))->toBeFalse();

    $path = database_path('migrations/landlord/2026_11_12_100100_rename_programme_results_module_slug.php');
    expect(is_file($path))->toBeTrue();
    $migration = require $path;

    for ($run = 0; $run < 2; $run++) {
        $migration->up();

        expect($module->fresh()->module_slug)->toBe('results-framework')
            ->and($complimentary->fresh()->feature_key)->toBe('module-results-framework')
            ->and($complimentary->fresh()->meta['source'])->toBe('complimentary')
            ->and($managed->fresh()->feature_key)->toBe('results-framework.manage')
            ->and($managed->fresh()->meta['module_slug'])->toBe('results-framework')
            ->and($managed->fresh()->meta['value'])->toBeTrue()
            ->and($feature->fresh()->slug)->toBe('module-results-framework')
            ->and($feature->packages()->firstOrFail()->pivot->value)->toBe('true')
            ->and($manager->isEnabledForTenant('results-framework', $tenant))->toBeTrue()
            ->and($manager->isEnabledForTenant('results-framework', $unrelated))->toBeFalse()
            ->and($otherFeature->fresh()->getAttributes())->toBe($otherFeatureBefore)
            ->and($view->fresh()->category)->toBe('results-framework')
            ->and($manage->fresh()->category)->toBe('custom-results');
    }

    $migration->down();
    expect($module->fresh()->module_slug)->toBe('programme-results')
        ->and($complimentary->fresh()->feature_key)->toBe('module-programme-results')
        ->and($managed->fresh()->meta['module_slug'])->toBe('programme-results')
        ->and($feature->fresh()->slug)->toBe('module-programme-results')
        ->and($view->fresh()->category)->toBe('programme-results')
        ->and($manage->fresh()->category)->toBe('custom-results');
});
