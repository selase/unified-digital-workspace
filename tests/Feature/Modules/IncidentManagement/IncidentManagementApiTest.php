<?php

declare(strict_types=1);

use App\Mail\Incidents\IncidentAssigned;
use App\Mail\Incidents\IncidentEscalated;
use App\Mail\Incidents\IncidentReminder as IncidentReminderMail;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\IncidentManagement\Database\Factories\IncidentCategoryFactory;
use App\Modules\IncidentManagement\Database\Factories\IncidentPriorityFactory;
use App\Modules\IncidentManagement\Database\Factories\IncidentStatusFactory;
use App\Modules\IncidentManagement\Models\Incident;
use App\Modules\IncidentManagement\Models\IncidentCategory;
use App\Modules\IncidentManagement\Models\IncidentPriority;
use App\Modules\IncidentManagement\Models\IncidentReminder;
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

/**
 * @return array{0: User, 1: Tenant}
 */
function createIncidentApiContext(): array
{
    $user = User::factory()->create();
    $tenant = app(TenantContext::class)->getTenant();

    if (! $tenant) {
        [$tenant] = setupIncidentTenantConnection($user);
    } else {
        $tenant->users()->syncWithoutDetaching($user->id);
    }

    $permissions = [
        'incidents.view',
        'incidents.create',
        'incidents.update',
        'incidents.delete',
        'incidents.assign',
        'incidents.delegate',
        'incidents.escalate',
        'incidents.tasks.manage',
        'incidents.comments.manage',
        'incidents.priorities.manage',
        'incidents.statuses.manage',
        'incidents.categories.manage',
    ];

    foreach ($permissions as $permission) {
        $existing = Permission::query()
            ->where('name', $permission)
            ->where('guard_name', 'web')
            ->first();

        if (! $existing) {
            Permission::create([
                'uuid' => (string) Str::uuid(),
                'name' => $permission,
                'guard_name' => 'web',
                'category' => 'Incidents',
            ]);
        }
    }

    $user->givePermissionTo($permissions);

    app(ModuleManager::class)->enableForTenant('incident-management', $tenant);

    return [$user, $tenant];
}

it('creates and updates incident categories', function () {
    [$user, $tenant] = createIncidentApiContext();

    $createResponse = actingAs($user, 'sanctum')->postJson('/api/incident-management/v1/categories', [
        'name' => 'Complaints',
    ]);

    $createResponse->assertSuccessful();

    $categoryUuid = $createResponse->json('data.uuid');

    $updateResponse = actingAs($user, 'sanctum')->putJson("/api/incident-management/v1/categories/{$categoryUuid}", [
        'description' => 'Customer complaints',
    ]);

    $updateResponse->assertSuccessful()
        ->assertJsonFragment([
            'uuid' => $categoryUuid,
            'description' => 'Customer complaints',
        ]);
});

it('creates and updates incident priorities', function () {
    [$user, $tenant] = createIncidentApiContext();

    $createResponse = actingAs($user, 'sanctum')->postJson('/api/incident-management/v1/priorities', [
        'name' => 'High',
        'level' => 3,
    ]);

    $createResponse->assertSuccessful();

    $priorityUuid = $createResponse->json('data.uuid');

    $updateResponse = actingAs($user, 'sanctum')->putJson("/api/incident-management/v1/priorities/{$priorityUuid}", [
        'resolution_time_minutes' => 720,
    ]);

    $updateResponse->assertSuccessful()
        ->assertJsonFragment([
            'uuid' => $priorityUuid,
            'resolution_time_minutes' => 720,
        ]);
});

it('creates and updates incident statuses', function () {
    [$user, $tenant] = createIncidentApiContext();

    $createResponse = actingAs($user, 'sanctum')->postJson('/api/incident-management/v1/statuses', [
        'name' => 'Open',
        'is_default' => true,
    ]);

    $createResponse->assertSuccessful();

    $statusUuid = $createResponse->json('data.uuid');

    $updateResponse = actingAs($user, 'sanctum')->putJson("/api/incident-management/v1/statuses/{$statusUuid}", [
        'sort_order' => 2,
    ]);

    $updateResponse->assertSuccessful()
        ->assertJsonFragment([
            'uuid' => $statusUuid,
            'sort_order' => 2,
        ]);
});

it('creates and manages incidents', function () {
    [$user, $tenant] = createIncidentApiContext();

    $category = IncidentCategoryFactory::new()->forTenant($tenant->id)->create();
    $priority = IncidentPriorityFactory::new()->forTenant($tenant->id)->create();
    $status = IncidentStatusFactory::new()->forTenant($tenant->id)->create(['is_default' => true]);

    $createResponse = actingAs($user, 'sanctum')->postJson('/api/incident-management/v1/incidents', [
        'title' => 'Login issue',
        'description' => 'Cannot access portal',
        'category_id' => $category->id,
        'priority_id' => $priority->id,
    ]);

    $createResponse->assertSuccessful();

    $incidentId = $createResponse->json('data.id');

    $updateResponse = actingAs($user, 'sanctum')->putJson("/api/incident-management/v1/incidents/{$incidentId}", [
        'status_id' => $status->id,
        'impact' => 'high',
    ]);

    $updateResponse->assertSuccessful()
        ->assertJsonFragment([
            'id' => $incidentId,
            'impact' => 'high',
        ]);

    expect(Activity::where('subject_id', $incidentId)->where('event', 'created')->exists())->toBeTrue();
    expect(Activity::where('subject_id', $incidentId)->where('event', 'updated')->exists())->toBeTrue();
});

it('assigns, delegates, escalates, resolves, and closes incidents', function () {
    [$user, $tenant] = createIncidentApiContext();

    Mail::fake();

    $status = IncidentStatusFactory::new()->forTenant($tenant->id)->create(['is_default' => true]);
    $priority = IncidentPriorityFactory::new()->forTenant($tenant->id)->create();
    $incident = Incident::factory()->forTenant($tenant->id)->create([
        'status_id' => $status->id,
        'priority_id' => $priority->id,
        'reported_by_id' => (string) $user->uuid,
    ]);

    $assignee = User::factory()->create();

    actingAs($user, 'sanctum')
        ->postJson("/api/incident-management/v1/incidents/{$incident->id}/assign", [
            'assigned_to_id' => (string) $assignee->uuid,
        ])
        ->assertSuccessful()
        ->assertJsonFragment([
            'assigned_to_id' => (string) $assignee->uuid,
        ]);

    expect(Activity::where('subject_id', $incident->id)->where('event', 'assigned')->exists())->toBeTrue();

    Mail::assertQueued(IncidentAssigned::class);

    $delegate = User::factory()->create();

    actingAs($user, 'sanctum')
        ->postJson("/api/incident-management/v1/incidents/{$incident->id}/delegate", [
            'assigned_to_id' => (string) $delegate->uuid,
        ])
        ->assertSuccessful()
        ->assertJsonFragment([
            'assigned_to_id' => (string) $delegate->uuid,
        ]);

    $priorityEscalated = IncidentPriorityFactory::new()->forTenant($tenant->id)->create();

    actingAs($user, 'sanctum')
        ->postJson("/api/incident-management/v1/incidents/{$incident->id}/escalate", [
            'to_priority_id' => $priorityEscalated->id,
        ])
        ->assertSuccessful()
        ->assertJsonFragment([
            'priority_id' => $priorityEscalated->id,
        ]);

    Mail::assertQueued(IncidentEscalated::class);

    actingAs($user, 'sanctum')
        ->postJson("/api/incident-management/v1/incidents/{$incident->id}/resolve", [
            'resolved_at' => now()->toISOString(),
        ])
        ->assertSuccessful();

    actingAs($user, 'sanctum')
        ->postJson("/api/incident-management/v1/incidents/{$incident->id}/close", [
            'closed_at' => now()->toISOString(),
        ])
        ->assertSuccessful();
});

it('creates tasks and comments for incidents', function () {
    [$user, $tenant] = createIncidentApiContext();

    $status = IncidentStatusFactory::new()->forTenant($tenant->id)->create(['is_default' => true]);
    $incident = Incident::factory()->forTenant($tenant->id)->create([
        'status_id' => $status->id,
        'reported_by_id' => (string) $user->uuid,
    ]);

    actingAs($user, 'sanctum')
        ->postJson("/api/incident-management/v1/incidents/{$incident->id}/tasks", [
            'title' => 'Investigate logs',
        ])
        ->assertSuccessful()
        ->assertJsonFragment([
            'title' => 'Investigate logs',
        ]);

    actingAs($user, 'sanctum')
        ->postJson("/api/incident-management/v1/incidents/{$incident->id}/comments", [
            'body' => 'We are investigating.',
            'is_internal' => true,
        ])
        ->assertSuccessful()
        ->assertJsonFragment([
            'body' => 'We are investigating.',
        ]);
});

it('accepts public incident submissions', function () {
    [$user, $tenant] = createIncidentApiContext();

    $category = IncidentCategory::factory()->forTenant($tenant->id)->create();
    $priority = IncidentPriority::factory()->forTenant($tenant->id)->create();

    $response = postJson('/api/incident-management/v1/public/submit', [
        'name' => 'External Reporter',
        'email' => 'reporter@example.com',
        'title' => 'Public incident',
        'description' => 'External report',
        'category_id' => $category->id,
        'priority_id' => $priority->id,
        'recaptcha_token' => 'recaptcha-pass',
    ]);

    $response->assertSuccessful()
        ->assertJsonFragment([
            'title' => 'Public incident',
        ]);
});

it('dispatches incident reminders by email', function () {
    [$user, $tenant] = createIncidentApiContext();

    Mail::fake();

    $incident = Incident::factory()->forTenant($tenant->id)->create([
        'assigned_to_id' => (string) $user->uuid,
    ]);

    $reminder = IncidentReminder::create([
        'incident_id' => $incident->id,
        'reminder_type' => 'due_soon',
        'scheduled_for' => now()->subMinute(),
        'channel' => 'email',
        'metadata' => [
            'user_id' => (string) $user->uuid,
        ],
    ]);

    Artisan::call('incidents:dispatch-reminders');

    Mail::assertQueued(IncidentReminderMail::class);
    $reminder->refresh();

    expect($reminder->sent_at)->not->toBeNull();
});

it('returns dashboard stats for incidents', function () {
    [$user, $tenant] = createIncidentApiContext();

    $status = IncidentStatusFactory::new()->forTenant($tenant->id)->create();
    $priority = IncidentPriorityFactory::new()->forTenant($tenant->id)->create();

    Incident::factory()->forTenant($tenant->id)->create([
        'status_id' => $status->id,
        'priority_id' => $priority->id,
        'due_at' => now()->subDay(),
    ]);

    $breached = Incident::factory()->forTenant($tenant->id)->create([
        'status_id' => $status->id,
        'priority_id' => $priority->id,
    ]);

    App\Modules\IncidentManagement\Models\IncidentSla::create([
        'incident_id' => $breached->id,
        'is_breached' => true,
    ]);

    $response = actingAs($user, 'sanctum')->getJson('/api/incident-management/v1/incidents/stats');

    $response->assertSuccessful()
        ->assertJsonStructure([
            'status_counts',
            'priority_counts',
            'overdue',
            'sla_breaches',
        ]);
});

it('exports incident audit log as csv', function () {
    [$user, $tenant] = createIncidentApiContext();

    $incident = Incident::factory()->forTenant($tenant->id)->create();

    activity()
        ->performedOn($incident)
        ->causedBy($user)
        ->withProperties(['action' => 'update'])
        ->log('incident updated');

    $response = actingAs($user, 'sanctum')->get('/api/incident-management/v1/incidents/export');

    $response->assertDownload('incidents-audit.csv');

    $csv = $response->streamedContent();

    expect($csv)->toContain((string) $incident->id);
    expect($csv)->toContain('incident updated');
});
