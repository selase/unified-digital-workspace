<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\IncidentManagement\Database\Factories\IncidentPriorityFactory;
use App\Modules\IncidentManagement\Models\Incident;
use App\Modules\IncidentManagement\Models\IncidentReminder;
use App\Modules\IncidentManagement\Services\IncidentReminderService;
use App\Modules\IncidentManagement\Services\IncidentSlaService;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::parse('2026-02-07 10:00:00'));
    [$this->tenant] = setupIncidentTenantConnection();
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('generates due and SLA reminders with priority thresholds', function (): void {
    $priority = IncidentPriorityFactory::new()->forTenant($this->tenant->id)->create([
        'response_time_minutes' => 60,
        'resolution_time_minutes' => 240,
    ]);

    $assignee = User::factory()->create();

    $incident = Incident::factory()->forTenant($this->tenant->id)->create([
        'priority_id' => $priority->id,
        'due_at' => now()->addDays(2),
        'assigned_to_id' => $assignee->id,
    ]);

    app(IncidentSlaService::class)->createOrUpdate($incident);

    $created = app(IncidentReminderService::class)->generateUpcomingReminders();

    expect($created)->toBe(3);
    expect(IncidentReminder::count())->toBe(3);

    $reminders = IncidentReminder::orderBy('reminder_type')->get();

    $dueSoon = $reminders->firstWhere('reminder_type', 'due_soon');
    $response = $reminders->firstWhere('reminder_type', 'sla_response_due');
    $resolution = $reminders->firstWhere('reminder_type', 'sla_resolution_due');

    expect($dueSoon?->scheduled_for)->toEqual(now()->addDays(1));
    expect($response?->scheduled_for)->toEqual($incident->sla?->response_due_at?->copy()->subMinutes(12));
    expect($resolution?->scheduled_for)->toEqual($incident->sla?->resolution_due_at?->copy()->subMinutes(48));
});

it('does not duplicate reminders when generation is re-run', function (): void {
    $priority = IncidentPriorityFactory::new()->forTenant($this->tenant->id)->create([
        'response_time_minutes' => 30,
        'resolution_time_minutes' => 120,
    ]);

    $incident = Incident::factory()->forTenant($this->tenant->id)->create([
        'priority_id' => $priority->id,
        'due_at' => now()->addDays(2),
    ]);

    app(IncidentSlaService::class)->createOrUpdate($incident);

    $service = app(IncidentReminderService::class);

    $firstRun = $service->generateUpcomingReminders();
    $secondRun = $service->generateUpcomingReminders();

    expect($firstRun)->toBe(3);
    expect($secondRun)->toBe(0);
    expect(IncidentReminder::count())->toBe(3);
});
