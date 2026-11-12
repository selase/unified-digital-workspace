<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Tenant;
use App\Models\TenantFeature;
use App\Models\User;
use App\Modules\ResultsFramework\Services\IndicatorService;
use App\Modules\ResultsFramework\Services\ProgrammeService;
use App\Services\ModuleManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

test('programmes can be updated and closed and validate dates and tenant-local names', function (): void {
    $tenant = Tenant::factory()->create();
    $other = Tenant::factory()->create();
    $programmes = app(ProgrammeService::class);
    $water = $programmes->create($tenant->id, 'Water', ['starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
    expect(fn () => $programmes->create($tenant->id, 'Water'))->toThrow(DomainException::class)
        ->and(fn () => $programmes->create($tenant->id, 'Invalid', ['starts_on' => '2026-12-31', 'ends_on' => '2026-01-01']))->toThrow(DomainException::class)
        ->and($programmes->create($other->id, 'Water')->tenant_id)->toBe($other->id);

    $programmes->update($water, ['name' => 'Clean water']);
    $programmes->close($water);
    expect($water->fresh()->name)->toBe('Clean water')
        ->and($water->fresh()->status)->toBe('closed');
});

test('the programme migration preserves legacy labels and assigns unlabelled indicators to General', function (): void {
    $migration = require app_path('Modules/ResultsFramework/Database/Migrations/2026_11_12_100200_create_rf_programmes.php');
    $chainMigration = require app_path('Modules/ResultsFramework/Database/Migrations/2026_11_12_100300_create_rf_results.php');
    $indicatorMigration = require app_path('Modules/ResultsFramework/Database/Migrations/2026_11_12_100400_extend_rf_indicators.php');
    $indicatorMigration->down();
    $chainMigration->down();
    $migration->down();
    $db = DB::connection('tenant');
    $tenant = Tenant::factory()->create();
    $ids = [];
    foreach (['Water', 'Water', null] as $name) {
        $ids[] = $db->table('rf_indicators')->insertGetId([
            'uuid' => (string) Str::uuid(), 'tenant_id' => $tenant->id, 'name' => 'Indicator',
            'programme' => $name, 'target' => 10, 'starts_on' => today(), 'ends_on' => today()->addDay(),
        ]);
    }

    $migration->up();
    $chainMigration->up();
    $programmes = $db->table('rf_programmes')->where('tenant_id', $tenant->id)->pluck('id', 'name');
    expect($programmes->count())->toBe(2)
        ->and($db->table('rf_indicators')->whereIn('id', $ids)->orderBy('id')->pluck('programme_id')->all())
        ->toBe([$programmes['Water'], $programmes['Water'], $programmes['General']])
        ->and($db->table('rf_indicators')->whereIn('id', $ids)->orderBy('id')->pluck('programme')->all())
        ->toBe(['Water', 'Water', null]);
    $indicatorMigration->up();
});

test('programme pages enforce access and group indicators by the linked programme', function (): void {
    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user);
    $foreign = Tenant::factory()->create();
    $service = app(ProgrammeService::class);
    $foreignProgramme = $service->create($foreign->id, 'Foreign programme');
    $water = $service->create($tenant->id, 'Water programme');
    $indicator = app(IndicatorService::class)->create($tenant->id, 'Wells completed', 10, today(), today()->addDay(), ['programme_id' => $water->id]);
    expect($indicator->programme()->firstOrFail()->id)->toBe($water->id)
        ->and(fn () => app(IndicatorService::class)->create($tenant->id, 'Foreign link', 10, today(), today()->addDay(), ['programme_id' => $foreignProgramme->id]))->toThrow(DomainException::class);

    TenantFeature::query()->create(['tenant_id' => $tenant->id, 'feature_key' => 'module-results-framework', 'enabled' => true, 'meta' => ['source' => 'complimentary']]);
    app(ModuleManager::class)->enableForTenant('results-framework', $tenant);
    Cache::flush();
    $view = Permission::query()->where('name', 'results.view')->firstOrFail();
    $manage = Permission::query()->where('name', 'results.manage')->firstOrFail();
    $user->givePermissionTo($view);
    $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id]);
    $this->get(route('results-framework.programmes.index'))->assertOk()->assertSee('Water programme')->assertDontSee('New programme');
    $this->get(route('results-framework.programmes.show', $foreignProgramme->uuid))->assertNotFound();
    $this->get(route('results-framework.index'))->assertOk()->assertSeeInOrder(['Water programme', 'Wells completed']);
    $this->post(route('results-framework.programmes.store'), ['name' => 'Forbidden', 'status' => 'planning'])->assertForbidden();
    $user->givePermissionTo($manage);
    $this->post(route('results-framework.programmes.store'), ['name' => '', 'status' => 'bad'])->assertSessionHasErrors(['name', 'status']);
    $this->post(route('results-framework.programmes.store'), ['name' => 'Education programme', 'status' => 'active'])->assertRedirect();
    $created = App\Modules\ResultsFramework\Models\ResultProgramme::query()->where('tenant_id', $tenant->id)->where('name', 'Education programme')->firstOrFail();
    $this->put(route('results-framework.programmes.update', $created->uuid), ['name' => 'Updated programme', 'status' => 'active'])->assertRedirect();
    $this->post(route('results-framework.programmes.close', $created->uuid))->assertSessionHas('success');
    expect($created->fresh()->status)->toBe('closed')
        ->and($created->fresh()->name)->toBe('Updated programme');
});
