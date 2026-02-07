<?php

declare(strict_types=1);

use App\Modules\IncidentManagement\Models\Incident;
use App\Policies\IncidentPolicy;

it('allows viewAny when user has incidents.view permission', function (): void {
    [$user] = createIncidentApiContext();

    $policy = app(IncidentPolicy::class);

    expect($policy->viewAny($user))->toBeTrue();
});

it('allows viewing incidents when reporter or assignee', function (): void {
    [$reporter, $tenant] = createIncidentApiContext();
    $policy = app(IncidentPolicy::class);

    $incident = Incident::factory()->forTenant($tenant->id)->create([
        'reported_by_id' => $reporter->id,
    ]);

    expect($policy->view($reporter, $incident))->toBeTrue();

    $assignee = createIncidentApiContext()[0];
    $incident->assigned_to_id = $assignee->id;
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
        'reported_by_id' => $user->id,
    ]);

    expect($policy->update($user, $incident))->toBeTrue();

    $assignee = App\Models\User::factory()->create();
    $incident->assigned_to_id = $assignee->id;
    $incident->save();

    expect($policy->update($assignee, $incident))->toBeTrue();
});

it('denies updates for unrelated users without permission', function (): void {
    $incident = Incident::factory()->create();
    $user = App\Models\User::factory()->create();

    $policy = app(IncidentPolicy::class);

    expect($policy->update($user, $incident))->toBeFalse();
});
