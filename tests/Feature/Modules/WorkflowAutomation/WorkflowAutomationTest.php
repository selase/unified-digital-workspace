<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Modules\WorkflowAutomation\Models\WorkflowRule;
use App\Modules\WorkflowAutomation\Services\WorkflowEngineService;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

describe('Phase 27: Cross-Module Workflow & Process Automation Engine (TRK-WORKFLOW-AUTOMATION)', function () {
    beforeEach(function () {
        foreach (['workflows.view', 'workflows.manage', 'workflows.execute'] as $name) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['uuid' => (string) Str::uuid(), 'category' => 'workflows']
            );
        }
    });

    test('evaluates conditions and executes workflow actions successfully', function () {
        $tenant = Tenant::factory()->create(['name' => 'Tema Port Health Authority', 'slug' => 'tema-port-workflows']);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'director@temaport.test',
        ]);
        $tenant->users()->attach($admin->id);

        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web'], ['uuid' => (string) Str::uuid()]);
        $admin->assignRole($superAdminRole);
        $admin->givePermissionTo(['workflows.view', 'workflows.manage', 'workflows.execute']);

        $engine = app(WorkflowEngineService::class);

        // 1. Create Rule via Web Controller
        $createResponse = $this->actingAs($admin)
            ->withSession(['current_tenant_id' => $tenant->id])
            ->post(route('workflow-automation.store'), [
                'name' => 'Critical Incident Immediate Director Alert',
                'description' => 'Dispatches topbar notice when a critical safety incident is logged',
                'trigger_event' => 'incident.logged',
                'condition_field' => 'impact',
                'condition_operator' => '=',
                'condition_value' => 'critical',
                'action_type' => 'dispatch_notification',
                'target_role' => 'Super Admin',
                'title_template' => 'Emergency Incident: {title}',
                'body_template' => 'A critical incident ({title}) with impact {impact} occurred.',
            ]);

        $createResponse->assertRedirect();

        $rule = WorkflowRule::where('tenant_id', $tenant->id)
            ->where('name', 'Critical Incident Immediate Director Alert')
            ->first();

        expect($rule)->not->toBeNull()
            ->and($rule->is_active)->toBeTrue()
            ->and($rule->execution_count)->toBe(0);

        // 2. Trigger with non-matching payload (impact = low) -> should NOT execute
        $engine->trigger((string) $tenant->id, 'incident.logged', [
            'title' => 'Flickering Bulb',
            'impact' => 'low',
        ]);

        $rule->refresh();
        expect($rule->execution_count)->toBe(0);
        expect(DatabaseNotification::count())->toBe(0);

        // 3. Trigger with matching payload (impact = critical) -> SHOULD execute
        $logs = $engine->trigger((string) $tenant->id, 'incident.logged', [
            'title' => 'Ammonia Line Pressure Breach',
            'impact' => 'critical',
        ]);

        expect($logs)->toHaveCount(1)
            ->and($logs[0]->status)->toBe('success')
            ->and($logs[0]->execution_depth)->toBe(1);

        $rule->refresh();
        expect($rule->execution_count)->toBe(1);

        // Verify topbar in-app notification was dispatched to the Super Admin
        $notification = DatabaseNotification::where('notifiable_id', $admin->id)->first();
        expect($notification)->not->toBeNull()
            ->and($notification->data['title'])->toBe('Emergency Incident: Ammonia Line Pressure Breach')
            ->and($notification->data['message'])->toContain('impact critical occurred');
    });

    test('adversarial reject: recursion circuit breaker trips at MAX_CHAIN_DEPTH to stop infinite loops', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-circuit-breaker']);
        $engine = app(WorkflowEngineService::class);

        // Create a cascading recursive rule: trigger_event 'loop.ping' triggers action 'trigger_downstream' to 'loop.ping'
        $recursiveRule = WorkflowRule::create([
            'tenant_id' => $tenant->id,
            'name' => 'Recursive Cascade Rule',
            'trigger_event' => 'loop.ping',
            'conditions' => [],
            'actions' => [
                [
                    'type' => 'trigger_downstream',
                    'downstream_event' => 'loop.ping',
                    'downstream_payload' => ['recursion_flag' => true],
                ],
            ],
            'is_active' => true,
        ]);

        // Trigger the loop
        $logs = $engine->trigger((string) $tenant->id, 'loop.ping', ['start' => true], 1);

        // Depth 1, 2, 3 execute, Depth 4 trips circuit breaker
        expect(count($logs))->toBe(4);

        $abortedLog = collect($logs)->firstWhere('status', 'loop_aborted');
        expect($abortedLog)->not->toBeNull()
            ->and($abortedLog->execution_depth)->toBe(4)
            ->and($abortedLog->error_message)->toContain('Recursion circuit breaker tripped');
    });

    test('adversarial reject: multi-tenant boundary isolation prevents cross-tenant rule execution and viewing', function () {
        $tenantA = Tenant::factory()->create(['slug' => 'tenant-a-wf']);
        $tenantB = Tenant::factory()->create(['slug' => 'tenant-b-wf']);

        $userB = User::factory()->create(['tenant_id' => $tenantB->id]);
        $tenantB->users()->attach($userB->id);
        $userB->assignRole(Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web'], ['uuid' => (string) Str::uuid()]));
        $userB->givePermissionTo('workflows.view');

        $ruleA = WorkflowRule::create([
            'tenant_id' => $tenantA->id,
            'name' => 'Tenant A Private Automation',
            'trigger_event' => 'store.order.paid',
            'conditions' => [],
            'actions' => [
                ['type' => 'log_audit_alert', 'message' => 'Tenant A Private Action'],
            ],
            'is_active' => true,
        ]);

        $engine = app(WorkflowEngineService::class);

        // Trigger event in Tenant B
        $logs = $engine->trigger((string) $tenantB->id, 'store.order.paid', ['order_id' => 99]);

        expect($logs)->toBeEmpty();
        $ruleA->refresh();
        expect($ruleA->execution_count)->toBe(0);

        // User B accessing Tenant A's rule gets 404
        $response = $this->actingAs($userB)
            ->withSession(['current_tenant_id' => $tenantB->id])
            ->get(route('workflow-automation.show', $ruleA));

        $response->assertStatus(404);
    });

    test('adversarial reject: unauthorized users without required workflow permissions receive 403', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-wf-rbac']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $tenant->users()->attach($user->id);

        $rule = WorkflowRule::create([
            'tenant_id' => $tenant->id,
            'name' => 'Internal Rule',
            'trigger_event' => 'custom.event',
            'conditions' => [],
            'actions' => [],
            'is_active' => true,
        ]);

        // 1. Without workflows.view -> 403 on index
        $this->actingAs($user)
            ->withSession(['current_tenant_id' => $tenant->id])
            ->get(route('workflow-automation.index'))
            ->assertStatus(403);

        // Grant workflows.view only
        $user->givePermissionTo('workflows.view');

        $this->actingAs($user)
            ->withSession(['current_tenant_id' => $tenant->id])
            ->get(route('workflow-automation.index'))
            ->assertStatus(200);

        // 2. Without workflows.manage -> 403 on store
        $this->actingAs($user)
            ->withSession(['current_tenant_id' => $tenant->id])
            ->post(route('workflow-automation.store'), [
                'name' => 'Unauthorized Rule',
                'trigger_event' => 'unauth.event',
                'action_type' => 'log_audit_alert',
            ])
            ->assertStatus(403);

        // 3. Without workflows.execute -> 403 on testRun
        $this->actingAs($user)
            ->withSession(['current_tenant_id' => $tenant->id])
            ->post(route('workflow-automation.test-run', $rule), [
                'test_payload' => '{}',
            ])
            ->assertStatus(403);

        // Grant workflows.execute -> 200/redirect
        $user->givePermissionTo('workflows.execute');
        $this->actingAs($user)
            ->withSession(['current_tenant_id' => $tenant->id])
            ->post(route('workflow-automation.test-run', $rule), [
                'test_payload' => '{}',
            ])
            ->assertRedirect();
    });
});
