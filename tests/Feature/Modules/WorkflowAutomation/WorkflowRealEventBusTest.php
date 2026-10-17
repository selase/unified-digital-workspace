<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Modules\WorkflowAutomation\Events\WorkflowDomainEvent;
use App\Modules\WorkflowAutomation\Listeners\WorkflowDomainEventListener;
use App\Modules\WorkflowAutomation\Models\WorkflowRule;
use App\Modules\WorkflowAutomation\Services\WorkflowEngineService;
use App\Notifications\InstitutionalWorkflowAlert;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

describe('P1-1: Real Workflow Event Bus & Downstream Chaining Test', function () {
    beforeEach(function () {
        foreach (['workflows.view', 'workflows.manage', 'workflows.execute'] as $name) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['uuid' => (string) Str::uuid(), 'category' => 'workflows']
            );
        }
    });

    test('WorkflowDomainEventListener executes matching rules and delivers InstitutionalWorkflowAlert', function () {
        Notification::fake();

        $tenant = Tenant::factory()->create(['name' => 'Workflow Event Tenant', 'slug' => 'wf-event-tenant']);

        $role = Role::firstOrCreate(['name' => 'Safety Officer', 'guard_name' => 'web'], ['uuid' => (string) Str::uuid()]);
        $officer = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'safety@workflow.test',
        ]);
        $tenant->users()->attach($officer->id);
        $officer->assignRole($role);

        WorkflowRule::create([
            'tenant_id' => $tenant->id,
            'name' => 'High Severity Incident Alert',
            'trigger_event' => 'incident.escalated',
            'conditions' => [
                ['field' => 'severity', 'operator' => 'equals', 'value' => 'critical'],
            ],
            'actions' => [
                [
                    'type' => 'send_notification',
                    'target_role' => 'Safety Officer',
                    'message' => 'Critical incident detected: {{title}}',
                ],
            ],
            'is_active' => true,
        ]);

        $event = new WorkflowDomainEvent(
            tenantId: (string) $tenant->id,
            eventName: 'incident.escalated',
            payload: [
                'title' => 'Chemical Spill at Dock 4',
                'severity' => 'critical',
            ]
        );

        $listener = app(WorkflowDomainEventListener::class);
        $listener->handle($event);

        Notification::assertSentTo(
            $officer,
            InstitutionalWorkflowAlert::class,
            function (InstitutionalWorkflowAlert $notification) {
                return str_contains($notification->message, 'Chemical Spill at Dock 4');
            }
        );
    });

    test('WorkflowEngineService prevents cascading loops beyond max execution depth', function () {
        $tenant = Tenant::factory()->create(['name' => 'Workflow Depth Tenant', 'slug' => 'wf-depth-tenant']);

        $engine = app(WorkflowEngineService::class);

        // Rule that emits another event
        WorkflowRule::create([
            'tenant_id' => $tenant->id,
            'name' => 'Chained Rule',
            'trigger_event' => 'chain.loop',
            'conditions' => [],
            'actions' => [
                [
                    'type' => 'emit_event',
                    'event_name' => 'chain.loop',
                ],
            ],
            'is_active' => true,
        ]);

        $executions = $engine->trigger(
            tenantId: (string) $tenant->id,
            event: 'chain.loop',
            payload: ['key' => 'initial'],
            depth: 4 // Exceeds MAX_CHAIN_DEPTH (3)
        );

        PHPUnit\Framework\Assert::assertCount(1, $executions);
        PHPUnit\Framework\Assert::assertSame('loop_aborted', $executions[0]->status);
        PHPUnit\Framework\Assert::assertSame(4, $executions[0]->execution_depth);
    });

    test('memo.dispatched fires active rule and delivers alert, while disabled rule does not fire', function () {
        Notification::fake();

        $tenant = Tenant::factory()->create(['name' => 'Memo Workflow Tenant', 'slug' => 'memo-wf-tenant']);
        $role = Role::firstOrCreate(['name' => 'Registry Clerk', 'guard_name' => 'web'], ['uuid' => (string) Str::uuid()]);
        $clerk = User::factory()->create(['tenant_id' => $tenant->id, 'email' => 'clerk@memo.test']);
        $tenant->users()->attach($clerk->id);
        $clerk->assignRole($role);

        // Active rule
        WorkflowRule::create([
            'tenant_id' => $tenant->id,
            'name' => 'Memo Dispatched Alert',
            'trigger_event' => 'memo.dispatched',
            'conditions' => [],
            'actions' => [
                [
                    'type' => 'send_notification',
                    'target_role' => 'Registry Clerk',
                    'message' => 'New memo dispatched: {{subject}}',
                ],
            ],
            'is_active' => true,
        ]);

        // Disabled rule with different target role
        $otherRole = Role::firstOrCreate(['name' => 'Auditor', 'guard_name' => 'web'], ['uuid' => (string) Str::uuid()]);
        $auditor = User::factory()->create(['tenant_id' => $tenant->id, 'email' => 'auditor@memo.test']);
        $tenant->users()->attach($auditor->id);
        $auditor->assignRole($otherRole);

        WorkflowRule::create([
            'tenant_id' => $tenant->id,
            'name' => 'Disabled Memo Rule',
            'trigger_event' => 'memo.dispatched',
            'conditions' => [],
            'actions' => [
                [
                    'type' => 'send_notification',
                    'target_role' => 'Auditor',
                    'message' => 'Disabled alert',
                ],
            ],
            'is_active' => false,
        ]);

        event(new WorkflowDomainEvent(
            tenantId: (string) $tenant->id,
            eventName: 'memo.dispatched',
            payload: [
                'id' => 101,
                'subject' => 'Procurement Committee Briefing',
                'status' => 'sent',
            ]
        ));

        // Active rule delivered alert
        Notification::assertSentTo(
            $clerk,
            InstitutionalWorkflowAlert::class,
            fn (InstitutionalWorkflowAlert $n): bool => str_contains($n->message, 'Procurement Committee Briefing')
        );

        // Disabled rule never delivered alert
        Notification::assertNotSentTo($auditor, InstitutionalWorkflowAlert::class);
    });

    test('document.published fires active rule and delivers alert, while disabled rule does not fire', function () {
        Notification::fake();

        $tenant = Tenant::factory()->create(['name' => 'Doc Workflow Tenant', 'slug' => 'doc-wf-tenant']);
        $role = Role::firstOrCreate(['name' => 'Compliance Lead', 'guard_name' => 'web'], ['uuid' => (string) Str::uuid()]);
        $officer = User::factory()->create(['tenant_id' => $tenant->id, 'email' => 'compliance@doc.test']);
        $tenant->users()->attach($officer->id);
        $officer->assignRole($role);

        // Active rule
        WorkflowRule::create([
            'tenant_id' => $tenant->id,
            'name' => 'Document Published Alert',
            'trigger_event' => 'document.published',
            'conditions' => [
                ['field' => 'status', 'operator' => 'equals', 'value' => 'published'],
            ],
            'actions' => [
                [
                    'type' => 'send_notification',
                    'target_role' => 'Compliance Lead',
                    'message' => 'Controlled document published: {{title}} ({{tracking_code}})',
                ],
            ],
            'is_active' => true,
        ]);

        // Disabled rule
        $archivistRole = Role::firstOrCreate(['name' => 'Archivist', 'guard_name' => 'web'], ['uuid' => (string) Str::uuid()]);
        $archivist = User::factory()->create(['tenant_id' => $tenant->id, 'email' => 'archivist@doc.test']);
        $tenant->users()->attach($archivist->id);
        $archivist->assignRole($archivistRole);

        WorkflowRule::create([
            'tenant_id' => $tenant->id,
            'name' => 'Disabled Document Rule',
            'trigger_event' => 'document.published',
            'conditions' => [],
            'actions' => [
                [
                    'type' => 'send_notification',
                    'target_role' => 'Archivist',
                    'message' => 'Archivist alert',
                ],
            ],
            'is_active' => false,
        ]);

        event(new WorkflowDomainEvent(
            tenantId: (string) $tenant->id,
            eventName: 'document.published',
            payload: [
                'id' => 202,
                'title' => 'Clinical Governance SOP 2026',
                'tracking_code' => 'SOP-CLIN-001',
                'status' => 'published',
            ]
        ));

        // Active rule delivered alert
        Notification::assertSentTo(
            $officer,
            InstitutionalWorkflowAlert::class,
            fn (InstitutionalWorkflowAlert $n): bool => str_contains($n->message, 'Clinical Governance SOP 2026') && str_contains($n->message, 'SOP-CLIN-001')
        );

        // Disabled rule never delivered alert
        Notification::assertNotSentTo($archivist, InstitutionalWorkflowAlert::class);
    });

    test('facilities.request.created fires active rule and delivers alert, while disabled rule does not fire', function () {
        Notification::fake();

        $tenant = Tenant::factory()->create(['name' => 'Facilities Workflow Tenant', 'slug' => 'fac-wf-tenant']);
        $role = Role::firstOrCreate(['name' => 'Estate Officer', 'guard_name' => 'web'], ['uuid' => (string) Str::uuid()]);
        $officer = User::factory()->create(['tenant_id' => $tenant->id, 'email' => 'estate@fac.test']);
        $tenant->users()->attach($officer->id);
        $officer->assignRole($role);

        // Active rule
        WorkflowRule::create([
            'tenant_id' => $tenant->id,
            'name' => 'Emergency Facilities Alert',
            'trigger_event' => 'facilities.request.created',
            'conditions' => [
                ['field' => 'priority', 'operator' => 'equals', 'value' => 'emergency'],
            ],
            'actions' => [
                [
                    'type' => 'send_notification',
                    'target_role' => 'Estate Officer',
                    'message' => 'Emergency facility request created: {{title}} ({{work_order_number}})',
                ],
            ],
            'is_active' => true,
        ]);

        // Disabled rule
        $vendorRole = Role::firstOrCreate(['name' => 'Contractor', 'guard_name' => 'web'], ['uuid' => (string) Str::uuid()]);
        $contractor = User::factory()->create(['tenant_id' => $tenant->id, 'email' => 'contractor@fac.test']);
        $tenant->users()->attach($contractor->id);
        $contractor->assignRole($vendorRole);

        WorkflowRule::create([
            'tenant_id' => $tenant->id,
            'name' => 'Disabled Facilities Rule',
            'trigger_event' => 'facilities.request.created',
            'conditions' => [],
            'actions' => [
                [
                    'type' => 'send_notification',
                    'target_role' => 'Contractor',
                    'message' => 'Contractor alert',
                ],
            ],
            'is_active' => false,
        ]);

        event(new WorkflowDomainEvent(
            tenantId: (string) $tenant->id,
            eventName: 'facilities.request.created',
            payload: [
                'id' => 303,
                'work_order_number' => 'WO-INST-999',
                'title' => 'Main Generator Overheating',
                'priority' => 'emergency',
                'status' => 'open',
            ]
        ));

        // Active rule delivered alert
        Notification::assertSentTo(
            $officer,
            InstitutionalWorkflowAlert::class,
            fn (InstitutionalWorkflowAlert $n): bool => str_contains($n->message, 'Main Generator Overheating') && str_contains($n->message, 'WO-INST-999')
        );

        // Disabled rule never delivered alert
        Notification::assertNotSentTo($contractor, InstitutionalWorkflowAlert::class);
    });
});
