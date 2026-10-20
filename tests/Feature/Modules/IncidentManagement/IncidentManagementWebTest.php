<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Modules\IncidentManagement\Models\Incident;
use App\Modules\IncidentManagement\Models\IncidentCategory;
use App\Modules\IncidentManagement\Models\IncidentPriority;
use App\Modules\IncidentManagement\Models\IncidentStatus;
use App\Modules\IncidentManagement\Models\IncidentTask;
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

/**
 * @return array{0: User, 1: Tenant}
 */
function createIncidentWebContext(): array
{
    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user);

    Session::put('active_tenant_id', $tenant->id);
    setPermissionsTeamId($tenant->id);
    app(TenantContext::class)->setTenant($tenant);

    $permissions = [
        'incidents.view',
        'incidents.create',
        'incidents.update',
        'incidents.delete',
        'incidents.assign',
        'incidents.escalate',
        'incidents.tasks.manage',
        'incidents.comments.manage',
    ];

    foreach ($permissions as $permission) {
        Permission::firstOrCreate([
            'name' => $permission,
            'category' => 'incidents',
            'guard_name' => 'web',
        ], [
            'uuid' => (string) Str::uuid(),
        ]);
    }

    $user->givePermissionTo($permissions);
    app(ModuleManager::class)->enableForTenant('incident-management', $tenant);

    return [$user, $tenant];
}

it('renders incident hub and register with metronic components', function (): void {
    [$user, $tenant] = createIncidentWebContext();

    $category = IncidentCategory::create([
        'tenant_id' => $tenant->id,
        'name' => 'Clinical Safety',
        'slug' => 'clinical-safety',
    ]);
    $priority = IncidentPriority::create([
        'tenant_id' => $tenant->id,
        'name' => 'High',
        'slug' => 'high',
        'level' => 2,
    ]);
    $status = IncidentStatus::create([
        'tenant_id' => $tenant->id,
        'name' => 'In Triage',
        'slug' => 'in-triage',
        'is_default' => true,
    ]);

    $incident = Incident::create([
        'tenant_id' => $tenant->id,
        'title' => 'Medication Administration Delay in Pediatric Ward',
        'description' => 'IV Antibiotics delivery was delayed by 90 minutes due to dispensing discrepancy.',
        'category_id' => $category->id,
        'priority_id' => $priority->id,
        'status_id' => $status->id,
        'reported_by_id' => (string) $user->uuid,
        'reported_via' => 'internal',
    ]);

    $hubResponse = test()->actingAs($user)->get(route('incident-management.index'));
    $hubResponse->assertOk()
        ->assertSee('Incident Management Hub')
        ->assertSee('Medication Administration Delay in Pediatric Ward')
        ->assertSee('Incident Register');

    $regResponse = test()->actingAs($user)->get(route('incident-management.incidents.index'));
    $regResponse->assertOk()
        ->assertSee('Incident Register')
        ->assertSee('Medication Administration Delay in Pediatric Ward')
        ->assertSee('Clinical Safety');
});

it('renders create incident form for authorized users', function (): void {
    [$user] = createIncidentWebContext();

    $response = test()->actingAs($user)->get(route('incident-management.incidents.create'));
    $response->assertOk()
        ->assertSee('Log New Incident')
        ->assertSee('Incident Title')
        ->assertSee('Detailed Occurrence Description');
});

it('stores a new incident with reference code and logs activity', function (): void {
    [$user, $tenant] = createIncidentWebContext();

    $category = IncidentCategory::create([
        'tenant_id' => $tenant->id,
        'name' => 'Patient Fall',
        'slug' => 'patient-fall',
    ]);
    $priority = IncidentPriority::create([
        'tenant_id' => $tenant->id,
        'name' => 'Medium',
        'slug' => 'medium',
        'level' => 3,
    ]);
    $status = IncidentStatus::create([
        'tenant_id' => $tenant->id,
        'name' => 'Reported',
        'slug' => 'reported',
        'is_default' => true,
    ]);

    $response = test()->actingAs($user)->post(route('incident-management.incidents.store'), [
        'title' => 'Patient Slip in Ward 3 Corridor',
        'description' => 'Patient lost footing on wet surface near nurse station; assisted immediately by orderly.',
        'category_id' => $category->id,
        'priority_id' => $priority->id,
        'status_id' => $status->id,
        'impact' => 'No fractures observed; ice pack applied to left wrist.',
    ]);

    $incident = Incident::query()->where('title', 'Patient Slip in Ward 3 Corridor')->first();
    expect($incident)->not->toBeNull();
    assert($incident !== null);

    $response->assertRedirect(route('incident-management.incidents.show', $incident));

    expect($incident->reference_code)->not->toBeEmpty()
        ->and($incident->reported_by_id)->toBe((string) $user->uuid)
        ->and($incident->reported_via)->toBe('internal');
});

it('displays incident show view with narrative, status, and CAPA tasks', function (): void {
    [$user, $tenant] = createIncidentWebContext();

    $category = IncidentCategory::create(['tenant_id' => $tenant->id, 'name' => 'Surgical Equipment', 'slug' => 'surgical-equipment']);
    $priority = IncidentPriority::create(['tenant_id' => $tenant->id, 'name' => 'Critical', 'slug' => 'critical', 'level' => 1]);
    $status = IncidentStatus::create(['tenant_id' => $tenant->id, 'name' => 'Under Investigation', 'slug' => 'under-investigation', 'is_default' => true]);

    $incident = Incident::create([
        'tenant_id' => $tenant->id,
        'title' => 'Operating Theatre 2 Autoclave Pressure Fluctuation',
        'description' => 'Sterilization pressure sensor triggered audible alert mid-cycle.',
        'category_id' => $category->id,
        'priority_id' => $priority->id,
        'status_id' => $status->id,
        'reported_by_id' => (string) $user->uuid,
        'reported_via' => 'internal',
    ]);

    $task = IncidentTask::create([
        'tenant_id' => $tenant->id,
        'incident_id' => $incident->id,
        'title' => 'Calibrate pressure transducer sensor',
        'status' => 'pending',
    ]);

    $response = test()->actingAs($user)->get(route('incident-management.incidents.show', $incident));
    $response->assertOk()
        ->assertSee('Operating Theatre 2 Autoclave Pressure Fluctuation')
        ->assertSee('Calibrate pressure transducer sensor')
        ->assertSee('Corrective & Preventive Actions (CAPA)');
});

it('allows assigning an investigator and escalating priority', function (): void {
    [$user, $tenant] = createIncidentWebContext();
    $investigator = User::factory()->create();
    $tenant->users()->attach($investigator->id);

    $pLow = IncidentPriority::create(['tenant_id' => $tenant->id, 'name' => 'Low', 'slug' => 'low', 'level' => 4]);
    $pUrgent = IncidentPriority::create(['tenant_id' => $tenant->id, 'name' => 'Urgent', 'slug' => 'urgent', 'level' => 1]);
    $status = IncidentStatus::create(['tenant_id' => $tenant->id, 'name' => 'Open', 'slug' => 'open', 'is_default' => true]);

    $incident = Incident::create([
        'tenant_id' => $tenant->id,
        'title' => 'Laboratory Centrifuge Unbalance',
        'description' => 'Centrifuge unit vibrating during serum separation.',
        'priority_id' => $pLow->id,
        'status_id' => $status->id,
        'reported_by_id' => (string) $user->uuid,
        'reported_via' => 'internal',
    ]);

    // Assign
    $assignResponse = test()->actingAs($user)->post(route('incident-management.incidents.assign', $incident), [
        'assigned_to_id' => (string) $investigator->uuid,
        'note' => 'Lead biomedical engineer assigned',
    ]);
    $assignResponse->assertRedirect(route('incident-management.incidents.show', $incident));

    $incident->refresh();
    expect($incident->assigned_to_id)->toBe((string) $investigator->uuid);

    // Escalate
    $escResponse = test()->actingAs($user)->post(route('incident-management.incidents.escalate', $incident), [
        'to_priority_id' => $pUrgent->id,
        'reason' => 'Multiple clinical samples pending test results',
    ]);
    $escResponse->assertRedirect(route('incident-management.incidents.show', $incident));

    $incident->refresh();
    expect($incident->priority_id)->toBe($pUrgent->id);
});

it('adds CAPA action tasks and toggles task completion', function (): void {
    [$user, $tenant] = createIncidentWebContext();

    $incident = Incident::create([
        'tenant_id' => $tenant->id,
        'title' => 'Needlestick Injury in Phlebotomy',
        'description' => 'Staff reported superficial puncture after blood draw.',
        'reported_by_id' => (string) $user->uuid,
        'reported_via' => 'internal',
    ]);

    // Add Task
    $taskResponse = test()->actingAs($user)->post(route('incident-management.incidents.tasks.store', $incident), [
        'title' => 'Complete post-exposure prophylaxis protocol',
        'due_at' => now()->addDays(2)->toDateString(),
    ]);
    $taskResponse->assertRedirect(route('incident-management.incidents.show', $incident));

    $task = $incident->tasks()->first();
    expect($task)->not->toBeNull();
    assert($task !== null);
    expect($task->title)->toBe('Complete post-exposure prophylaxis protocol')
        ->and($task->status)->toBe('pending');

    // Toggle Task
    $toggleResponse = test()->actingAs($user)->post(route('incident-management.incidents.tasks.toggle', [$incident, $task]));
    $toggleResponse->assertRedirect(route('incident-management.incidents.show', $incident));

    $task->refresh();
    expect($task->status)->toBe('completed')
        ->and($task->completed_at)->not->toBeNull();
});

it('posts investigation comments to incident thread', function (): void {
    [$user, $tenant] = createIncidentWebContext();

    $incident = Incident::create([
        'tenant_id' => $tenant->id,
        'title' => 'Power Backup Delay in Server Room',
        'description' => 'UPS transferred 4 seconds slower than SLA baseline.',
        'reported_by_id' => (string) $user->uuid,
        'reported_via' => 'internal',
    ]);

    $commentResponse = test()->actingAs($user)->post(route('incident-management.incidents.comments.store', $incident), [
        'body' => 'Generator technician inspected ATS switchgear; capacitor replacement ordered.',
        'is_internal' => true,
    ]);
    $commentResponse->assertRedirect(route('incident-management.incidents.show', $incident));

    expect($incident->comments()->count())->toBe(1);
    expect($incident->comments()->first()->body)->toBe('Generator technician inspected ATS switchgear; capacitor replacement ordered.');
});

it('marks incident as resolved and closes incident', function (): void {
    [$user, $tenant] = createIncidentWebContext();

    $incident = Incident::create([
        'tenant_id' => $tenant->id,
        'title' => 'Elevator 3 Door Sensor Fault',
        'description' => 'Door safety edge sensor disengaged on Level 4.',
        'reported_by_id' => (string) $user->uuid,
        'reported_via' => 'internal',
    ]);

    // Resolve
    $resResponse = test()->actingAs($user)->post(route('incident-management.incidents.resolve', $incident));
    $resResponse->assertRedirect(route('incident-management.incidents.show', $incident));

    $incident->refresh();
    expect($incident->resolved_at)->not->toBeNull();

    // Close
    $closeResponse = test()->actingAs($user)->post(route('incident-management.incidents.close', $incident));
    $closeResponse->assertRedirect(route('incident-management.incidents.show', $incident));

    $incident->refresh();
    expect($incident->closed_at)->not->toBeNull();
});

test('incident progress reports and the task board load when reports and tasks have people attached', function (): void {
    [$user, $tenant] = createIncidentWebContext();

    $category = IncidentCategory::create(['tenant_id' => $tenant->id, 'name' => 'Facilities', 'slug' => 'facilities']);
    $priority = IncidentPriority::create(['tenant_id' => $tenant->id, 'name' => 'Medium', 'slug' => 'medium', 'level' => 3]);
    $status = IncidentStatus::create(['tenant_id' => $tenant->id, 'name' => 'Open', 'slug' => 'open', 'is_default' => true]);

    $incident = Incident::create([
        'tenant_id' => $tenant->id,
        'title' => 'Burst pipe in ward corridor',
        'description' => 'Water on the floor near ward 3.',
        'category_id' => $category->id,
        'priority_id' => $priority->id,
        'status_id' => $status->id,
        'reported_by_id' => (string) $user->uuid,
        'reported_via' => 'internal',
    ]);

    App\Modules\IncidentManagement\Models\IncidentProgressReport::create([
        'tenant_id' => $tenant->id,
        'incident_id' => $incident->id,
        'user_id' => (string) $user->uuid,
        'body' => 'Plumber has isolated the supply.',
        'is_internal' => false,
    ]);

    IncidentTask::create([
        'tenant_id' => $tenant->id,
        'incident_id' => $incident->id,
        'title' => 'Replace the damaged section',
        'assigned_to_id' => (string) $user->uuid,
        'status' => 'open',
    ]);

    test()->actingAs($user)->get(route('incident-management.reports.index'))
        ->assertOk()
        ->assertSee('Plumber has isolated the supply.');

    test()->actingAs($user)->get(route('incident-management.tasks.index'))
        ->assertOk()
        ->assertSee('Replace the damaged section');
});
