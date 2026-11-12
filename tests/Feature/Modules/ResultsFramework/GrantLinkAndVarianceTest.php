<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\TenantModule;
use App\Modules\Grants\Models\GrtFunder;
use App\Modules\Grants\Services\GrantService;
use App\Modules\ResultsFramework\Models\ResultProgramme;
use App\Modules\ResultsFramework\Services\IndicatorService;
use App\Modules\ResultsFramework\Services\ProgrammeService;
use App\Modules\ResultsFramework\Services\VarianceService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

require_once __DIR__.'/fixtures.php';

test('programme grant links are tenant scoped and grant reports respect results access', function (): void {
    [$user, $tenant] = createMonitoringContext();
    enableResultMeasureModules($tenant, 'grants', 'finance');
    Permission::firstOrCreate(['name' => 'grants.view', 'guard_name' => 'web'], ['uuid' => (string) Str::uuid(), 'category' => 'grants']);
    $user->givePermissionTo('grants.view');
    $grantService = app(GrantService::class);
    $funder = GrtFunder::query()->create(['tenant_id' => $tenant->id, 'name' => 'Test funder']);
    $grant = $grantService->create($tenant->id, $funder, 10000, ['title' => 'Water grant']);
    $foreign = Tenant::factory()->create();
    $other = $grant->replicate(['uuid']);
    $other->tenant_id = $foreign->id;
    $other->save();
    expect(fn () => app(ProgrammeService::class)->create($tenant->id, 'Foreign link', ['grant_id' => $other->id]))->toThrow(DomainException::class);

    $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id]);
    $this->get(route('results-framework.programmes.create'))->assertOk()->assertSee('Water grant');
    $this->post(route('results-framework.programmes.store'), [
        'name' => 'Water works', 'status' => 'active', 'grant_id' => $grant->id,
    ])->assertRedirect();
    $programme = ResultProgramme::query()->where('name', 'Water works')->firstOrFail();
    expect($programme->grant_id)->toBe($grant->id);
    app(IndicatorService::class)->create($tenant->id, 'Wells commissioned', 10, today()->subWeek(), today()->addWeek(), ['programme_id' => $programme->id]);
    $show = route('grants.grants.show', $grant->uuid);
    $report = route('grants.grants.report', $grant->uuid);
    $this->get($show)->assertOk()->assertSee('Wells commissioned');
    expect($this->get($report)->assertOk()->streamedContent())->toContain('Water works', 'Wells commissioned');
    $user->revokePermissionTo('results.view');
    $this->get($show)->assertOk()->assertDontSee('Wells commissioned');
    expect($this->get($report)->assertOk()->streamedContent())->not->toContain('Wells commissioned');
    $user->givePermissionTo('results.view');
    TenantModule::query()->where('tenant_id', $tenant->id)->where('module_slug', 'results-framework')->update(['is_enabled' => false]);
    Cache::flush();
    $this->get($show)->assertOk()->assertDontSee('Wells commissioned');
});

test('variance notes require a behind or missed indicator and resolve only once', function (): void {
    [$user, $tenant] = createMonitoringContext();
    $indicators = app(IndicatorService::class);
    $onTrack = $indicators->create($tenant->id, 'On track', 10, today(), today()->addWeek());
    $behind = $indicators->create($tenant->id, 'Behind', 10, today()->subWeeks(3), today()->addWeek());
    $notes = app(VarianceService::class);
    expect(fn () => $notes->add($onTrack, 'Reason', null, (string) $user->uuid))->toThrow(DomainException::class);
    $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id]);
    $show = route('results-framework.indicators.show', $behind->uuid);
    $this->post(route('results-framework.indicators.variance.store', $behind->uuid), ['reason' => 'Supplier delay', 'corrective_action' => 'Change supplier'])->assertSessionHas('success');
    $note = $behind->varianceNotes()->sole();
    expect($note->noted_by)->toBe((string) $user->uuid);
    $this->get($show)->assertOk()->assertSee('Supplier delay');
    $this->post(route('results-framework.indicators.variance.resolve', [$behind->uuid, $note->id]))->assertSessionHas('success');
    expect($note->fresh()->status)->toBe('resolved')
        ->and($note->fresh()->resolved_at)->not->toBeNull()
        ->and(fn () => $notes->resolve($note->fresh(), (string) $user->uuid))->toThrow(DomainException::class);
});
