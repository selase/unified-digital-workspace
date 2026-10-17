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

describe('P1-5: Report Builder PDF Export Test', function () {
    beforeEach(function () {
        foreach (['reports.view', 'reports.create', 'reports.manage', 'reports.export', 'incidents.view'] as $name) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['uuid' => (string) Str::uuid(), 'category' => 'reports']
            );
        }
    });

    test('authorized user can export report as PDF with application/pdf header and execution log', function () {
        $tenant = Tenant::factory()->create(['name' => 'Ridge Regional Hospital', 'slug' => 'ridge-reports']);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'analyst@ridge.test',
        ]);
        $tenant->users()->attach($user->id);

        $role = Role::firstOrCreate(['name' => 'Report Manager', 'guard_name' => 'web'], ['uuid' => (string) Str::uuid()]);
        $user->assignRole($role);
        $user->givePermissionTo(['reports.view', 'reports.manage', 'incidents.view']);

        Incident::create([
            'tenant_id' => $tenant->id,
            'reference_code' => 'INC-PDF-1',
            'title' => 'Radiology Scanner Calibrated',
            'impact' => 'low',
            'description' => 'Regular quarterly calibration',
        ]);

        $report = ReportDefinition::create([
            'tenant_id' => $tenant->id,
            'name' => 'Radiology Incident Register',
            'description' => 'Summary of biomedical equipment events',
            'data_source' => 'incidents',
            'selected_columns' => ['reference_code', 'title', 'impact'],
            'is_public' => true,
            'created_by_user_id' => (string) $user->uuid,
        ]);

        $response = $this->actingAs($user)->get(route('reports.export.pdf', $report));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');

        $contentDisposition = (string) $response->headers->get('Content-Disposition');
        expect($contentDisposition)->toContain('attachment;')
            ->and($contentDisposition)->toContain('.pdf');

        // Verify execution log recorded format 'pdf'
        $log = ReportExecutionLog::where('report_definition_id', $report->id)
            ->where('export_format', 'pdf')
            ->first();

        expect($log)->not->toBeNull()
            ->and($log->tenant_id)->toBe((string) $tenant->id)
            ->and($log->rows_count)->toBe(1);
    });

    test('adversarial negative test: unauthorized user cannot export private report as PDF', function () {
        $tenant = Tenant::factory()->create();

        $owner = User::factory()->create(['tenant_id' => $tenant->id]);
        $tenant->users()->attach($owner->id);
        $owner->givePermissionTo(['reports.view', 'incidents.view']);

        $unauthorizedUser = User::factory()->create(['tenant_id' => $tenant->id]);
        $tenant->users()->attach($unauthorizedUser->id);
        $unauthorizedUser->givePermissionTo(['reports.view', 'incidents.view']); // Has view, but not author or manager!

        $report = ReportDefinition::create([
            'tenant_id' => $tenant->id,
            'name' => 'Confidential Executive Briefing',
            'data_source' => 'incidents',
            'selected_columns' => ['title'],
            'is_public' => false, // Private!
            'created_by_user_id' => (string) $owner->uuid,
        ]);

        $response = $this->actingAs($unauthorizedUser)->get(route('reports.export.pdf', $report));

        // Must reject with 403 Forbidden
        $response->assertForbidden();
    });
});
