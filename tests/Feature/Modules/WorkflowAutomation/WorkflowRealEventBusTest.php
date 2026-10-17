<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Modules\WorkflowAutomation\Events\WorkflowDomainEvent;
use App\Modules\WorkflowAutomation\Listeners\WorkflowDomainEventListener;
use App\Modules\WorkflowAutomation\Models\WorkflowRule;
use App\Modules\WorkflowAutomation\Services\WorkflowEngineService;
use App\Notifications\InstitutionalWorkflowAlert;
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantContext;
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

    test('crm.inquiry.created is dispatched from CmsEnquiryController::store', function () {
        Event::fake([WorkflowDomainEvent::class]);
        Illuminate\Support\Facades\Mail::fake();

        $tenant = Tenant::factory()->create(['name' => 'CMS Inquiry Tenant', 'slug' => 'cms-inquiry-tenant', 'email' => 'admin@cms-inquiry.test']);
        app(TenantContext::class)->setTenant($tenant);
        app(ModuleManager::class)->enableForTenant('cms-core', $tenant);

        $token = App\Modules\CmsCore\Support\EnquiryToken::issue(time() - 10);

        $response = $this->withSession(['active_tenant_id' => $tenant->id])
            ->postJson(route('cms-core.public.enquiries.store'), [
                'type' => App\Modules\CmsCore\Support\EnquiryType::Partnership->value,
                'name' => 'Kwame Mensah',
                'email' => 'kwame@example.com',
                'phone' => '+233201234567',
                'organisation' => 'Health Systems Africa',
                'message' => 'We would like to partner on community healthcare initiatives.',
                'form_token' => $token,
            ]);

        $response->assertOk();

        Event::assertDispatched(WorkflowDomainEvent::class, function (WorkflowDomainEvent $event) use ($tenant) {
            return $event->tenantId === (string) $tenant->id
                && $event->eventName === 'crm.inquiry.created'
                && $event->payload['full_name'] === 'Kwame Mensah'
                && $event->payload['email'] === 'kwame@example.com';
        });
    });

    test('crm.inquiry.created is dispatched from PublicRealEstateWebController::inquire', function () {
        Event::fake([WorkflowDomainEvent::class]);

        $tenant = Tenant::factory()->create(['name' => 'Real Estate Inquiry Tenant', 'slug' => 're-inquiry-tenant']);
        app(TenantContext::class)->setTenant($tenant);
        app(ModuleManager::class)->enableForTenant('crm', $tenant);

        $listing = App\Modules\RealEstate\Models\PropertyListing::create([
            'tenant_id' => (string) $tenant->id,
            'title' => 'Prime Commercial Plot - Airport City',
            'location' => 'Airport Residential Area, Accra',
            'price_pesewas' => 50000000,
            'is_published' => true,
        ]);

        $response = $this->post(route('real-estate.public.inquire', $listing), [
            'full_name' => 'Abena Serwaa',
            'email' => 'abena@example.com',
            'phone' => '+233241234567',
            'message' => 'Interested in inspecting this commercial plot.',
        ]);

        $response->assertSessionHasNoErrors();

        Event::assertDispatched(WorkflowDomainEvent::class, function (WorkflowDomainEvent $event) use ($tenant) {
            return $event->tenantId === (string) $tenant->id
                && $event->eventName === 'crm.inquiry.created'
                && $event->payload['full_name'] === 'Abena Serwaa'
                && $event->payload['email'] === 'abena@example.com';
        });
    });

    test('crm.lead.converted is dispatched from CrmWebController::convertLead', function () {
        Event::fake([WorkflowDomainEvent::class]);

        Permission::firstOrCreate(['name' => 'crm.convert', 'guard_name' => 'web'], ['uuid' => (string) Str::uuid(), 'category' => 'crm']);
        Permission::firstOrCreate(['name' => 'crm.view', 'guard_name' => 'web'], ['uuid' => (string) Str::uuid(), 'category' => 'crm']);

        $user = User::factory()->create();
        $tenant = setActiveTenantForTest($user);
        app(TenantContext::class)->setTenant($tenant);
        app(ModuleManager::class)->enableForTenant('crm', $tenant);
        $user->givePermissionTo('crm.convert', 'crm.view');

        $lead = App\Modules\Crm\Models\CrmLead::create([
            'tenant_id' => (string) $tenant->id,
            'reference_code' => 'LEAD-TEST-001',
            'source' => 'direct',
            'full_name' => 'Kofi Annan',
            'organisation' => 'Annan Global Foundation',
            'email' => 'kofi@annan-foundation.org',
            'status' => App\Modules\Crm\Models\CrmLead::STATUS_NEW,
        ]);

        $response = $this->actingAs($user)->post(route('crm.leads.convert', $lead), [
            'account_name' => 'Annan Global Foundation',
            'account_type' => 'enterprise',
            'currency' => 'GHS',
        ]);

        $response->assertSessionHasNoErrors();

        Event::assertDispatched(WorkflowDomainEvent::class, function (WorkflowDomainEvent $event) use ($tenant, $lead) {
            return $event->tenantId === (string) $tenant->id
                && $event->eventName === 'crm.lead.converted'
                && $event->payload['lead_id'] === $lead->id
                && $event->payload['account_name'] === 'Annan Global Foundation';
        });
    });

    test('store.order.placed is dispatched from StoreInventoryService::placeOrder', function () {
        Event::fake([WorkflowDomainEvent::class]);

        $tenant = Tenant::factory()->create(['name' => 'Store Event Tenant', 'slug' => 'store-evt-tenant']);

        $product = App\Modules\Store\Models\StoreProduct::create([
            'tenant_id' => (string) $tenant->id,
            'name' => 'Institutional Lanyard Pack',
            'slug' => 'lanyard-pack',
            'sku' => 'LAN-001',
            'price_cents' => 2500,
            'stock_quantity' => 100,
            'is_active' => true,
        ]);

        $service = app(App\Modules\Store\Services\StoreInventoryService::class);
        $order = $service->placeOrder(
            tenantId: (string) $tenant->id,
            customerData: [
                'customer_name' => 'Esi Dapaah',
                'customer_email' => 'esi@store.test',
                'payment_method' => 'portal_balance',
            ],
            cartItems: [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                ],
            ]
        );

        expect($order->status)->toBe('pending');

        Event::assertDispatched(WorkflowDomainEvent::class, function (WorkflowDomainEvent $event) use ($tenant, $order) {
            return $event->tenantId === (string) $tenant->id
                && $event->eventName === 'store.order.placed'
                && $event->payload['order_id'] === $order->id
                && $event->payload['customer_email'] === 'esi@store.test'
                && $event->payload['total_amount_cents'] === 5000;
        });
    });
});
