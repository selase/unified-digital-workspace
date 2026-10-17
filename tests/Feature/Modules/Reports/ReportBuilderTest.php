<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Modules\IncidentManagement\Models\Incident;
use App\Modules\Reports\Models\ReportDefinition;
use App\Modules\Reports\Models\ReportExecutionLog;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

describe('Phase 26: Cross-Module Custom Report Builder & CSV Exporter (TRK-REPORT-BUILDER)', function () {
    beforeEach(function () {
        foreach (['reports.view', 'reports.create', 'reports.export', 'incidents.view', 'crm.inquiries.view'] as $name) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['uuid' => (string) Str::uuid(), 'category' => 'reports']
            );
        }
    });

    test('creates custom report definition, previews rows, and streams valid CSV', function () {
        $tenantA = Tenant::factory()->create(['name' => 'Korle Bu Teaching Hospital', 'slug' => 'kbth-reports']);

        $admin = User::factory()->create([
            'tenant_id' => $tenantA->id,
            'email' => 'reporting-admin@kbth.test',
        ]);
        $tenantA->users()->attach($admin->id);

        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web'], ['uuid' => (string) Str::uuid()]);
        $admin->assignRole($superAdminRole);

        // Seed 3 incidents for Tenant A
        Incident::create([
            'tenant_id' => $tenantA->id,
            'reference_code' => 'INC-A01',
            'title' => 'Generator Voltage Spike',
            'impact' => 'high',
            'reported_via' => 'portal',
            'description' => 'Spike on busbar',
        ]);
        Incident::create([
            'tenant_id' => $tenantA->id,
            'reference_code' => 'INC-A02',
            'title' => 'HVAC Chiller Filter Clog',
            'impact' => 'medium',
            'reported_via' => 'phone',
            'description' => 'Clogged air filter',
        ]);
        Incident::create([
            'tenant_id' => $tenantA->id,
            'reference_code' => 'INC-A03',
            'title' => 'Bio-waste Leakage Contained',
            'impact' => 'critical',
            'reported_via' => 'email',
            'description' => 'Leakage in room 4',
        ]);

        // Seed 1 incident for Tenant B
        $tenantB = Tenant::factory()->create(['name' => 'Ridge Hospital', 'slug' => 'ridge-reports']);
        Incident::create([
            'tenant_id' => $tenantB->id,
            'reference_code' => 'INC-B99',
            'title' => 'Secret Ridge Incident',
            'impact' => 'critical',
            'reported_via' => 'portal',
            'description' => 'Private incident',
        ]);

        // 1. Create Report Definition
        $storeResponse = $this->actingAs($admin)
            ->withSession(['current_tenant_id' => $tenantA->id])
            ->post(route('reports.store'), [
                'name' => 'Incidents Operational Audit',
                'description' => 'Review of all safety incidents logged in Korle Bu',
                'data_source' => 'incidents',
                'selected_columns' => ['reference_code', 'title', 'impact', 'reported_via'],
                'is_public' => 1,
            ]);

        $storeResponse->assertRedirect();

        $report = ReportDefinition::where('tenant_id', $tenantA->id)
            ->where('name', 'Incidents Operational Audit')
            ->first();

        expect($report)->not->toBeNull()
            ->and($report->data_source)->toBe('incidents')
            ->and($report->selected_columns)->toContain('reference_code', 'title', 'impact', 'reported_via');

        // 2. View Preview
        $showResponse = $this->actingAs($admin)
            ->withSession(['current_tenant_id' => $tenantA->id])
            ->get(route('reports.show', $report));

        $showResponse->assertStatus(200)
            ->assertSee('Incidents Operational Audit')
            ->assertSee('Generator Voltage Spike')
            ->assertSee('HVAC Chiller Filter Clog')
            ->assertSee('Bio-waste Leakage Contained')
            ->assertDontSee('Secret Ridge Incident'); // Strict isolation

        // 3. Stream CSV Export
        $exportResponse = $this->actingAs($admin)
            ->withSession(['current_tenant_id' => $tenantA->id])
            ->get(route('reports.export.csv', $report));

        $exportResponse->assertStatus(200);
        expect($exportResponse->headers->get('content-type'))->toContain('text/csv');

        // Capture CSV content
        ob_start();
        $exportResponse->sendContent();
        $csvContent = ob_get_clean();

        expect($csvContent)->toContain('Reference Code')
            ->and($csvContent)->toContain('Title')
            ->and($csvContent)->toContain('Impact')
            ->and($csvContent)->toContain('Reported Via')
            ->and($csvContent)->toContain('INC-A01')
            ->and($csvContent)->toContain('Generator Voltage Spike')
            ->and($csvContent)->toContain('INC-A02')
            ->and($csvContent)->toContain('INC-A03')
            ->and($csvContent)->not->toContain('INC-B99'); // Strict isolation

        // Verify Execution Log
        $log = ReportExecutionLog::where('report_definition_id', $report->id)
            ->where('export_format', 'csv')
            ->first();

        expect($log)->not->toBeNull()
            ->and($log->rows_count)->toBe(3);
    });

    test('adversarial reject: non-allowlisted data source creation fails', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-audit-reject']);
        $admin = User::factory()->create(['tenant_id' => $tenant->id]);
        $tenant->users()->attach($admin->id);
        $admin->assignRole(Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web'], ['uuid' => (string) Str::uuid()]));

        $response = $this->actingAs($admin)
            ->withSession(['current_tenant_id' => $tenant->id])
            ->post(route('reports.store'), [
                'name' => 'Exploit Attempt',
                'data_source' => 'users_passwords_table',
                'selected_columns' => ['password', 'remember_token'],
            ]);

        $response->assertSessionHasErrors('data_source');
        expect(ReportDefinition::where('name', 'Exploit Attempt')->exists())->toBeFalse();
    });

    test('adversarial reject: user without permission to module data source gets 403 denied', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-audit-rbac']);

        // User only has reports.view, but NOT crm.inquiries.view
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $tenant->users()->attach($staff->id);
        $staff->givePermissionTo('reports.view');

        $report = ReportDefinition::create([
            'tenant_id' => $tenant->id,
            'name' => 'CRM Leads Report',
            'data_source' => 'crm_inquiries',
            'selected_columns' => ['inquiry_number', 'channel', 'status'],
            'created_by_user_id' => (string) $staff->uuid,
        ]);

        $response = $this->actingAs($staff)
            ->withSession(['current_tenant_id' => $tenant->id])
            ->get(route('reports.show', $report));

        $response->assertStatus(403);
    });

    test('adversarial reject: cross-tenant access to report definition returns 404', function () {
        $tenantA = Tenant::factory()->create(['slug' => 'tenant-a-rep']);
        $tenantB = Tenant::factory()->create(['slug' => 'tenant-b-rep']);

        $userB = User::factory()->create(['tenant_id' => $tenantB->id]);
        $tenantB->users()->attach($userB->id);
        $userB->assignRole(Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web'], ['uuid' => (string) Str::uuid()]));

        $reportA = ReportDefinition::create([
            'tenant_id' => $tenantA->id,
            'name' => 'Tenant A Private Report',
            'data_source' => 'incidents',
            'selected_columns' => ['reference_code'],
        ]);

        $response = $this->actingAs($userB)
            ->withSession(['current_tenant_id' => $tenantB->id])
            ->get(route('reports.show', $reportA));

        $response->assertStatus(404);
    });
});
