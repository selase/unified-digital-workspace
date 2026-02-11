<?php

declare(strict_types=1);

use App\Modules\IncidentManagement\Models\Incident;
use App\Policies\IncidentPolicy;

beforeEach(function (): void {
    setupIncidentTenantConnection();
});

it('allows viewAny when user has incidents.view permission', function (): void {
    [$user] = createIncidentApiContext();

    $policy = app(IncidentPolicy::class);

    expect($policy->viewAny($user))->toBeTrue();
});

it('allows viewing incidents when reporter or assignee', function (): void {
    [$reporter, $tenant] = createIncidentApiContext();
    $policy = app(IncidentPolicy::class);

    $incident = Incident::factory()->forTenant($tenant->id)->create([
        'reported_by_id' => (string) $reporter->uuid,
    ]);

    expect($policy->view($reporter, $incident))->toBeTrue();

    $assignee = createIncidentApiContext()[0];
    $incident->assigned_to_id = (string) $assignee->uuid;
    $incident->save();

    expect($policy->view($assignee, $incident))->toBeTrue();
});

it('denies viewing incidents for unrelated users without permission', function (): void {
    $incident = Incident::factory()->create();
    $user = App\Models\User::factory()->create();

    $policy = app(IncidentPolicy::class);

    expect($policy->view($user, $incident))->toBeFalse();
});

it('allows updates for users with permission or ownership', function (): void {
    [$user, $tenant] = createIncidentApiContext();
    $policy = app(IncidentPolicy::class);

    $incident = Incident::factory()->forTenant($tenant->id)->create([
        'reported_by_id' => (string) $user->uuid,
    ]);

    expect($policy->update($user, $incident))->toBeTrue();

    $assignee = App\Models\User::factory()->create();
    $incident->assigned_to_id = (string) $assignee->uuid;
    $incident->save();

    expect($policy->update($assignee, $incident))->toBeTrue();
});

it('denies updates for unrelated users without permission', function (): void {
    $incident = Incident::factory()->create();
    $user = App\Models\User::factory()->create();

    $policy = app(IncidentPolicy::class);

    expect($policy->update($user, $incident))->toBeFalse();
});
