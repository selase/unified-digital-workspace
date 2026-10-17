<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Modules\QualityMonitoring\Models\Workplan;
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

describe('P1-6: Quality Monitoring Evidence Vault Test', function () {
    beforeEach(function () {
        foreach (['qm.workplans.view', 'qm.workplans.manage'] as $perm) {
            Permission::firstOrCreate(
                ['name' => $perm, 'guard_name' => 'web'],
                ['uuid' => (string) Str::uuid(), 'category' => 'quality-monitoring']
            );
        }
    });

    test('authorized user can securely upload and download evidence documents', function () {
        Storage::fake('local');

        $user = User::factory()->create();
        $tenant = setActiveTenantForTest($user);
        Session::put('active_tenant_id', $tenant->id);
        setPermissionsTeamId($tenant->id);
        app(TenantContext::class)->setTenant($tenant);
        app(ModuleManager::class)->enableForTenant('quality-monitoring', $tenant);

        $user->givePermissionTo(['qm.workplans.view', 'qm.workplans.manage']);

        $workplan = Workplan::create([
            'tenant_id' => $tenant->id,
            'title' => 'Cardiac Care Quality Milestone',
            'period_start' => now()->startOfYear()->toDateString(),
            'period_end' => now()->endOfYear()->toDateString(),
            'status' => 'active',
            'owner_id' => (string) $user->uuid,
        ]);

        $file = UploadedFile::fake()->create('clinical-audit-2026.pdf', 300, 'application/pdf');

        // 1. Upload evidence
        $uploadResponse = $this->actingAs($user)->post(
            route('quality-monitoring.workplans.evidence.upload', $workplan),
            [
                'evidence_file' => $file,
                'description' => 'Accreditation board clinical compliance audit report',
            ]
        );

        $uploadResponse->assertSessionHas('status', 'success');

        $workplan->refresh();
        $evidenceList = $workplan->metadata['evidence_files'] ?? [];
        expect($evidenceList)->toHaveCount(1);

        $entry = $evidenceList[0];
        expect($entry['name'])->toBe('clinical-audit-2026.pdf')
            ->and($entry['mime_type'])->toBe('application/pdf')
            ->and($entry['uploaded_by'])->toBe((string) $user->uuid);

        Storage::disk('local')->assertExists($entry['path']);

        // 2. Download evidence with private cache headers
        $downloadResponse = $this->actingAs($user)->get(
            route('quality-monitoring.workplans.evidence.download', [
                'workplan' => $workplan,
                'fileId' => $entry['id'],
            ])
        );

        $downloadResponse->assertOk();
        $downloadResponse->assertHeader('Cache-Control', 'must-revalidate, no-cache, private');
    });

    test('adversarial negative test: rejects malicious file extensions with 422', function () {
        Storage::fake('local');

        $user = User::factory()->create();
        $tenant = setActiveTenantForTest($user);
        Session::put('active_tenant_id', $tenant->id);
        setPermissionsTeamId($tenant->id);
        app(TenantContext::class)->setTenant($tenant);
        app(ModuleManager::class)->enableForTenant('quality-monitoring', $tenant);

        $user->givePermissionTo(['qm.workplans.manage']);

        $workplan = Workplan::create([
            'tenant_id' => $tenant->id,
            'title' => 'Test Security Workplan',
            'period_start' => now()->startOfYear()->toDateString(),
            'period_end' => now()->endOfYear()->toDateString(),
            'status' => 'active',
            'owner_id' => (string) $user->uuid,
        ]);

        // Attempt to upload executable shell script
        $badFile = UploadedFile::fake()->create('exploit.sh', 10, 'text/x-shellscript');

        $response = $this->actingAs($user)->post(
            route('quality-monitoring.workplans.evidence.upload', $workplan),
            [
                'evidence_file' => $badFile,
            ]
        );

        $response->assertSessionHasErrors(['evidence_file']);
    });

    test('adversarial negative test: rejects cross-tenant download with 404', function () {
        Storage::fake('local');

        $userA = User::factory()->create();
        $tenantA = setActiveTenantForTest($userA);

        $userB = User::factory()->create();
        $tenantB = setActiveTenantForTest($userB);

        Session::put('active_tenant_id', $tenantB->id);
        setPermissionsTeamId($tenantB->id);
        app(TenantContext::class)->setTenant($tenantB);
        app(ModuleManager::class)->enableForTenant('quality-monitoring', $tenantB);

        $userB->givePermissionTo(['qm.workplans.view']);

        $workplanA = Workplan::create([
            'tenant_id' => $tenantA->id,
            'title' => 'Tenant A Secret Workplan',
            'period_start' => now()->startOfYear()->toDateString(),
            'period_end' => now()->endOfYear()->toDateString(),
            'status' => 'active',
            'metadata' => [
                'evidence_files' => [
                    [
                        'id' => 'secret-file-id',
                        'name' => 'secret.pdf',
                        'path' => 'evidence/tenantA/secret.pdf',
                        'mime_type' => 'application/pdf',
                        'size_bytes' => 1024,
                    ],
                ],
            ],
        ]);

        // User B attempts to access Tenant A's evidence
        $response = $this->actingAs($userB)->get(
            route('quality-monitoring.workplans.evidence.download', [
                'workplan' => $workplanA,
                'fileId' => 'secret-file-id',
            ])
        );

        $response->assertNotFound();
    });
});
