<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Modules\ResultsFramework\Services\IndicatorService;
use App\Modules\ResultsFramework\Services\ProgrammeService;
use App\Modules\ResultsFramework\Services\ResultChainService;

test('a chain is ordered and rejects invalid levels, cycles and parents outside its programme and tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $other = Tenant::factory()->create();
    $programmes = app(ProgrammeService::class);
    $programme = $programmes->create($tenant->id, 'Water');
    $another = $programmes->create($tenant->id, 'Education');
    $foreign = $programmes->create($other->id, 'Health');
    $chain = app(ResultChainService::class);
    $goal = $chain->add($programme, 'goal', 'Water access');
    $outcome = $chain->add($programme, 'outcome', 'Cleaner water', $goal, ['sort_order' => 2]);
    $early = $chain->add($programme, 'outcome', 'Improved access', $goal, ['sort_order' => 1]);
    $output = $chain->add($programme, 'output', 'Wells', $outcome, ['assumptions' => 'Maintenance available']);
    $activity = $chain->add($programme, 'activity', 'Drill a well', $output);
    $otherParent = $chain->add($another, 'goal', 'Other goal');
    $foreignParent = $chain->add($foreign, 'goal', 'Foreign goal');

    expect(fn () => $chain->add($programme, 'outcome', 'Invalid', $activity))->toThrow(DomainException::class)
        ->and(fn () => $chain->add($programme, 'output', 'Invalid', $otherParent))->toThrow(DomainException::class)
        ->and(fn () => $chain->add($programme, 'output', 'Invalid', $foreignParent))->toThrow(DomainException::class)
        ->and(fn () => $chain->move($goal, $activity, 1))->toThrow(DomainException::class)
        ->and($chain->tree($programme)->sole()->children->pluck('id')->all())->toBe([$early->id, $outcome->id])
        ->and($chain->tree($programme)->sole()->children->last()->children->sole()->children->sole()->id)->toBe($activity->id);
    $chain->move($early, $goal, 3);
    expect($chain->tree($programme)->sole()->children->pluck('id')->all())->toBe([$outcome->id, $early->id]);
});

test('removal refuses children and indicators and an indicator cannot attach outside its programme', function (): void {
    $tenant = Tenant::factory()->create();
    $other = Tenant::factory()->create();
    $programmes = app(ProgrammeService::class);
    $programme = $programmes->create($tenant->id, 'Water');
    $another = $programmes->create($tenant->id, 'Education');
    $foreign = $programmes->create($other->id, 'Health');
    $chain = app(ResultChainService::class);
    $parent = $chain->add($programme, 'goal', 'Goal');
    $child = $chain->add($programme, 'outcome', 'Outcome', $parent);
    $wrong = $chain->add($another, 'goal', 'Other goal');
    $foreignNode = $chain->add($foreign, 'goal', 'Foreign goal');
    $indicators = app(IndicatorService::class);
    $create = fn (int $node) => $indicators->create($tenant->id, 'Indicator', 10, today(), today()->addDay(), ['programme_id' => $programme->id, 'result_id' => $node]);

    expect(fn () => $chain->remove($parent))->toThrow(DomainException::class, 'children')
        ->and(fn () => $create($wrong->id))->toThrow(DomainException::class)
        ->and(fn () => $create($foreignNode->id))->toThrow(DomainException::class);

    $indicator = $create($child->id);
    expect($indicator->node()->firstOrFail()->id)->toBe($child->id)
        ->and(fn () => $chain->remove($child))->toThrow(DomainException::class, 'indicators');
    $indicator->delete();
    $chain->remove($child);
    $chain->remove($parent);
    expect($chain->tree($programme))->toBeEmpty();
});

test('a viewer sees the chain and risks but cannot change it', function (): void {
    $user = App\Models\User::factory()->create();
    $tenant = setActiveTenantForTest($user);
    App\Models\TenantFeature::query()->create(['tenant_id' => $tenant->id, 'feature_key' => 'module-results-framework', 'enabled' => true, 'meta' => ['source' => 'complimentary']]);
    app(App\Services\ModuleManager::class)->enableForTenant('results-framework', $tenant);
    Illuminate\Support\Facades\Cache::flush();
    $user->givePermissionTo('results.view');
    $programme = app(ProgrammeService::class)->create($tenant->id, 'Water');
    app(ResultChainService::class)->add($programme, 'goal', 'Reliable water', details: ['risks' => 'Dry season']);

    $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id]);
    $this->get(route('results-framework.programmes.show', $programme->uuid))
        ->assertOk()->assertSee('Reliable water')->assertSee('Dry season')->assertDontSee('Add result');
    $this->post(route('results-framework.programmes.results.store', $programme->uuid), ['level' => 'goal', 'title' => 'Denied'])
        ->assertForbidden();
});
